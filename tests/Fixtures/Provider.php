<?php

namespace SnowDigital\JsonApi\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Provider extends Model
{
    protected $guarded = [];

    protected $casts = ['data' => 'array'];

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function ratings(): MorphMany
    {
        return $this->morphMany(Rating::class, 'rateable');
    }
}
