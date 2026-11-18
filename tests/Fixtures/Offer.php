<?php

namespace SnowDigital\JsonApi\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;

class Offer extends Model
{
    protected $guarded = [];

    protected $hidden = ['secret'];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * Same shape as telecom's Offer::entry(): a belongsTo once the type is known.
     */
    public function entry(): BelongsTo|MorphTo
    {
        return $this->entry_type
            ? $this->belongsTo(Relation::getMorphedModel($this->entry_type) ?? $this->entry_type, 'entry_id')
            : $this->morphTo();
    }

    public function duplicating(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'duplicating_id');
    }

    public function getLabelAttribute(): string
    {
        return strtoupper($this->name);
    }
}
