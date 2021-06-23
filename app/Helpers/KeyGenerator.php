<?php

namespace Neocom\JWK\Helpers;

use Base64Url\Base64Url;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Neocom\JWK\Contracts\Helpers\KeyGenerator as KeyGeneratorContract;
use Neocom\JWK\Utils\JWKUtils;
use Neocom\JWK\Utils\KeyUtils;
use RuntimeException;

class KeyGenerator implements KeyGeneratorContract
{
    /**
     * {@inheritdoc}
     */
    public function generateKey(array $options)
    {
        // Pull out the key type
        $type = Arr::get($options, 'key_type', 'rsa');

        // Array holding the options to pass to the relevant key generator
        $keyOptions = [];

        // Function to call
        $methodName = sprintf('create%sKey', Str::upper($type));

        // Pull out some extra options that can be added to keys
        $extraOptions = Arr::only($options, KeyUtils::getExtraParameters());
        $extraOptions = Arr::where($extraOptions, function ($value) {
            return !empty($value);
        });
        $keyOptions[] = $extraOptions;

        // Go through each valid key type
        switch (Str::upper($type)) {

            // RSA Key
            case 'RSA':

                // Pull out the bit size from the options. Default is a 4k bit size
                $bitSize = Arr::get($options, 'bit_size', 4096);

                // Add the bit size to the key options
                $keyOptions = Arr::prepend($keyOptions, $bitSize);
                break;

            // EC and OKP keys
            case 'EC':
            case 'OKP':

                // Default curve for the relevant type
                $defaultCurve = ['ec' => 'P-256', 'okp' => 'Ed25519'][$type];

                // Pull out the curve from the options
                $curve = Arr::get($options, 'curve', $defaultCurve);

                // Add the curve to the key options
                $keyOptions = Arr::prepend($keyOptions, $curve);
                break;

            // Oct keys
            case 'OCT':

                // Oct keys need a custom method name
                $methodName = 'createOctKey';

                // Does the key have a pre-defined secret to use
                if (Arr::has($options, 'secret') && Arr::get($options, 'secret', '') !== '') {

                    // Set the relevant method
                    $methodName = 'createFromSecret';

                    // Pull out the secret and add it to the key options
                    $secret     = Arr::get($options, 'secret');
                    $keyOptions = Arr::prepend($keyOptions, $secret);

                // Otherwise this is a standard key generation
                } else {

                    // Pull out the bit size from the options
                    $bitSize = Arr::get($options, 'bit_size', 64);

                    // Add the bit size to the key options
                    $keyOptions = Arr::prepend($keyOptions, $bitSize);
                }

                break;
        }

        // Generate the key
        /** @var JWK $key */
        $key = JWKFactory::{$methodName}(...$keyOptions);

        // Add the thumbprint for this key
        $key = JWKUtils::addThumbprint($key);

        return $key;
    }

    /**
     * {@inheritdoc}
     */
    public function getKeyParams(JWK $jwk)
    {
        // Pull out the key type from the jwk
        $keyType = Str::upper($jwk->get('kty'));

        // Array holding the parameters for the key
        $keyParams = ['key_type' => $keyType];

        // Determine the type of key
        switch ($keyType) {

            // RSA Keys
            case 'RSA':

                // Determine the bit size of the key
                $modulus = Base64Url::decode($jwk->get('n'));
                $keySize = Str::length($modulus, '8bit') * 8;
                $keyParams['bit_size'] = $keySize;

                break;

            // EC and OKP Keys
            case 'EC':
            case 'OKP':

                // Get the curve of the current key
                $keyCurve = $jwk->get('crv');
                $keyParams['curve'] = $keyCurve;

                break;

            // Oct Keys. Unsupported
            case 'OCT':
                throw new RuntimeException("Can't get key parameters from an OCT key");

        }

        // Extract any extra parameters from the key such as 'alg' and 'use'
        $extraKeyParams = collect(KeyUtils::getExtraParameters())->mapWithKeys(function ($item) use ($jwk) {
            if (! $jwk->has($item)) {
                return null;
            }

            return [$item => $jwk->get($item)];
        })->toArray();
        $keyParams = array_merge($keyParams, $extraKeyParams);

        return $keyParams;
    }
}
