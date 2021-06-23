<?php

namespace Neocom\JWK\Contracts\Helpers;

use Jose\Component\Core\JWK;
use Neocom\JWK\Models\Key;

interface KeyConverter
{
    /**
     * Convert a Key model into a JWK
     *
     * @param Key $key  Key to convert
     * @param bool $publicKey  Force convert Key into a public JWK
     * @return JWK
     */
    public function convertKeyToJwk(Key $key, bool $publicKey = false);

    /**
     * Convert a JWK into key data
     *
     * @param JWK $jwk  JWK to convert
     * @return array
     */
    public function convertJwkToKeyData(JWK $jwk);
}
