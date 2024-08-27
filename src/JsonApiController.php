<?php

namespace SnowDigital\LaravelJsonApi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use SnowDigital\LaravelJsonApi\Resources\JsonApiCollection;
use SnowDigital\LaravelJsonApi\Resources\JsonApiResource;

class JsonApiController
{
    protected string $resource;

    public function __construct(Route $route)
    {
        abort_if(! $resourceName = $route->parameter('resource'), 404);
        abort_if(! $resource = JsonApi::resource($resourceName), 500);

        $this->resource = $resource;

        $route->forgetParameter('resource');
    }

    public function index(): JsonApiCollection
    {
        /** @var Model|DefaultQueryBuilder $resource */
        $resource = new $this->resource;

        if ($resource instanceof Model) {
            $paginator = (new DefaultQueryBuilder($resource))
                ->jsonPaginate();
        } else {
            $paginator = $resource->jsonPaginate();
        }

        return new JsonApiCollection($paginator);
    }

    public function show(string $id): JsonApiResource
    {
        /** @var Model|DefaultQueryBuilder $resource */
        $resource = new $this->resource;

        if ($resource instanceof Model) {
            $resource = new DefaultQueryBuilder($resource);
        }

        $entry = $resource
            ->findOrFail($id)
            ->append($resource::$appendsItem);

        return new JsonApiResource($entry);
    }
}
