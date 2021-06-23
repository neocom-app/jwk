<?php

namespace Neocom\JWK\Contracts\Repositories;

interface EncryptionKeyRepository
{
    /**
     * Get Encryption Key for a client
     *
     * @param string $hash  Hash to search on
     * @return string
     */
    public function getEncryptionKey(string $hash);

    /**
     * Create a new Encryption Key
     *
     * @param string $key  Encryption Key Text
     * @param string $hash  Encryption Key Hash
     * @return bool
     */
    public function createEncryptionKey(string $key, string $hash);

    /**
     * Delete an existing Encryption Key
     *
     * @param string $hash  Hash to search on
     * @return bool
     */
    public function deleteEncryptionKey(string $hash);

    /**
     * Force Delete an existing Encryption Key
     *
     * @param string $hash  Hash to search on
     * @return bool
     */
    public function forceDeleteEncryptionKey(string $hash);
}
