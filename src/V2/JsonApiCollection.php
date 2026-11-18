<?php

namespace SnowDigital\JsonApi\V2;

use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;

/**
 * A page of resource objects; `included` is deduplicated across the whole page.
 */
class JsonApiCollection extends AnonymousResourceCollection
{
    /**
     * @param  mixed  $resource
     * @param  class-string<JsonApiResource>  $collects
     */
    public function __construct($resource, string $collects = JsonApiResource::class)
    {
        parent::__construct($resource, $collects);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function with($request)
    {
        return PrimaryData::withoutPrimaryInIncluded(parent::with($request), $this->collection->all(), $this->resolveJsonApiRequestFrom($request));
    }
}
