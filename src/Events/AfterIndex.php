<?php

namespace SnowDigital\JsonApi\Events;

use Illuminate\Http\Resources\Json\ResourceCollection;

class AfterIndex
{
    public function __construct(
        public string $resource,
        public ResourceCollection $collection,
    ) {}
}
