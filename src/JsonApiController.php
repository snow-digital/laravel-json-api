<?php

namespace SnowDigital\JsonApi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use SnowDigital\JsonApi\QueryBuilder\DefaultQueryBuilder;
use SnowDigital\JsonApi\Resources\JsonApiCollection;
use SnowDigital\JsonApi\Resources\JsonApiResource;

class JsonApiController
{
    protected string $resource;

    protected ?array $only;

    public function __construct(Route $route)
    {
        abort_if(! $resourceName = $route->parameter('resource'), 404);
        abort_if(! $resource = JsonApi::resource($resourceName), 500);

        $this->resource = is_array($resource) ? $resource[0] : $resource;
        $this->only = is_array($resource) ? $resource[1] : null;

        $route->forgetParameter('resource');
    }

    public function index(): JsonApiCollection
    {
        abort_if($this->only && ! in_array('browse', $this->only), 404);

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
        abort_if($this->only && ! in_array('show', $this->only), 404);

        /** @var Model|DefaultQueryBuilder $resource */
        $resource = new $this->resource;

        if ($resource instanceof Model) {
            $resource = new DefaultQueryBuilder($resource);
        }

        $entry = $resource
            ->findOrFail($id);

        return new JsonApiResource($entry);
    }
}
