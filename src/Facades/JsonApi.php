<?php

namespace SnowDigital\JsonApi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed resource(string $key)
 * @method static void setResources(array $resources)
 * @method static void setResource(string $key, string $resource)
 * @method static array keys()
 *
 * @see \SnowDigital\JsonApi\ApiRegister
 */
class JsonApi extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \SnowDigital\JsonApi\ApiRegister::class;
    }
}
