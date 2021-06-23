<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JWK Private Key Encryption Options
    |--------------------------------------------------------------------------
    |
    | Here you may specify the configuration options that apply to the key
    | encryption system.
    |
    */

    'encryption' => [

        'enabled' => env('JWK_ENCRYPTION_ENABLED', false),

        'algortihms' => [

            'key_algorithm'     => env('JWK_ENCRYPTION_KEY_ALGORITHM', 'PBES2-HS256+A128KW'),
            'content_algorithm' => env('JWK_ENCRYPTION_CONTENT_ALGORITHM', 'A128GCM'),
        ],

        'enable_payload_compression' => env('JWK_ENCRYPTION_ENABLE_PAYLOAD_COMPRESSION', false),

    ],

];
