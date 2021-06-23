<?php

namespace Neocom\JWK\Contracts\Repositories;

use Neocom\JWK\Models\Key;

interface KeyRepository
{
    /**
     * Get all keys in the database
     *
     * @param array $options  List of options to apply to the query
     * @return Collection|Key[]
     */
    public function getAll(array $options = []);

    /**
     * Return a single key instance from the database
     *
     * @param string $keyID  The Key ID/Thumbprint
     * @param array $options  List of options to apply to the query
     * @return Key
     */
    public function getSingleKey(string $keyID, array $options = []);

    /**
     * Create a new key instance in the database using key data
     *
     * @param array $keyData
     * @param array $tags
     * @return bool
     */
    public function createKey(array $keyData, array $tags = []);

    /**
     * Revoke a single key in the database
     *
     * @param Key|string  $key
     * @return bool
     */
    public function revokeKey($key);

    /**
     * Unrevoke a single key in the database
     *
     * @param Key|string  $key
     * @return bool
     */
    public function unrevokeKey($key);

    /**
     * Delete a single key in the database
     *
     * @param Key|string  $key
     * @return bool
     */
    public function deleteKey($key);

    /**
     * Force delete a key in the database
     *
     * @param Key|string  $key
     * @return bool
     */
    public function forceDeleteKey($key);
}
