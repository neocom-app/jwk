<?php

namespace Neocom\JWK\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Neocom\JWK\Traits\UsesUuids;

class Model extends BaseModel
{
    use HasFactory, UsesUuids;
}
