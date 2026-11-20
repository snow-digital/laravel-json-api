<?php

namespace SnowDigital\JsonApi\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Comment extends Model
{
    protected $guarded = [];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}
