<?php

namespace SnowDigital\JsonApi\Tests\Fixtures;

use Illuminate\Http\Request;
use SnowDigital\JsonApi\Resources\ResourceObject;

class OfferResultResource extends ResourceObject
{
    public function toAttributes(Request $request): array
    {
        return [
            ...parent::toAttributes($request),
            'computed' => "{$this->resource->name}!",
        ];
    }
}
