<?php

namespace SnowDigital\JsonApi\Resources;

use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use SnowDigital\JsonApi\Resources\Concerns\KeepsPrimaryDataOutOfIncluded;

/**
 * A page of resource objects; `included` is deduplicated across the whole page.
 */
class CompoundDocument extends AnonymousResourceCollection
{
    use KeepsPrimaryDataOutOfIncluded;

    /**
     * @param  mixed  $resource
     * @param  class-string<ResourceObject>  $collects
     */
    public function __construct($resource, string $collects = ResourceObject::class)
    {
        parent::__construct($resource, $collects);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function with($request)
    {
        return $this->withoutPrimaryData(parent::with($request), $this->collection->all(), $this->resolveJsonApiRequestFrom($request));
    }
}
