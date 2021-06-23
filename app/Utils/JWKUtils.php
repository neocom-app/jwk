<?php

namespace Neocom\JWK\Utils;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;

class JWKUtils
{
    protected static string $thumbprintHashFuncName = 'sha256';

    /**
     * Add a thumbprint to the JWK if one doesn't already exist
     *
     * @param JWK $jwk  The JWK to add the thumbprint to
     * @param string $hashFunc  Hash function to use when generating the thumbprint
     * @return JWK
     */
    public static function addThumbprint(JWK $jwk, string $hashFunc = '')
    {
        // Check if the JWK already has a thumbprint
        if ($jwk->has('kid')) {
            return $jwk;
        }

        // Get the hash function
        $hashFunc = $hashFunc ?: static::$thumbprintHashFuncName;

        // Get the existing key data
        $keyData = $jwk->all();

        // Generate the thumbprint
        $thumbprint = $jwk->thumbprint($hashFunc);

        // Merge the data together
        $keyData = array_merge(['kid' => $thumbprint], $keyData);

        // Return with a new JWK containing the thumbprint
        return new JWK($keyData);
    }

    /**
     * Create a JWKSet from a single or collection or JWKs
     *
     * @param JWKSet|Collection|array|JWK[]|JWK  $jwks
     * @return JWKSet
     */
    public static function createJWKSet($jwks, bool $fromJson = false)
    {
        // If the passed argument is already a JWKSet, just return it
        if ($jwks instanceof JWKSet) {
            return $jwks;
        }

        // If the passed list is a Collection, convert it to an array
        if ($jwks instanceof Collection) {
            $jwks = $jwks->toArray();
        }

        // Convert a single key to an array of keys
        $jwks = Arr::wrap($jwks);

        // Attempt to create the key set from json data
        if ($fromJson) {
            return JWKSet::createFromKeyData($jwks);
        }

        return new JWKSet($jwks);
    }
}
