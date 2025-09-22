<?php

namespace SnowDigital\JsonApi\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class FiltersJson implements Filter
{
    public function __invoke(Builder $query, $value, string $property): void
    {
        if (is_array($value)) {
            array_map(function ($value) use ($query, $property) {
                $this->applyFilter($query, $value, $property);
            }, $value);

            return;
        }

        $this->applyFilter($query, $value, $property);
    }

    protected function applyFilter(Builder $query, mixed $value, string $property): void
    {
        $not = false;

        if (str_starts_with($value, '!')) {
            $value = substr($value, 1);

            $not = true;
        }

        if (str_contains($value, '.')) {
            [$key, $value] = explode('.', $value, 2);

            $query->{$not ? 'whereJsonDoesntContain' : 'whereJsonContains'}("data->$key", $value);

            return;
        }

        $query->{$not ? 'whereJsonDoesntContainKey' : 'whereJsonContainsKey'}("data->$value");
    }
}
