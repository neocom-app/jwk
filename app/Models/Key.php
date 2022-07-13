<?php

namespace Neocom\JWK\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Neocom\JWK\Casts\Base64Json;
use Neocom\JWK\Scopes\RevokedKeyScope;

class Key extends Model
{
    use SoftDeletes;

    protected $casts = [
        'key_data' => Base64Json::class,
        'revoked_at' => 'datetime',
    ];

    /**
     * Get the tags for this key.
     */
    public function tags()
    {
        return $this->hasMany(Tag::class);
    }

    /**
     * Boot the revoked key scope for this model
     */
    protected static function booted()
    {
        static::addGlobalScope(new RevokedKeyScope);
    }

    /**
     * Revoke the key
     *
     * @return bool
     */
    public function revoke()
    {
        $this->{$this->getRevokedAtColumn()} = $this->freshTimestampString();

        return $this->save();
    }

    public function canBeCleanedUp($days)
    {
        if (is_null($this->revoked_at)) {
            return false;
        }

        return $this->revoked_at->diffInDays() > $days;
    }

    /**
     * Determine if the key has been revoked.
     *
     * @return bool
     */
    public function getRevokedAttribute()
    {
        return $this->revoked();
    }

    /**
     * Determine if the key has been revoked.
     *
     * @return bool
     */
    public function revoked()
    {
        return ! is_null($this->{$this->getRevokedAtColumn()});
    }

    /**
     * Get the name of the "revoked at" column.
     *
     * @return string
     */
    public function getRevokedAtColumn()
    {
        return 'revoked_at';
    }

    /**
     * Get the fully qualified "revoked at" column.
     *
     * @return string
     */
    public function getQualifiedRevokedAtColumn()
    {
        return $this->qualifyColumn($this->getRevokedAtColumn());
    }
}
