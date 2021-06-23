<?php

namespace Neocom\JWK\Utils;

use Illuminate\Support\Str;

class KeyUtils
{
   /**
     * Get the list of parameters required to generate this key type
     *
     * @param string $keyType  Key Type
     * @param bool $includeExtraOptions  Include extra valid key parameters
     * @return array
     */
    public static function getDefaultParameters(string $keyType, bool $includeExtraOptions = false)
    {
        // Loop through each valid key type
        switch (Str::upper($keyType)) {

            // RSA
            case 'RSA':
                $options = ['bit_size' => 4096];
                break;

            // EC
            case 'EC':
                $options = ['curve' => 'P-256'];
                break;

            // OKP
            case 'OKP':
                $options = ['curve' => 'Ed25519'];
                break;

            // OCT
            case 'OCT':
                $options = ['bit_size' => 256, 'secret' => ''];
                break;
        }

        // Merge the key type into the list
        $options = array_merge(['key_type' => $keyType], $options);

        // Add the extra options if required
        if ($includeExtraOptions) {
            $extraOptions = array_fill_keys(static::getExtraParameters(), '');
            $options = array_merge($options, $extraOptions);
        }

        return $options;
    }

    /**
     * Get the list of extra parameters that can be embedded into a JWK
     *
     * @return array
     */
    public static function getExtraParameters()
    {
        // List of valid optional parameters that can be added to a key (Default options are 'alg' and 'use')
        return ['alg', 'use'];
    }
}
