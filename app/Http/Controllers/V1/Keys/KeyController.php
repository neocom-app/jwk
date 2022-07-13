<?php

namespace Neocom\JWK\Http\Controllers\V1\Keys;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Neocom\JWK\Contracts\Cache\KeyCache;
use Neocom\JWK\Contracts\Helpers\KeyConverter;
use Neocom\JWK\Contracts\Helpers\KeyEncryptor;
use Neocom\JWK\Contracts\Helpers\KeyGenerator;
use Neocom\JWK\Contracts\Repositories\EncryptionKeyRepository;
use Neocom\JWK\Contracts\Repositories\KeyRepository;
use Neocom\JWK\Http\Controllers\Controller;
use Neocom\JWK\Utils\JWKUtils;
use Neocom\JWK\Utils\KeyUtils;

class KeyController extends Controller
{
    private KeyRepository $repo;
    private KeyGenerator $generator;
    private KeyConverter $converter;
    private KeyCache $cache;

    public function __construct(KeyRepository $repository, KeyGenerator $generator, KeyConverter $converter, KeyCache $cache)
    {
        $this->repo = $repository;
        $this->generator = $generator;
        $this->converter = $converter;
        $this->cache = $cache;
    }

    public function listKeys(Request $request, string $keyType)
    {
        // Extract the key type
        $keyType = $request->getKeyType($keyType);

        // Catch errors
        try {

            // Run the key process through the encryptor if needed
            $keySet = $this->encryptKeySetIfRequired(
                $request,
                function () use ($keyType) {
                    return in_array($keyType, ['all', 'private']);
                },
                function () use ($request, $keyType) {

                    // Pull out all of the tags
                    $tags = $request->getParametersWithPrefix('tag:');

                    // Set flags based on the passed key type
                    $includeRevokedKeys = in_array($keyType, ['all', 'public']);
                    $convertToPublic    = in_array($keyType, ['public']);

                    // Create the cache key
                    $cacheKey = $keyType;

                    // If there are any tags, extract them and add it to the cache key
                    if (count($tags)) {

                        // Convert the tags into a md5 for the cache
                        ksort($tags);
                        $tagsKey = md5(json_encode($tags));

                        // Create the cache key
                        $cacheKey .= ':'.$tagsKey;
                    }

                    // Attempt to get the list of keys from the cache
                    if (($keySet = $this->cache->get('list', $cacheKey)) === null) {

                        // Get all the keys from the database
                        /** @var Collection */
                        $keys = $this->repo->getAll(['includeRevoked' => $includeRevokedKeys, 'tags' => $tags]);

                        // Convert each key into a JWK
                        $keys = $keys->map(function ($item) use ($convertToPublic) {
                            return $this->converter->convertKeyToJWK($item, $convertToPublic);
                        });

                        // Convert the list of keys to a JWKSet
                        $keySet = JWKUtils::createJWKSet($keys);

                        // If there are no results, don't attempt to cache it
                        if ($keys->isNotEmpty()) {

                            // Store the result in the cache
                            $this->cache->store('list', $cacheKey, $keySet);
                        }

                    // Data was obtained from the cache, force it into a JWKSet object
                    } else {
                        $keySet = JWKUtils::createJWKSet($keySet, true);
                    }

                    return $keySet;
                }
            );

            return response()->json($keySet);

        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    $e->getMessage()
                ]
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function getSingleKey(Request $request, string $keyID)
    {
        // Load the key from the database
        $key = $this->repo->getSingleKey($keyID, ['includeRevoked' => true]);

        // Run the key process through the encryptor if needed
        $keySet = $this->encryptKeySetIfRequired(
            $request,
            function () use ($key) {
                return (! $key->revoked) || false;
            },
            function ($_, bool $resultToBeEncrypted) use ($key) {

                // Convert the key to a JWKSet
                $jwk    = $this->converter->convertKeyToJWK($key, ! $resultToBeEncrypted);
                $keySet = JWKUtils::createJWKSet($jwk);

                // Return the keyset
                return $keySet;
            },
            false
        );

        return response()->json($keySet);
    }

    public function generateKey(Request $request)
    {
        // Get the request body
        $data = $request->all();

        // Extract the key type from the data with a default of RSA
        $keyType = Arr::get($data, 'key_type', 'RSA');

        // Get the list of valid key parameters
        $keyOptions = KeyUtils::getDefaultParameters($keyType, true);

        // Apply the defaults to the request body
        $data = Arr::applyDefaults($data, $keyOptions);

        // Pull out all of the key params from the body
        $keyParams = Arr::only($data, array_keys($keyOptions));

        // Get all of the tags to be assigned to this key
        $tags       = Arr::get($data, 'tags', []);
        $inlineTags = $request->getParametersWithPrefix('tag:');

        // Merge the two tag lists together
        $tags = Arr::applyDefaults($tags, $inlineTags);

        // Generate a key for the requested parameters
        $jwk = $this->generator->generateKey($keyParams);

        // Convert the JWK into a json key and save it to the database
        $key = $this->converter->convertJwkToKeyData($jwk);
        $created = $this->repo->createKey($key, $tags);

        // Purge the relevant cache entries
        $this->cache->purge('list');

        // If the key was created, return a 201, otherwise return a 422
        if ($created) {
            $response = response()->noContent(Response::HTTP_CREATED, [
                'Location' => route('v1.keys.singleKey.getKey', [ 'key_id' => $key['kid'] ]),
                'X-JWK-Thumbprint' => $key['kid'],
            ]);
        } else {
            $response = response()->json([
                'error' => [
                    'Unable to generate key'
                ]
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
    }

    public function rotateKey(Request $request, string $keyID)
    {
        // Get the requested key
        $key = $this->repo->getSingleKey($keyID, ['includeTags' => true]);

        // Get the key parameters
        $jwk       = $this->converter->convertKeyToJWK($key);
        $keyParams = $this->generator->getKeyParams($jwk);

        // Generate a new key using the same parameters
        $newJwk     = $this->generator->generateKey($keyParams);
        $newJwkData = $this->converter->convertJwkToKeyData($newJwk);

        // Extract any tags from the old key
        $tags = $key->tags->mapWithKeys(function ($tag) {
            return [ $tag->tag_name => $tag->tag_value ];
        })->toArray();

        // Wrap the database functions in a transaction
        $successful = DB::transaction(function () use ($key, $newJwkData, $tags) {

            // Revoke the old key
            $this->repo->revokeKey($key);

            // Attempt to create the new key
            $this->repo->createKey($newJwkData, $tags);

            return true;
        });

        // Key was successfully created, return a 204 status code response and purge the cache
        if ($successful) {

            // Purge the relevant caches
            $this->cache->purge('list');

            // Set the 204 response to return
            $response = response()->noContent(Response::HTTP_NO_CONTENT, [
                'Location' => route('v1.keys.getKey', [ 'key_id' => $newJwkData['kid'] ]),
                'X-JWK-Thumbprint' => $newJwkData['kid'],
            ]);

        // Otherwise the key didn't rotate successfully, throw an error
        } else {

            // Set the error response
            $response = response()->json([
                'error' => [
                    'The key was unable to be rotated'
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $response;
    }

    public function revokeKey(Request $request, string $keyID)
    {
        // Get the requested key
        $key = $this->repo->getSingleKey($keyID);

        // Revoke the old key
        $revoked = $this->repo->revokeKey($key);

        // Purge the relevant caches
        $this->cache->purge('list');

        // Return with a 204 status code saying that the key has been revoked
        if ($revoked) {
            $response = response()->noContent();
        } else {
            $response = response()->json([
                'error' => [
                    'Unable to revoke key'
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $response;
    }

    public function deleteKey(Request $request, string $keyID)
    {
        // Get the requested key
        $key = $this->repo->getSingleKey($keyID, ['includeRevoked' => true]);

        // Do we need to force delete the key
        $forceDelete = $request->post('force', false);

        // If this is a force delete, swap the method over
        $methodName = 'deleteKey';
        if ($forceDelete) {
            $methodName = 'forceDeleteKey';
        }

        // Delete the old key
        $deleted = $this->repo->{$methodName}($key);

        // Purge the relevant caches
        $this->cache->purge('list');

        // Return with a 204 status code saying that the key has been revoked or return with a 500 if it failed
        if ($deleted) {
            $response = response()->noContent();
        } else {
            $response = response()->json([
                'error' => [
                    'Unable to delete key'
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $response;
    }

    public function cleanupKeys(Request $request)
    {
        // Pull out all of the tags
        $tags = $request->getParametersWithPrefix('tag:');

        // Get the list of keys to be deleted
        $keys = $this->repo->getAll(['tags' => $tags, 'onlyRevoked' => true]);

        // Get the number of days to cleanup keys from. Defaults to 30 days
        $days = $request->post('days', 30);

        // Filter the list to keys that were revoked after a certian timeframe
        $keys = $keys->filter->canBeCleanedUp($days);

        // Do we need to force delete the keys
        $forceDelete = $request->post('force', false);

        // If this is a force delete, swap the method over
        $methodName = 'deleteKey';
        if ($forceDelete) {
            $methodName = 'forceDeleteKey';
        }

        // Get the list of keys to be deleted
        $deleted = $this->repo->{$methodName}($keys);

        // Purge the relevant caches
        $this->cache->purge('list');

        // Return with a 204 status code saying that the key has been revoked or return with a 500 if it failed
        if ($deleted) {
            $response = response()->noContent();
        } else {
            $response = response()->json([
                'error' => [
                    'Unable to cleanup key(s)'
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $response;
    }

    protected function encryptKeySetIfRequired(Request $request, $shouldBeEncrypted, Closure $callback, bool $throwOnMissingKey = true)
    {
        // Load the encryptor from DI
        /** @var \Neocom\JWK\Contracts\Helpers\KeyEncryptor */
        $encryptor = app()->make(KeyEncryptor::class);

        // Get any encryption settings if required
        $encryptionSettings = $request->getParametersWithPrefix('encryption:');

        // Determine if encryption should be enabled
        $encryptResult = value($shouldBeEncrypted, $request) ?: false;

        // If the encryptor is not enabled, just return the callback
        if (! $encryptor->isEncryptionEnabled() || ! $encryptResult) {
            return $callback($request, false);
        }

        // If no key has been provided, throw an error
        if (Arr::has($encryptionSettings, 'key')) {
            $encryptionKey = Arr::get($encryptionSettings, 'key', '');
        } else if (! $throwOnMissingKey) {
            return $callback($request, false);
        } else {
            throw new \Exception('No encryption key has been provided');
        }

        // Load the encryption key repo from DI
        /** @var \Neocom\JWK\Contracts\Repositories\EncryptionKeyRepository */
        $encryptionKeyRepo = app()->make(EncryptionKeyRepository::class);

        // If an encryption key has been provided, attempt to get the actual key from the database
        $encryptionKey = $encryptionKeyRepo->getEncryptionKey($encryptionKey);

        // Get the JWK set from the callback
        $jwkSet = $callback($request, $encryptResult);

        // Encrypt the key set with the encryption key
        $encryptedKeySet = $encryptor->encryptKeySet($jwkSet, $encryptionKey);

        return $encryptedKeySet;
    }
}
