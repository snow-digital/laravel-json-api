<?php

namespace SnowDigital\JsonApi\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use SnowDigital\JsonApi\ApiRegister;
use SnowDigital\JsonApi\Facades\JsonApi;
use SnowDigital\JsonApi\QueryBuilder\DefaultQueryBuilder;

/**
 * The relationships a resource object declares: its type's query builder includes.
 */
class ResourceRelationships
{
    /** @var array<string, list<string>> keyed by "model class@type" */
    protected static array $declared = [];

    /** @var array<string, array{belongsTo: list<string>, foreignKeys: list<string>}> keyed by "model class@type" */
    protected static array $foreignKeys = [];

    /**
     * Use the includes of the builder being served, for the type it is served as.
     *
     * @param  array<int, mixed>  $includes
     */
    public static function remember(Model $model, string $type, array $includes): void
    {
        if (JsonApi::type($model) === $type) {
            static::$declared[static::key($model)] ??= static::topLevel($model, $includes);
        }
    }

    /**
     * Top-level relationship names (`posts.author` declares `posts`).
     *
     * @return list<string>
     */
    public static function declared(Model $model): array
    {
        return static::$declared[static::key($model)] ??= static::topLevel($model, static::declaredIncludes($model));
    }

    /**
     * Declared relationships that are a belongsTo (or morphTo) on this model class.
     *
     * @return list<string>
     */
    public static function belongsTo(Model $model): array
    {
        return static::foreignKeyInfo($model)['belongsTo'];
    }

    /**
     * Columns that only exist to point at a declared belongsTo, and so are not attributes.
     *
     * @return list<string>
     */
    public static function foreignKeys(Model $model): array
    {
        return static::foreignKeyInfo($model)['foreignKeys'];
    }

    /**
     * @return list<string>
     */
    public static function columns(BelongsTo $relation): array
    {
        return $relation instanceof MorphTo
            ? [$relation->getForeignKeyName(), $relation->getMorphType()]
            : [$relation->getForeignKeyName()];
    }

    public static function flush(): void
    {
        static::$declared = [];
        static::$foreignKeys = [];
    }

    protected static function key(Model $model): string
    {
        return $model::class . '@' . JsonApi::type($model);
    }

    /**
     * @return array{belongsTo: list<string>, foreignKeys: list<string>}
     */
    protected static function foreignKeyInfo(Model $model): array
    {
        $key = static::key($model);

        if (isset(static::$foreignKeys[$key])) {
            return static::$foreignKeys[$key];
        }

        // A blank instance gives one answer per class, so every row of a type has the same attributes.
        $blank = $model->newInstance();
        $belongsTo = [];
        $foreignKeys = [];

        foreach (static::declared($model) as $name) {
            $relation = $blank->{$name}();

            if ($relation instanceof BelongsTo) {
                $belongsTo[] = $name;
                array_push($foreignKeys, ...static::columns($relation));
            }
        }

        return static::$foreignKeys[$key] = [
            'belongsTo' => $belongsTo,
            'foreignKeys' => array_values(array_unique($foreignKeys)),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected static function declaredIncludes(Model $model): array
    {
        $resource = ApiRegister::$resources[JsonApi::type($model)] ?? null;
        $resource = is_array($resource) ? $resource[0] : $resource;

        if ($resource === $model::class) {
            $resource = DefaultQueryBuilder::class;
        }

        if (! is_string($resource) || ! is_a($resource, DefaultQueryBuilder::class, true)) {
            return [];
        }

        // Builder constructors read and validate the current request (and may have side
        // effects), so only the includes are asked for, from an instance never constructed.
        /** @var DefaultQueryBuilder $builder */
        $builder = (new \ReflectionClass($resource))->newInstanceWithoutConstructor();
        $builder->resource = $model->newInstance();

        return $builder->getIncludes();
    }

    /**
     * @param  array<int, mixed>  $includes
     * @return list<string>
     */
    protected static function topLevel(Model $model, array $includes): array
    {
        return collect($includes)
            ->filter(fn ($include) => is_string($include))
            ->map(fn (string $include) => Str::before($include, '.'))
            ->unique()
            ->filter(fn (string $name) => method_exists($model, $name))
            ->values()
            ->all();
    }
}
