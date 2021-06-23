<?php

namespace Neocom\JWK\Traits;

use GoldSpecDigital\LaravelEloquentUUID\Database\Eloquent\Uuid;

trait UsesUuids
{
    use Uuid;

    /**
     * Get the value indicating whether the IDs are incrementing.
     *
     * @return bool
     */
    public function getIncrementing()
    {
        return false;
    }
    /**
     * Get the auto-incrementing key type.
     *
     * @return string
     */
    public function getKeyType()
    {
        return 'string';
    }
}
