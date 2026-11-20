<?php

namespace SnowDigital\JsonApi\Tests\Fixtures;

use SnowDigital\JsonApi\ApiController;
use SnowDigital\JsonApi\Resources\CompoundDocument;
use SnowDigital\JsonApi\Resources\ResourceObject;

class ResourceObjectController extends ApiController
{
    protected string $resourceClass = ResourceObject::class;

    protected string $collectionClass = CompoundDocument::class;
}
