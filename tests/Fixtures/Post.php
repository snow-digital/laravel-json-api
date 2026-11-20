<?php

namespace SnowDigital\JsonApi\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;

class Post extends Model
{
    protected $guarded = [];

    protected $hidden = ['secret'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    /**
     * A belongsTo once the type is known, a morphTo otherwise.
     */
    public function content(): BelongsTo|MorphTo
    {
        return $this->content_type
            ? $this->belongsTo(Relation::getMorphedModel($this->content_type) ?? $this->content_type, 'content_id')
            : $this->morphTo();
    }

    public function original(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'original_id');
    }

    public function getLabelAttribute(): string
    {
        return strtoupper($this->name);
    }
}
