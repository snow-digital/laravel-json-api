<?php

namespace SnowDigital\JsonApi\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Model $resource
 */
class JsonApiResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            /** @var string $id The resource id. */
            'id' => (string) $this->resource->getKey(),
            /** @var object $attributes The resource attributes. */
            'attributes' => $this->resource->toArray(),
        ];
    }

    protected static function newCollection($resource): JsonApiCollection
    {
        return new JsonApiCollection($resource);
    }
}
