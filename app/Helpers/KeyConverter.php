<?php

namespace Neocom\JWK\Helpers;

use Jose\Component\Core\JWK;
use Neocom\JWK\Contracts\Helpers\KeyConverter as KeyConverterContract;
use Neocom\JWK\Models\Key;
use Neocom\JWK\Utils\JWKUtils;

class KeyConverter implements KeyConverterContract
{
    /**
     * {@inheritdoc}
     */
    public function convertKeyToJwk(Key $key, bool $publicKey = false)
    {
        // Convert to a JWK
        $jwk = new JWK($key->key_data);

        // If the key doesn't have a thumbprint, add one
        if (! $jwk->has('kid')) {
            $jwk = JWKUtils::addThumbprint($jwk);
        }

        // If we are after a public key or the key is revoked, convert it to a public one
        if ($key->revoked || $publicKey) {
            $jwk = $jwk->toPublic($jwk);
        }

        return $jwk;
    }

    /**
     * {@inheritdoc}
     */
    public function convertJwkToKeyData(JWK $jwk)
    {
        // Get the JSON representation of the JWK
        $jwkData = $jwk->all();

        return $jwkData;
    }
}
