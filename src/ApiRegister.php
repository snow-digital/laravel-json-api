<?php

namespace SnowDigital\JsonApi;

use Illuminate\Support\Arr;

class ApiRegister
{
    public static array $resources = [];

    public function resource(string $key): mixed
    {
        return Arr::get(ApiRegister::$resources, $key);
    }

    public function setResources(array $resources): void
    {
        static::$resources = array_unique(
            array_merge(static::$resources, $resources)
        );
    }

    public function setResource(string $key, string $resource): void
    {
        static::$resources[$key] = $resource;
    }

    public function keys(): array
    {
        return array_keys(static::$resources);
    }
}
