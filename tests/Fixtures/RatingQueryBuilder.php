<?php

namespace SnowDigital\JsonApi\Tests\Fixtures;

use SnowDigital\JsonApi\QueryBuilder\DefaultQueryBuilder;

class RatingQueryBuilder extends DefaultQueryBuilder
{
    protected static array $tableColumns = [];

    public function __construct()
    {
        parent::__construct(new Rating);
    }

    public function getIncludes(): array
    {
        return ['rateable'];
    }
}
