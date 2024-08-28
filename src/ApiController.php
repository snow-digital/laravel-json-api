<?php

namespace SnowDigital\JsonApi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use SnowDigital\JsonApi\Facades\JsonApi;
use SnowDigital\JsonApi\QueryBuilder\DefaultQueryBuilder;
use SnowDigital\JsonApi\Resources\JsonApiCollection;
use SnowDigital\JsonApi\Resources\JsonApiResource;
use Spatie\QueryBuilder\QueryBuilder;

class ApiController
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

        /** @var Model|QueryBuilder $resource */
        $resource = new $this->resource();

        if ($resource instanceof Model) {
            $paginator = (new DefaultQueryBuilder($resource))
                ->jsonPaginate();
        } else {
            $paginator = $resource->jsonPaginate();
        }

        if ($append = request('append')) {
            $paginator->append(explode(',', $append));
        }

        return new JsonApiCollection($paginator);
    }

    public function show(string $id): JsonApiResource
    {
        abort_if($this->only && ! in_array('show', $this->only), 404);

        /** @var Model|QueryBuilder $resource */
        $resource = new $this->resource();

        if ($resource instanceof Model) {
            $resource = new DefaultQueryBuilder($resource);
        }

        $entry = $resource
            ->findOrFail($id);

        if ($append = request('append')) {
            $entry->append(explode(',', $append));
        }

        return (new JsonApiResource($entry))
            ->additional(['data' => ['bbb' => 'aaa']]);
    }

    public function post(Request $request): JsonApiResource
    {
        abort_if($this->only && ! in_array('post', $this->only), 404);

        return $this->upsert($request);
    }

    public function patch(Request $request, string $id): JsonApiResource
    {
        abort_if($this->only && ! in_array('patch', $this->only), 404);

        return $this->upsert($request, $id);
    }

    public function delete(string $id): Response
    {
        abort_if($this->only && ! in_array('delete', $this->only), 404);

        /** @var Model|QueryBuilder $resource */
        $resource = new $this->resource();

        if ($resource instanceof QueryBuilder) {
            $resource = $resource->getSubject()->getModel();
        }

        $entry = $resource->findOrFail($id);

        if (! $entry->delete()) {
            abort(400, 'This entry cannot be deleted');
        }

        return response(null, 204);
    }

    public function restore(string $id): Response
    {
        abort_if($this->only && ! in_array('restore', $this->only), 404);

        /** @var Model|QueryBuilder $resource */
        $resource = new $this->resource();

        if ($resource instanceof QueryBuilder) {
            $resource = $resource->getSubject()->getModel();
        }

        if (! method_exists($resource, 'restore')) {
            abort(500);
        }

        $entry = $resource->withTrashed()->findOrFail($id);

        if (! $entry->restore()) {
            abort(400, 'This entry cannot be restore');
        }

        return response(null, 204);
    }

    protected function upsert(Request $request, ?string $id = null): JsonApiResource
    {
        /** @var Model|DefaultQueryBuilder $resource */
        $resource = new $this->resource();

        if ($resource instanceof QueryBuilder) {
            $resource = $resource->getSubject()->getModel();
        }

        /** @var Model $entry */
        $entry = $id
            ? $resource->findOrFail($id)
            : new $resource;

        $entry
            ->fill($request->all())
            ->save();

        return new JsonApiResource($entry);
    }
}
