<?php

namespace SnowDigital\JsonApi\QueryBuilder;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\Enums\FilterOperator;
use Spatie\QueryBuilder\QueryBuilder;

class DefaultQueryBuilder extends QueryBuilder
{
    protected static array $tableColumns = [];

    protected static array $relationshipIncludes = [];

    public function __construct(public Model $resource)
    {
        parent::__construct($resource->newQuery());

        $this
            ->allowedFields($this->getFields())
            ->allowedSorts($this->getSorts())
            ->allowedFilters($this->getFilters())
            ->allowedIncludes($this->getIncludes());
    }

    public function getFields(): array
    {
        return array_keys(static::getTableColumns());
    }

    public function getSorts(): array
    {
        return array_keys(static::getTableColumns());
    }

    public function getFilters(): array
    {
        $filters = [];

        foreach (static::getTableColumns() as $column => $type) {
            $filters[$column] = match ($type) {
                'tinyint', 'smallint', 'int', 'bigint' => AllowedFilter::exact($column),
                'double' => AllowedFilter::operator($column, FilterOperator::DYNAMIC),
                default => AllowedFilter::partial($column),
            };
        }

        return $filters;
    }

    public function getIncludes(): array
    {
        return $this->discoverRelationships();
    }

    protected function discoverRelationships(): array
    {
        $modelClass = get_class($this->resource);

        if (isset(static::$relationshipIncludes[$modelClass])) {
            return static::$relationshipIncludes[$modelClass];
        }

        $includes = [];
        $reflection = new \ReflectionClass($this->resource);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $returnType = $method->getReturnType();

            if (! $returnType instanceof \ReflectionNamedType || $returnType->isBuiltin()) {
                continue;
            }

            $typeName = $returnType->getName();

            if (! is_subclass_of($typeName, Relation::class) || is_a($typeName, MorphTo::class, true)) {
                continue;
            }

            $includes[] = $method->getName();
        }

        return static::$relationshipIncludes[$modelClass] = $includes;
    }

    public function getTableColumns(): array
    {
        $model = $this->resource;

        if (isset(static::$tableColumns[$model::class])) {
            return static::$tableColumns[$model::class];
        }

        if ($model->getVisible()) {
            return static::$tableColumns[$model::class] = $model->getVisible();
        }

        $table = $model->getConnection()->getTablePrefix() . $model->getTable();

        if ($model->getConnection()->getDriverName() === 'sqlite') {
            $sql = "pragma table_info($table)";

            $columns = collect($model->getConnection()->raw($sql));
        } else {
            $sql = 'select column_name as `name`, data_type as `type` from information_schema.columns where table_schema = ? and table_name = ?';

            $columns = collect($model->getConnection()
                ->selectFromWriteConnection(
                    $sql,
                    [$model->getConnection()->getDatabaseName(), $table]
                ));
        }

        if (! $columns->count()) {
            return static::$tableColumns[$model::class] = [];
        }

        $columns = $columns
            ->whereNotIn('name', $model->getHidden())
            ->pluck('type', 'name')
            ->toArray();

        return static::$tableColumns[$model::class] = $columns;
    }
}
