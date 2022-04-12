<?php

namespace Neocom\JWK\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
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
        ], true);

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
        ], true);
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
        ]);

        // Set up the query builder
        $query = Key::query();

        // Add the revoked and trashed clauses
        $query->withRevoked($options['includeRevoked']);
        $query->withTrashed($options['includeTrashed']);

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
            $query->whereHas('tags', function (Builder $query) use ($tags) {
                foreach ($tags as $key => $value) {
                    $query->where(function (Builder $query) use ($key, $value) {
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
        // Get the key, if required
        if (! ($key instanceof Key)) {
            $key = $this->getSingleKey($key);
        }

        // Delete the key (this will soft-delete the key by default)
        return $key->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function forceDeleteKey($key)
    {
        // Get the key, if required. (Including trashed or soft-deleted keys)
        if (! ($key instanceof Key)) {
            $key = $this->getSingleKey($key, ['includeRevoked' => true, 'includeTrashed' => true]);
        }

        // Delete the key (this will soft-delete the key by default)
        return $key->forceDelete();
    }
}
