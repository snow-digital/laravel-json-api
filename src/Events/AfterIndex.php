<?php

namespace SnowDigital\JsonApi\Events;

use SnowDigital\JsonApi\Resources\JsonApiCollection;

class AfterIndex
{
    public function __construct(
        public string $resource,
        public JsonApiCollection $collection,
    ) {}
}
