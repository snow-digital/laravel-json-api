<?php

namespace SnowDigital\JsonApi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed resource(string $key)
 * @method static \SnowDigital\JsonApi\ApiEvents events()
 * @method static \SnowDigital\JsonApi\ApiRegister discoverResources(string $directory, string $namespace)
 * @method static \SnowDigital\JsonApi\ApiRegister setResources(array $resources)
 * @method static \SnowDigital\JsonApi\ApiRegister setResource(string $key, string $resource)
 * @method static array keys()
 * @method static \SnowDigital\JsonApi\ApiRegister types(array<class-string<\Illuminate\Database\Eloquent\Model>, string> $types)
 * @method static string type(\Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model> $model)
 * @method static void rememberType(class-string<\Illuminate\Database\Eloquent\Model> $class, string $key)
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
