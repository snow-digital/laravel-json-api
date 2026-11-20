<?php

namespace SnowDigital\JsonApi\Tests\Fixtures;

use SnowDigital\JsonApi\QueryBuilder\DefaultQueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class PostQueryBuilder extends DefaultQueryBuilder
{
    protected static array $tableColumns = [];

    public function __construct()
    {
        parent::__construct(new Post);
    }

    public function getFilters(): array
    {
        return array_merge(parent::getFilters(), [
            'author.slug' => AllowedFilter::exact('author.slug'),
        ]);
    }

    public function getIncludes(): array
    {
        return ['author', 'content'];
    }
}
