<?php

namespace Neocom\JWK\Helpers;

use Illuminate\Support\Str;
use Neocom\JWK\Contracts\Helpers\EncryptionKeyGenerator as EncryptionKeyGeneratorContract;
use RuntimeException;

class EncryptionKeyGenerator implements EncryptionKeyGeneratorContract
{
    /**
     * Length of the generated encryption key
     */
    protected $keySize = 64;

    /**
     * {@inheritdoc}
     */
    public function generateEncryptionKey(string $secret)
    {
        // If there is no secret provided, throw an error
        if (! $secret) {
            throw new RuntimeException('A secret must be provided to generate the encryption key');
        }

        // Create the encryption key
        $encryptionKey = Str::random($this->keySize);

        // Generate the has based off the secret and encryption key
        $encryptionHash = hash_hmac('sha256', $encryptionKey, $secret);

        // Return the key and hash
        return [
            'key'  => $encryptionKey,
            'hash' => $encryptionHash,
        ];
    }
}
