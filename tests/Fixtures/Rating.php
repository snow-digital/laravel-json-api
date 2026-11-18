<?php

namespace SnowDigital\JsonApi\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Rating extends Model
{
    protected $guarded = [];

    public function rateable(): MorphTo
    {
        return $this->morphTo();
    }
}
