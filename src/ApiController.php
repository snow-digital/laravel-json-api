<?php

namespace SnowDigital\JsonApi;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Route;
use SnowDigital\JsonApi\Events\AfterIndex;
use SnowDigital\JsonApi\Facades\JsonApi;
use SnowDigital\JsonApi\QueryBuilder\DefaultQueryBuilder;
use SnowDigital\JsonApi\Resources\CompoundDocument;
use SnowDigital\JsonApi\Resources\JsonApiCollection;
use SnowDigital\JsonApi\Resources\JsonApiResource;
use SnowDigital\JsonApi\Resources\ResourceObject;
use SnowDigital\JsonApi\Resources\ResourceRelationships;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\QueryBuilderRequest;

class ApiController
{
    /**
     * The resource a model is serialized with. A `ResourceObject` (with a `CompoundDocument`)
     * serves spec-shaped documents; see `usesResourceObjects()`. Scramble documents `index` and
     * `show` from their phpdoc `@return`, so a subclass serving other classes narrows those.
     *
     * @var class-string<JsonResource>
     */
    protected string $resourceClass = JsonApiResource::class;

    /** @var class-string<ResourceCollection> */
    protected string $collectionClass = JsonApiCollection::class;

    protected string $resource;

    protected string $resourceName;

    protected ?array $only;

    public function __construct(Route $route)
    {
        $this->resolveResource($route);
    }

    /**
     * A route keeps its controller instance, so the constructor only runs for its first request:
     * resolve the resource again whenever the route still carries it.
     */
    public function callAction(string $method, array $parameters): mixed
    {
        if (array_key_exists('resource', $parameters)) {
            $this->resolveResource(request()->route());
            unset($parameters['resource']);
        }

        return $this->{$method}(...array_values($parameters));
    }

    /**
     * @return LengthAwarePaginator<JsonApiResource>
     *
     * @phpstan-return ResourceCollection
     */
    public function index(): ResourceCollection
    {
        abort_if($this->only && ! in_array('browse', $this->only), 404);

        $builder = $this->newQueryBuilder();
        $paginator = $builder->jsonPaginate();

        $this->afterQuery($builder);

        if ($append = request('append')) {
            $paginator->append(explode(',', $append));
        }

        $collection = $this->newCollection($paginator);

        event(new AfterIndex($this->resourceName, $collection));

        return $collection;
    }

    /**
     * @return JsonApiResource
     *
     * @phpstan-return JsonResource
     */
    public function show(string $id): JsonResource
    {
        abort_if($this->only && ! in_array('show', $this->only), 404);

        $builder = $this->newQueryBuilder();
        /** @var Model $entry */
        $entry = $builder->findOrFail($id);

        $this->afterQuery($builder);

        if ($append = request('append')) {
            $entry->append(explode(',', $append));
        }

        return $this->newResource($entry);
    }

    public function post(Request $request): JsonResource
    {
        abort_if($this->only && ! in_array('post', $this->only), 404);

        return $this->upsert($request);
    }

    public function patch(Request $request, string $id): JsonResource
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

    protected function upsert(Request $request, ?string $id = null): JsonResource
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

        return $this->newResource($entry);
    }

    protected function resolveResource(Route $route): void
    {
        abort_if(! $resourceName = $route->parameter('resource'), 404);
        abort_if(! $resource = JsonApi::resource($resourceName), 500);

        $this->resourceName = $resourceName;
        $this->resource = is_array($resource) ? $resource[0] : $resource;
        $this->only = is_array($resource) ? $resource[1] : null;

        $route->forgetParameter('resource');
    }

    /**
     * For custom actions. The built-in ones keep the check inline, where Scramble documents the 404.
     */
    protected function ensureAction(string $action): void
    {
        abort_if($this->only && ! in_array($action, $this->only), 404);
    }

    /**
     * The one switch between the two output formats. Resource objects need what attributes-only
     * resources do not: sparse fieldsets left to the serializer, the served type remembered,
     * and includes limited to what the query builder loaded.
     */
    protected function usesResourceObjects(): bool
    {
        return is_a($this->resourceClass, ResourceObject::class, true);
    }

    protected function newQueryBuilder(): QueryBuilder
    {
        if (! $this->usesResourceObjects()) {
            return $this->instantiateQueryBuilder();
        }

        $builder = $this->withoutSparseFieldsets(fn () => $this->instantiateQueryBuilder());

        if ($builder instanceof DefaultQueryBuilder) {
            JsonApi::rememberType($builder->getModel()::class, $this->resourceName);
            ResourceRelationships::remember($builder->getModel(), $this->resourceName, $builder->getIncludes());
        }

        return $builder;
    }

    protected function instantiateQueryBuilder(): QueryBuilder
    {
        /** @var Model|QueryBuilder $resource */
        $resource = new $this->resource();

        return $resource instanceof Model ? $this->makeQueryBuilder($resource) : $resource;
    }

    protected function makeQueryBuilder(Model $resource): DefaultQueryBuilder
    {
        return new DefaultQueryBuilder($resource);
    }

    protected function afterQuery(QueryBuilder $builder): void
    {
        if ($this->usesResourceObjects()) {
            $this->restrictIncludes(request(), $builder);
        }
    }

    protected function newResource(Model $entry): JsonResource
    {
        return new $this->resourceClass($entry);
    }

    protected function newCollection(AbstractPaginator $paginator): ResourceCollection
    {
        $collection = $this->collectionClass;
        $resource = $this->resourceClass;

        return is_a($collection, CompoundDocument::class, true) && is_a($resource, ResourceObject::class, true)
            ? new $collection($paginator, $resource)
            : new $collection($paginator);
    }

    /**
     * Spatie eager loads only the allowed include paths and ignores the others (when invalid
     * includes don't throw), but a resource object reads `include` itself and would lazy load
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
     * fields of related types. For resource objects only the serializer applies sparse fieldsets.
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
