<?php

namespace Neocom\JWK\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Neocom\JWK\Contracts\Repositories\KeyRepository as KeyRepositoryContract;
use Neocom\JWK\Models\Key;

class KeyRepository implements KeyRepositoryContract
{
    /**
     * {@inheritdoc}
     */
    public function getAll(array $options = [])
    {
        // List of default options
        $options = Arr::applyDefaults($options, [
            'tags'           => [],
            'includeRevoked' => true,
        ]);

        return $this->getKeyQueryBuilder($options)->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getSingleKey(string $keyID, array $options = [])
    {
        // List of default options
        $options = Arr::applyDefaults($options, [
            'includeTags'    => false,
            'includeRevoked' => false,
            'includeTrashed' => false,
        ]);
        $options = array_merge(['key' => $keyID], $options);

        return $this->getKeyQueryBuilder($options)->firstOrFail();
    }

    /**
     * Get the initial query builder which includes the trashed and revoked clauses
     *
     * @param array $options  List of options to apply to the query builder
     * @return Builder
     */
    protected function getKeyQueryBuilder(array $options)
    {
        // Apply default options
        $options = Arr::applyDefaults($options, [
            'key'            => '',
            'tags'           => [],
            'includeTags'    => false,
            'includeRevoked' => false,
            'includeTrashed' => false,
            'onlyRevoked'    => false,
            'onlyTrashed'    => false,
        ], true);

        // Set up the query builder
        $query = Key::query();

        // For the include/only clauses. If both of them are specified, then the only clause trumps over the include clause
        if ($options['includeRevoked'] && $options['onlyRevoked']) $options['includeRevoked'] = false;
        if ($options['includeTrashed'] && $options['onlyTrashed']) $options['includeTrashed'] = false;

        // Add the revoked and trashed clauses to the query.
        if ($options['includeRevoked']) $query->withRevoked();
        if ($options['includeTrashed']) $query->withTrashed();

        // Add the only revoked/trashed clases if they are required
        if ($options['onlyRevoked']) $query->onlyRevoked();
        if ($options['onlyTrashed']) $query->onlyTrashed();

        // If tags need to be included, load the relationship
        if (Arr::get($options, 'includeTags')) {
            $query->with('tags');
        }

        // If we have a key id, add the relevant clause
        if (($key = Arr::get($options, 'key')) !== '') {
            if (Str::isUuid($key)) {
                $query->where('id', $key);
            } else {
                $query->where('thumbprint', $key);
            }
        }

        // If we have any tags, add the relevant clause(s)
        if (($tags = collect(Arr::get($options, 'tags')))->isNotEmpty()) {
            $query->where(function (Builder $query) use ($tags) {
                foreach ($tags as $key => $value) {
                    $query->whereHas('tags', function (Builder $query) use ($key, $value) {
                        $query->where('tag_name', '=', $key)->where('tag_value', '=', $value);
                    });
                }
            });
        }

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function createKey(array $keyData, array $tags = [])
    {
        // Create a new key model
        $key = new Key();

        // Add the key data to the model
        $key->key_data = $keyData;

        // Set additional parameters from the key data to the model
        $additionalAttrs = collect(['type' => 'kty', 'thumbprint' => 'kid'])->mapWithKeys(function ($item, $key) use ($keyData) {
            if (! isset($keyData[$item])) {
                return null;
            }
            return [ $key => $keyData[$item] ];
        })->toArray();
        $key->forceFill($additionalAttrs);

        // If there any tags, add them to this key entry as well
        if (count($tags) > 0) {
            $tags = collect($tags)->mapWithKeys(function ($value, $key) {
                return [
                    $key => [
                        'tag_name' => $key,
                        'tag_value' => $value,
                    ],
                ];
            })->values()->toArray();
        }

        // Wrap this block in a transaction to catch errors
        return DB::transaction(function () use ($key, $tags) {

            // Save the key
            $key->save();

            // If there are tags, create them
            if (! empty($tags)) {
                $key->tags()->createMany($tags);
            }

            // If we reached here, then we can assume the save(s) were successful
            return true;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function revokeKey($key)
    {
        // Get the key, if required
        if (! ($key instanceof Key)) {
            $key = $this->getSingleKey($key);
        }

        // Trigger the revoke command on the model
        return $key->revoke();
    }

    /**
     * {@inheritdoc}
     */
    public function unrevokeKey($key)
    {
        // Get the key, if required
        if (! ($key instanceof Key)) {
            $key = $this->getSingleKey($key, ['includeRevoked' => true]);
        }

        // Unrevoke the model
        $key->{$key->getRevokedAtColumn()} = null;
        return $key->save();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteKey($key)
    {
        return $this->deleteKeyCollection($key);
    }

    /**
     * {@inheritdoc}
     */
    public function forceDeleteKey($key)
    {
        // Force delete all listed keys
        return $this->deleteKeyCollection($key, ['includeRevoked' => true, 'includeTrashed' => true], 'forceDelete');
    }

    /**
     * Delete a single or collection of keys
     *
     * @param mixed $keys
     * @param array $options
     * @param string $method
     * @return bool
     */
    protected function deleteKeyCollection($keys, array $keyOptions = [], string $deleteMethod = 'delete')
    {
        // Make sure that the passed key is in a colletion
        $keys = Collection::wrap($keys);

        // If the list is empty, just exit
        if ($keys->isEmpty()) {
            return true;
        }

        // Make sure that each key is a key model instance
        $keys = $keys->map(function ($key) use ($keyOptions) {
            if (! ($key instanceof Key)) {
                $key = $this->getSingleKey($key, $keyOptions);
            }
            return $key;
        });

        // Delete each of the keys (this will soft-delete the key by default)
        $results = $keys->map->{$deleteMethod}();

        return true;
    }
}
