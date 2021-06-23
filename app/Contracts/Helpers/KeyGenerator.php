<?php

namespace Neocom\JWK\Contracts\Helpers;

use Jose\Component\Core\JWK;

interface KeyGenerator
{
    /**
     * Generate a JWK with a set of valid options
     *
     * @param array $options  List of key parameters
     * @return JWK
     */
    public function generateKey(array $options);

    /**
     * Extract key parameters out of a JWK
     *
     * @param JWK $jwk  Key to extract parameters from
     * @return array
     */
    public function getKeyParams(JWK $jwk);
}
