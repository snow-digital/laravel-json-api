<?php

namespace SnowDigital\JsonApi\Tests\Fixtures;

use SnowDigital\JsonApi\QueryBuilder\DefaultQueryBuilder;

class ProviderQueryBuilder extends DefaultQueryBuilder
{
    protected static array $tableColumns = [];

    /** Like telecom's builder, the constructor has a side effect v2 must not trigger behind our back. */
    public static int $constructed = 0;

    public function __construct()
    {
        static::$constructed++;

        parent::__construct(new Provider);
    }

    public function getIncludes(): array
    {
        return array_merge(parent::getIncludes(), ['offers.entry']);
    }
}
