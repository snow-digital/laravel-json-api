<?php

namespace SnowDigital\JsonApi\Tests\Fixtures;

use SnowDigital\JsonApi\QueryBuilder\DefaultQueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class OfferQueryBuilder extends DefaultQueryBuilder
{
    protected static array $tableColumns = [];

    public function __construct()
    {
        parent::__construct(new Offer);
    }

    public function getFilters(): array
    {
        return array_merge(parent::getFilters(), [
            'provider.slug' => AllowedFilter::exact('provider.slug'),
        ]);
    }

    public function getIncludes(): array
    {
        return ['provider', 'entry'];
    }
}
