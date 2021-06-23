<?php

namespace Neocom\JWK\Models;

class Tag extends Model
{
    protected $fillable = [
        'tag_name',
        'tag_value',
    ];

    public function key()
    {
        return $this->belongsTo(Key::class);
    }
}
