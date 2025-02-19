<?php

namespace Infrastructure\Docs;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use SnowDigital\JsonApi\Facades\JsonApi;
use SnowDigital\JsonApi\QueryBuilder\DefaultQueryBuilder;

/**
 * @codeCoverageIgnore
 */
class QueryBuilderToSchema extends OperationExtension
{
    public static string $referenceNamePrefix = 'App\Model\\';

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if ($operation->method === 'delete'
            || str_ends_with($routeInfo->route->getAction('as'), '.restore')
            || ! (
                str_starts_with($routeInfo->route->getAction('controller'), 'SnowDigital\JsonApi\ApiController')
                || str_ends_with($routeInfo->route->getAction('as'), '.browse')
            )
        ) {
            return;
        }

        if (! $dataType = $operation->responses[0]->getContent('application/json')
            ?->type
        ) {
            $this->defaultParameters($operation);

            return;
        }

        /** @var ArrayType|ObjectType $dataType */
        $dataType = $dataType->getProperty('data') instanceof ArrayType
            ? $dataType->getProperty('data')
            : $dataType;

        if (! $typesIncludes = $this->getTypesAndIncludesFromRoute($routeInfo)) {
            $this->defaultParameters($operation);

            return;
        }

        $components = $this->openApiTransformer->getComponents();
        [
            'attributes' => $attributes,
            'includes' => $includes,
            'appends' => $appends,
            'resource' => $resource,
        ] = $typesIncludes;

        if ($components->hasSchema($this->getReferenceName($resource))) {
            $reference = (new Reference(
                'schemas',
                $this->getReferenceName($resource),
                $components
            ));

            $dataType instanceof ArrayType
                ? $dataType->setItems($reference)
                : $dataType->addProperty('data', $reference);

            if ($operation->method === 'patch' || $operation->method === 'post') {
                $this->addRequestBody($operation, $components->getSchema($this->getReferenceName($resource)), $resource);
            }

            return;
        }

        /** @var Schema $itemsSchema */
        $itemsSchema = clone $components->get($dataType->items);
        /** @var ObjectType $itemsType */
        $itemsType = clone $itemsSchema->type;

        $attributesProperty = clone $itemsType->getProperty('attributes');
        /** @var ObjectType $attributesProperty */
        $attributesProperty->addProperty('id', $itemsType->getProperty('id'));

        $filterType = new ObjectType();
        $fieldsType = new ObjectType();

        $filterType->addProperty('id', new IntegerType());
        $fieldsType->addProperty('id', new IntegerType());

        array_map(
            fn ($type, $name) =>
                $attributesProperty->addProperty($name, $type) &&
                $filterType->addProperty($name, $type) &&
                $fieldsType->addProperty($name, $type),
            $attributes,
            array_keys($attributes)
        );

        if ($appends) {
            array_map(
                fn ($name) => $attributesProperty->addProperty($name, new UnknownType()),
                $appends,
            );
        }

        $sortType = (new ArrayType())->setItems((new StringType())->enum(array_merge(['id'], array_keys($attributes))));

        $includeType = $includes
            ? (new ArrayType())->setItems((new StringType())->enum($includes))
            : null;

        $this->defaultParameters($operation, $filterType, $sortType, $fieldsType, $includeType);

        $itemsType->addProperty('attributes', $attributesProperty);
        $itemsSchema->type = $itemsType;

        $reference = (new Reference(
            'schemas',
            $this->getReferenceName($resource),
            $components
        ));

        $components->add($reference, $itemsSchema);

        $dataType instanceof ArrayType
            ? $dataType->setItems($reference)
            : $dataType->addProperty('data', $reference);
    }

    protected function getReferenceName(object $resource): string
    {
        return Str::of($resource::class)
            ->remove(static::$referenceNamePrefix)
            ->replace('\\', '|');
    }

    protected function getTypesAndIncludesFromRoute(RouteInfo $routeInfo): ?array
    {
        $uri = $routeInfo->route->uri();

        $resourceName = str_ends_with($uri, '{id}')
            ? Str::of($uri)->remove('/{id}')->afterLast('/')
            : Str::of($uri)->afterLast('/');

        if (! $resource = JsonApi::resource($resourceName)) {
            return null;
        }

        $resource = is_array($resource) ? $resource[0] : $resource;
        $resource = (new $resource);

        if ($resource instanceof Model) {
            $resource = new DefaultQueryBuilder(new $resource);
        }

        return [
            'attributes' => Arr::mapWithKeys($resource->getTableColumns(), fn ($type, $name) => [$name => $this->makeType($type)]),
            'includes' => $resource->getIncludes(),
            'appends' => $resource->getModel()->getAppends(),
            'resource' => $resource->resource,
        ];
    }

    protected function makeType(string $type): Type
    {
        return match ($type) {
            'tinyint', 'smallint', 'int', 'bigint' => new IntegerType(),
            'float', 'double', 'decimal', 'timestamp' => new NumberType(),
            'varchar', 'text', 'datetime' => new StringType(),
            'bool', 'boolean' => new BooleanType(),
            'json', 'array' => new ArrayType(),
            default => new UnknownType(),
        };
    }

    protected function defaultParameters(
        Operation $operation,
        Type $filterType = new ObjectType(),
        Type $sortType = new ArrayType(),
        Type $fieldsType = new ObjectType(),
        Type $includeType = null,
    ): void {
        $operation
            ->description('Browse resources using JSON:API. `filter`, `sort`, and `fields` can use any available `attribute`.')
            ->addParameters([
                // @todo: It should be `style`: deepObject, `explode`: true, but it crash
                // @see: https://spec.openapis.org/oas/latest.html#styleValues
                Parameter::make('filter', 'query')
                    ->setSchema(Schema::fromType($filterType))
                    ->description('The filter query parameters can be used to add where clauses to your query. You can specify multiple matching filter values by passing a comma separated list of values.')
                    ->example(['id' => '1,2']),
                // @todo: It should be `explode`: false
                Parameter::make('sort', 'query')
                    ->setSchema(Schema::fromType($sortType))
                    ->description('The sort query parameter is used to determine by which property the results collection will be ordered. Sorting is ascending by default and can be reversed by adding a hyphen (-) to the start of the property name.')
                    ->example(['-id', 'sort_order']),
                // @todo: It should be `style`: deepObject, `explode`: true, but it crash
                Parameter::make('fields', 'query')
                    ->setSchema(Schema::fromType($fieldsType))
                    ->description('Sometimes you\'ll want to fetch only a couple fields to reduce the overall size of your SQL query. This can be done by using the fields request query parameter.')
                    ->example(['products' => 'id,name']),
                // @todo: It should be `style`: deepObject, `explode`: true, but it crash
                Parameter::make('page', 'query')
                    ->setSchema(Schema::fromType((new ObjectType())->addProperty('number', new IntegerType())->addProperty('size', new IntegerType())))
                    ->description('Allow to manage the pagination.')
                    ->example(['number' => 1, 'size' => 20]),
            ]);

        if ($includeType) {
            $operation
                ->addParameters([
                    // @todo: It should be `explode`: false
                    Parameter::make('include', 'query')
                        ->setSchema(Schema::fromType($includeType))
                        ->description('The include query parameter will load any relation or relation count on the resulting models. You can load multiple relationships by separating them with a comma.')
                        ->example(['products']),
                ]);
        }
    }

    protected function addRequestBody(
        Operation $operation,
        Schema $schema,
        Model $resource,
    ): void {
        /** @var ObjectType $attributes */
        $attributes = clone $schema->type->getProperty('attributes');
        $attributes->properties = array_intersect_key($attributes->properties, array_flip($resource->getFillable()));

        $operation
            ->addRequestBodyObject(
                (new RequestBodyObject())
                    ->setContent('application/json', Schema::fromType($attributes))
            );
    }
}
