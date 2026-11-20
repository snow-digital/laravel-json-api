<?php

namespace SnowDigital\JsonApi\Tests\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use SnowDigital\JsonApi\ApiController;
use SnowDigital\JsonApi\Resources\CompoundDocument;
use SnowDigital\JsonApi\Resources\ResourceObject;

class ResourceObjectController extends ApiController
{
    protected string $resourceClass = ResourceObject::class;

    protected string $collectionClass = CompoundDocument::class;

    /**
     * @return CompoundDocument
     *
     * @phpstan-return ResourceCollection
     */
    public function index(): ResourceCollection
    {
        return parent::index();
    }

    /**
     * @return ResourceObject
     *
     * @phpstan-return JsonResource
     */
    public function show(string $id): JsonResource
    {
        return parent::show($id);
    }
}
