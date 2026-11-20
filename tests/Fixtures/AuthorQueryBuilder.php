<?php

namespace SnowDigital\JsonApi\Tests\Fixtures;

use SnowDigital\JsonApi\QueryBuilder\DefaultQueryBuilder;

class AuthorQueryBuilder extends DefaultQueryBuilder
{
    protected static array $tableColumns = [];

    /** Counts constructions: a builder's constructor may have side effects v2 must not trigger behind our back. */
    public static int $constructed = 0;

    public function __construct()
    {
        static::$constructed++;

        parent::__construct(new Author);
    }

    public function getIncludes(): array
    {
        return array_merge(parent::getIncludes(), ['posts.content']);
    }
}
