<?php

namespace SnowDigital\LaravelJsonApi;

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
            $filters[] = match ($type) {
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

        $sql = 'select column_name as `column_name`, data_type as `data_type` from information_schema.columns where table_schema = ? and table_name = ?';
        $table = $model->getConnection()->getTablePrefix() . $model->getTable();

        $columns = collect($model->getConnection()
            ->selectFromWriteConnection(
                $sql,
                [$model->getConnection()->getDatabaseName(), $table]
            ));

        if (! $columns->count()) {
            return static::$tableColumns[$model::class] = [];
        }

        $columns = $columns
            ->whereNotIn('column_name', $model->getHidden())
            ->pluck('data_type', 'column_name')
            ->toArray();

        return static::$tableColumns[$model::class] = $columns;
    }
}
