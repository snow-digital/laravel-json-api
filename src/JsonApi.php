<?php

namespace SnowDigital\JsonApi;

use Illuminate\Support\Arr;

class JsonApi
{
    public static array $resources = [];

    public static function resource(string $key): mixed
    {
        return Arr::get(JsonApi::$resources, $key);
    }

    public static function setResources(array $resources): void
    {
        JsonApi::$resources = array_unique(
            array_merge(JsonApi::$resources, $resources)
        );
    }

    public static function setResource(string $key, string $resource): void
    {
        self::$resources[$key] = $resource;
    }

    public static function key(string $resource): string|int|false
    {
        return array_search($resource, JsonApi::$resources);
    }
}
