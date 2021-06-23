<?php

namespace Neocom\JWK\Contracts\Helpers;

use Jose\Component\Core\JWKSet;

interface KeyEncryptor
{
    /**
     * Whether key encryption is enabled
     */
    public function isEncryptionEnabled();

    /**
     *
     */
    // public function shouldEncryptKey();

    /**
     * Encrypt a JWK or JWKSet using the provided encryption key
     *
     * @param JWKSet|JWK $keyset  Key set to encrypt
     * @param string $encryptionKey  Encryption key to encrypt the token with
     * @return string
     */
    public function encryptKeySet($keys, $encryptionKey);
}
