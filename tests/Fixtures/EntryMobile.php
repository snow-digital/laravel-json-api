<?php

namespace SnowDigital\JsonApi\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class EntryMobile extends Model
{
    protected $guarded = [];

    /** Like telecom's entries: the owning offer is always eager loaded. */
    protected $with = ['parent'];

    public function parent(): MorphOne
    {
        return $this->morphOne(Offer::class, 'entry');
    }
}
