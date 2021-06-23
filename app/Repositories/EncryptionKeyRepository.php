<?php

namespace Neocom\JWK\Repositories;

use Neocom\JWK\Contracts\Repositories\EncryptionKeyRepository as EncryptionKeyRepositoryContract;
use Neocom\JWK\Models\EncryptionKey;

class EncryptionKeyRepository implements EncryptionKeyRepositoryContract
{
    /**
     * {@inheritdoc}
     */
    public function getEncryptionKey(string $hash)
    {
        $encryptionKey = $this->getEncryptionKeyModel($hash);
        return $encryptionKey->key;
    }

    /**
     * Get the Encryption Key model from the hash
     *
     * @param string $hash
     * @return EncryptionKey
     */
    protected function getEncryptionKeyModel(string $hash)
    {
        return EncryptionKey::where('hash', $hash)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function createEncryptionKey(string $key, string $hash)
    {
        // Create a new encryption key model
        $keyModel = new EncryptionKey();

        // Add the key to the model
        $keyModel->key = $key;
        $keyModel->hash = $hash;

        return $keyModel->save();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteEncryptionKey($hash)
    {
        // Get the encryption key model from the database
        $key = $this->getEncryptionKeyModel($hash);

        // Delete the key (this will soft-delete the key by default)
        return $key->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function forceDeleteEncryptionKey($hash)
    {
        // Get the encryption key model from the database
        $key = $this->getEncryptionKeyModel($hash);

        // Delete the key (this will soft-delete the key by default)
        return $key->forceDelete();
    }
}
