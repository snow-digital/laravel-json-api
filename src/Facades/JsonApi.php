<?php

namespace SnowDigital\JsonApi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed resource(string $key)
 * @method static \SnowDigital\JsonApi\ApiRegister discoverResources(string $directory, string $namespace)
 * @method static \SnowDigital\JsonApi\ApiRegister setResources(array $resources)
 * @method static \SnowDigital\JsonApi\ApiRegister setResource(string $key, string $resource)
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
