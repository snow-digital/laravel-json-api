<?php

namespace SnowDigital\JsonApi\Tests\Fixtures;

use SnowDigital\JsonApi\QueryBuilder\DefaultQueryBuilder;

class CommentQueryBuilder extends DefaultQueryBuilder
{
    protected static array $tableColumns = [];

    public function __construct()
    {
        parent::__construct(new Comment);
    }

    public function getIncludes(): array
    {
        return ['commentable'];
    }
}
