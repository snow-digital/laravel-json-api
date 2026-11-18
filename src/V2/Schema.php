<?php

namespace SnowDigital\JsonApi\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use SnowDigital\JsonApi\ApiRegister;
use SnowDigital\JsonApi\QueryBuilder\DefaultQueryBuilder;

/**
 * What v2 knows about each model: its JSON:API type and its declared relationships.
 */
class Schema
{
    /** @var array<class-string<Model>, string> */
    protected static array $types = [];

    /** @var array<class-string<Model>, list<string>> */
    protected static array $relationships = [];

    /** @var array<class-string<Model>, array{belongsTo: list<string>, foreignKeys: list<string>}> */
    protected static array $foreignKeys = [];

    /**
     * Map models to the resource key they are served under, e.g. [Offer::class => 'offers'].
     *
     * Needed for models registered through a query builder: the builder's model is only
     * known once it is constructed, and constructing one has side effects.
     *
     * @param  array<class-string<Model>, string>  $types
     */
    public static function types(array $types): void
    {
        static::$types = array_merge(static::$types, $types);
        static::$relationships = array_diff_key(static::$relationships, $types);
        static::$foreignKeys = array_diff_key(static::$foreignKeys, $types);
    }

    /**
     * @param  list<string>  $includes
     */
    public static function remember(Model $model, string $type, array $includes): void
    {
        static::$types[$model::class] ??= $type;

        if (static::$types[$model::class] === $type) {
            static::$relationships[$model::class] ??= static::topLevel($model, $includes);
        }
    }

    /**
     * @param  Model|class-string<Model>  $model
     */
    public static function type(Model|string $model): string
    {
        $class = is_string($model) ? $model : $model::class;

        if ($type = static::$types[$class] ?? static::registeredKey($class)) {
            return $type;
        }

        $alias = Relation::getMorphAlias($class);

        if ($alias !== $class) {
            // Morph aliases like `offers.entry-mobile` use characters member names may not.
            return trim(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $alias), '-_');
        }

        return (string) Str::of(class_basename($class))->snake()->pluralStudly();
    }

    /**
     * Top-level relationship names the model's resource declares (its builder's includes).
     *
     * @return list<string>
     */
    public static function relationships(Model $model): array
    {
        return static::$relationships[$model::class] ??= static::topLevel($model, static::declaredIncludes($model));
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
    public static function relationColumns(BelongsTo $relation): array
    {
        return $relation instanceof MorphTo
            ? [$relation->getForeignKeyName(), $relation->getMorphType()]
            : [$relation->getForeignKeyName()];
    }

    public static function flush(): void
    {
        static::$types = [];
        static::$relationships = [];
        static::$foreignKeys = [];
    }

    /**
     * @return array{belongsTo: list<string>, foreignKeys: list<string>}
     */
    protected static function foreignKeyInfo(Model $model): array
    {
        if (isset(static::$foreignKeys[$model::class])) {
            return static::$foreignKeys[$model::class];
        }

        // A blank instance gives one answer per class, so every row of a type has the same attributes.
        $blank = $model->newInstance();
        $belongsTo = [];
        $foreignKeys = [];

        foreach (static::relationships($model) as $name) {
            $relation = $blank->{$name}();

            if ($relation instanceof BelongsTo) {
                $belongsTo[] = $name;
                array_push($foreignKeys, ...static::relationColumns($relation));
            }
        }

        return static::$foreignKeys[$model::class] = [
            'belongsTo' => $belongsTo,
            'foreignKeys' => array_values(array_unique($foreignKeys)),
        ];
    }

    /**
     * @param  class-string<Model>  $class
     */
    protected static function registeredKey(string $class): ?string
    {
        foreach (ApiRegister::$resources as $key => $resource) {
            if ((is_array($resource) ? $resource[0] : $resource) === $class) {
                return static::$types[$class] = (string) $key;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected static function declaredIncludes(Model $model): array
    {
        $type = static::type($model);
        $resource = ApiRegister::$resources[$type] ?? null;
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
