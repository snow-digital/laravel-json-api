<?php

namespace SnowDigital\JsonApi\Resources;

use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use SnowDigital\JsonApi\Resources\Concerns\KeepsPrimaryDataOutOfIncluded;

class CompoundDocument extends AnonymousResourceCollection
{
    use KeepsPrimaryDataOutOfIncluded;

    /**
     * The constructor sets the real one; this default is what Scramble documents.
     *
     * @var class-string<ResourceObject>
     */
    public $collects = ResourceObject::class;

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
