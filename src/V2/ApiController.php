<?php

namespace SnowDigital\JsonApi\V2;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SnowDigital\JsonApi\Events\AfterIndex;
use SnowDigital\JsonApi\Facades\JsonApi;
use SnowDigital\JsonApi\QueryBuilder\DefaultQueryBuilder;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\QueryBuilderRequest;

/**
 * Browse and read registered resources as JSON:API v2 documents.
 *
 * Resolves resources exactly like the v1 controller (`JsonApi::resource()`, `[class, only]`).
 */
class ApiController
{
    /** @var class-string<JsonApiResource> */
    protected string $resourceClass = JsonApiResource::class;

    /** @var class-string<JsonApiCollection> */
    protected string $collectionClass = JsonApiCollection::class;

    protected string $resource;

    protected string $resourceName;

    protected ?array $only;

    public function index(Request $request): JsonApiCollection
    {
        $this->resolveResource($request, 'browse');

        $builder = $this->makeQueryBuilder();
        $paginator = $builder->jsonPaginate();
        $this->restrictIncludes($request, $builder);

        if ($append = $request->query('append')) {
            $paginator->append(explode(',', $append));
        }

        $collection = new $this->collectionClass($paginator, $this->resourceClass);

        event(new AfterIndex($this->resourceName, $collection));

        return $collection;
    }

    public function show(Request $request): JsonApiResource
    {
        $this->resolveResource($request, 'show');

        $builder = $this->makeQueryBuilder();
        /** @var Model $entry */
        $entry = $builder->findOrFail($request->route('id'));
        $this->restrictIncludes($request, $builder);

        if ($append = $request->query('append')) {
            $entry->append(explode(',', $append));
        }

        return new $this->resourceClass($entry);
    }

    /**
     * Resolved per action, not in the constructor: a route keeps its controller instance,
     * so one controller can serve several resources in a long-running process.
     */
    protected function resolveResource(Request $request, string $action): void
    {
        abort_if(! $resourceName = $request->route('resource'), 404);
        abort_if(! $resource = JsonApi::resource($resourceName), 500);

        $this->resourceName = $resourceName;
        $this->resource = is_array($resource) ? $resource[0] : $resource;
        $this->only = is_array($resource) ? $resource[1] : null;

        abort_if($this->only && ! in_array($action, $this->only), 404);
    }

    protected function makeQueryBuilder(): QueryBuilder
    {
        $builder = $this->withoutSparseFieldsets(function () {
            $resource = new $this->resource();

            return $resource instanceof Model ? new DefaultQueryBuilder($resource) : $resource;
        });

        if ($builder instanceof DefaultQueryBuilder) {
            Schema::remember($builder->getModel(), $this->resourceName, $builder->getIncludes());
        }

        return $builder;
    }

    /**
     * Spatie eager loads only the allowed include paths and ignores the others (when invalid
     * includes don't throw), but the serializer reads `include` itself and would lazy load
     * the rest row by row. Keep only the paths Spatie loaded.
     */
    protected function restrictIncludes(Request $request, QueryBuilder $builder): void
    {
        $parameter = config('query-builder.parameters.include', 'include');

        if (! $request->has($parameter) || ! $builder instanceof DefaultQueryBuilder) {
            return;
        }

        $allowed = [];

        foreach ($builder->getIncludes() as $include) {
            if (is_string($include)) {
                foreach (explode('.', $include) as $i => $segment) {
                    $allowed[] = implode('.', array_slice(explode('.', $include), 0, $i + 1));
                }
            }
        }

        $requested = array_filter(explode(',', (string) $request->input($parameter)));

        $request->query->set($parameter, implode(',', array_intersect($requested, $allowed)));
    }

    /**
     * Spatie would turn `fields[type]` into a SQL select per table: without the primary and
     * foreign keys, eager loads and relationship identifiers break, and its validation rejects
     * fields of related types. In v2 only the serializer applies sparse fieldsets, per type.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function withoutSparseFieldsets(Closure $callback): mixed
    {
        $container = Container::getInstance();
        $previous = $container->getBindings()[QueryBuilderRequest::class] ?? null;

        $container->bind(QueryBuilderRequest::class, function ($app) {
            $request = QueryBuilderRequest::fromRequest($app['request']);
            $parameter = config('query-builder.parameters.fields', 'fields');

            $request->query->remove($parameter);
            $request->request->remove($parameter);
            $request->json()->remove($parameter);

            return $request;
        });

        try {
            return $callback();
        } finally {
            $previous
                ? $container->bind(QueryBuilderRequest::class, $previous['concrete'], $previous['shared'])
                : $container->offsetUnset(QueryBuilderRequest::class);
        }
    }
}
