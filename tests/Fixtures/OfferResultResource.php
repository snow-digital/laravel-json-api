<?php

namespace SnowDigital\JsonApi\Tests\Fixtures;

use Illuminate\Http\Request;
use SnowDigital\JsonApi\V2\JsonApiResource;

class OfferResultResource extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            ...parent::toAttributes($request),
            'computed' => "{$this->resource->name}!",
        ];
    }
}
