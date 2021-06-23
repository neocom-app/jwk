<?php

namespace Neocom\JWK\Helpers;

use Jose\Component\Core\Algorithm;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\Core\Util\JsonConverter;
use Jose\Component\Encryption\Algorithm\ContentEncryption;
use Jose\Component\Encryption\Algorithm\KeyEncryption;
use Jose\Component\Encryption\Compression\CompressionMethodManager;
use Jose\Component\Encryption\Compression\Deflate;
use Jose\Component\Encryption\JWEBuilder;
use Jose\Component\Encryption\Serializer\JSONFlattenedSerializer;
use Illuminate\Support\Collection;
use Neocom\JWK\Contracts\Helpers\KeyEncryptor as KeyEncryptorContract;
use Neocom\JWK\Contracts\Helpers\KeyGenerator;

class KeyEncryptor implements KeyEncryptorContract
{
    protected KeyGenerator $generator;

    public function __construct(KeyGenerator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * {@inheritdoc}
     */
    public function isEncryptionEnabled()
    {
        return config('jwk.encryption.enabled', false);
    }

    /**
     * {@inheritdoc}
     */
    public function encryptKeySet($keys, $encryptionKey)
    {
        // If encryption is disabled, bail with the unencrypted keys
        if (! $this->isEncryptionEnabled()) {
            return $keys;
        }

        // Check if any of the parameters are valid
        if (! ($keys instanceof JWKSet || $keys instanceof JWK) || ! $encryptionKey) {
            return null;
        }

        // Generate a Jose encryption key from the passed encryption key
        $encryptionJwk = $this->generator->generateKey(['key_type' => 'oct', 'secret' => $encryptionKey]);

        // Attempt to get the payload of the keyset
        switch (true) {
            case $keys instanceof JWKSet: $keyPayload = $this->getJWKSetData($keys); break;
            case $keys instanceof JWK:    $keyPayload = $this->getJWKData($keys);    break;

            default: $keyPayload = null; break;
        }

        // If a payload couldn't be extracted, bail
        if ($keyPayload === null) {
            return null;
        }

        // Get the JWE builder
        $builder = $this->getJweBuilder();

        // Get the JWE serializer
        $serializer = new JSONFlattenedSerializer();

        // JWE Header
        $header = collect([
            'alg' => $this->getEncryptionAlgorithm('key', 'PBES2-HS256+A128KW'),
            'enc' => $this->getEncryptionAlgorithm('content', 'A128GCM'),
            'zip' => $this->getCompressionMethod(),
            'cty' => $keyPayload['cty'],
        ]);

        // Add the contents and encryption key
        $builder = $builder
            ->withPayload(JsonConverter::encode($keyPayload['payload']))
            ->withSharedProtectedHeader($header->filter()->toArray())
            ->addRecipient($encryptionJwk);

        // Create the token
        $jwe = $builder->build();

        // Serialize the JWE into a JSON response
        $serializedJwe = $serializer->serialize($jwe);

        // Decode the serialized jwe into an array, since laravel encodes the response as json
        $serializedJwe = JsonConverter::decode($serializedJwe);

        return $serializedJwe;
    }

    protected function getJWKSetData(JWKSet $jwks)
    {
        // If there is one key in the key set, pass this to the key extractor instead
        if ($jwks->count() === 1) {
            return $this->getJWKData(head($jwks->all()));
        }

        // Get all of the data inside the key set and return it as an array
        return [
            'payload' => $jwks->jsonSerialize(),
            'cty'     => 'jwk-set+json',
        ];
    }

    protected function getJWKData(JWK $jwk)
    {
        // Simple extract the data as a json payload and set the content type
        return [
            'payload' => $jwk->jsonSerialize(),
            'cty'     => 'jwk+json',
        ];
    }

    /**
     * Get the JWE Builder instance
     *
     * @return JWEBuilder
     */
    protected function getJweBuilder()
    {
        // Create the builder with a list of encryption algorithms that can be used
        $builder = new JWEBuilder(
            new AlgorithmManager($this->getEncryptionAlgorithms('key')->toArray()),
            new AlgorithmManager($this->getEncryptionAlgorithms('content')->toArray()),
            new CompressionMethodManager([ new Deflate() ]),
        );

        // Initialize the builder
        $builder->create();

        // Create the builder instance
        return $builder;
    }

    /**
     * @return Collection
     */
    protected function getEncryptionAlgorithms(string $type)
    {
        // List of algorithms and their types
        $algorithms = collect([
            [ 'type' => 'key', 'class' => KeyEncryption\A128KW::class ],
            [ 'type' => 'key', 'class' => KeyEncryption\A192KW::class ],
            [ 'type' => 'key', 'class' => KeyEncryption\A256KW::class ],
            [ 'type' => 'key', 'class' => KeyEncryption\A128GCMKW::class ],
            [ 'type' => 'key', 'class' => KeyEncryption\A192GCMKW::class ],
            [ 'type' => 'key', 'class' => KeyEncryption\A256GCMKW::class ],
            [ 'type' => 'key', 'class' => KeyEncryption\Dir::class ],
            [ 'type' => 'key', 'class' => KeyEncryption\PBES2HS256A128KW::class ],
            [ 'type' => 'key', 'class' => KeyEncryption\PBES2HS384A192KW::class ],
            [ 'type' => 'key', 'class' => KeyEncryption\PBES2HS512A256KW::class ],

            [ 'type' => 'content', 'class' => ContentEncryption\A128GCM::class ],
            [ 'type' => 'content', 'class' => ContentEncryption\A192GCM::class ],
            [ 'type' => 'content', 'class' => ContentEncryption\A256GCM::class ],
            [ 'type' => 'content', 'class' => ContentEncryption\A128CBCHS256::class ],
            [ 'type' => 'content', 'class' => ContentEncryption\A192CBCHS384::class ],
            [ 'type' => 'content', 'class' => ContentEncryption\A256CBCHS512::class ],
        ]);

        // Filter the list and then create all of the required instances
        return $algorithms->filter(function ($algorithm) use ($type) {
            return $algorithm['type'] === $type;
        })->transform(function($algorithm) {
            return new $algorithm['class'];
        });
    }

    /**
     * @return string
     */
    protected function getEncryptionAlgorithm(string $type, string $default)
    {
        // Get the selected algorithms from the config and select the required config
        $algorithms = [
            'key'     => config('jwk.encryption.algorithms.key_algorithm'),
            'content' => config('jwk.encryption.algorithms.content_algorithm'),
        ];
        $selectedAlgorithm = $algorithms[$type] ?: null;

        // Check if the algorithm is a valid one by comparing against the list of algorithms
        $algorithms = $this->getEncryptionAlgorithms($type);
        $algorithms = $algorithms->map(function (Algorithm $algorithm) {
            return $algorithm->name();
        });
        if (! $algorithms->contains($selectedAlgorithm)) {
            return $default;
        }

        return $selectedAlgorithm;
    }

    /**
     * @return string|null
     */
    protected function getCompressionMethod()
    {
        return config('jwk.encryption.enable_jwe_payload_compression', false) ? 'DEF' : null;
    }
}
