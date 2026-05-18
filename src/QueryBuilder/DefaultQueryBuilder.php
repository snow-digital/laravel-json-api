<?php

namespace SnowDigital\JsonApi\QueryBuilder;

use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DefaultQueryBuilder extends QueryBuilder
{
    protected static array $tableColumns = [];

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
                default => AllowedFilter::partial($column),
            };
        }

        return $filters;
    }

    public function getIncludes(): array
    {
        return [];
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
