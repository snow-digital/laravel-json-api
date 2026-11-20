<?php

namespace SnowDigital\JsonApi\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Video extends Model
{
    protected $guarded = [];

    /** The owning post is always eager loaded. */
    protected $with = ['post'];

    public function post(): MorphOne
    {
        return $this->morphOne(Post::class, 'content');
    }
}
