<?php

namespace Neocom\JWK\Contracts\Helpers;

interface EncryptionKeyGenerator
{
    /**
     * Generate an encryption key and hash using a provided secret
     *
     * @param array $options  Secret used for createing the encryption hash
     * @return array
     */
    public function generateEncryptionKey(string $secret);
}
