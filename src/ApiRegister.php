<?php

namespace SnowDigital\JsonApi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\QueryBuilder;

class ApiRegister
{
    public static array $resources = [];

    /** @var array<class-string<Model>, string> */
    public static array $types = [];

    public function resource(string $key): mixed
    {
        return Arr::get(ApiRegister::$resources, $key);
    }

    public function events(): ApiEvents
    {
        return app(ApiEvents::class);
    }

    public function discoverResources(string $directory, string $namespace): static
    {
        if (blank($directory) || blank($namespace)) {
            return $this;
        }

        $filesystem = app(Filesystem::class);

        if ((! $filesystem->exists($directory)) && (! str($directory)->contains('*'))) {
            return $this;
        }

        $namespace = str($namespace);

        foreach ($filesystem->allFiles($directory) as $file) {
            $variableNamespace = $namespace->contains('*') ? str_ireplace(
                ['\\' . $namespace->before('*'), $namespace->after('*')],
                ['', ''],
                str_replace([DIRECTORY_SEPARATOR], ['\\'], (string) str($file->getPath())->after(base_path())),
            ) : null;

            if (is_string($variableNamespace)) {
                $variableNamespace = (string) str($variableNamespace)->before('\\');
            }

            $class = (string) $namespace
                ->append('\\', $file->getRelativePathname())
                ->replace('*', $variableNamespace ?? '')
                ->replace([DIRECTORY_SEPARATOR, '.php'], ['\\', '']);

            if (! class_exists($class)) {
                continue;
            }

            if ((new \ReflectionClass($class))->isAbstract()) {
                continue;
            }

            if (! is_subclass_of($class, Model::class) && ! is_subclass_of($class, QueryBuilder::class)) {
                continue;
            }

            $resource = (string) str($class)
                ->after($namespace->replace('*', $variableNamespace ?? ''))
                ->trim('\\')
                ->kebab()
                ->replace('\\-', '/')
                ->plural();

            static::$resources[$resource] = $class;
        }

        return $this;
    }

    public function setResources(array $resources): static
    {
        static::$resources = array_merge(static::$resources, $resources);

        return $this;
    }

    public function setResource(string $key, string $resource): static
    {
        static::$resources[$key] = $resource;

        return $this;
    }

    public function keys(): array
    {
        return array_keys(static::$resources);
    }

    public function key(string $resource): string|int|false
    {
        return array_search($resource, static::$resources);
    }

    /**
     * @param  array<class-string<Model>, string>  $types
     */
    public function types(array $types): static
    {
        static::$types = array_merge(static::$types, $types);

        return $this;
    }

    /**
     * @param  Model|class-string<Model>  $model
     */
    public function type(Model|string $model): string
    {
        $class = is_string($model) ? $model : $model::class;

        if ($type = static::$types[$class] ?? $this->registeredKey($class)) {
            return $type;
        }

        $alias = Relation::getMorphAlias($class);

        if ($alias !== $class) {
            // Member names may not contain characters such as `.`.
            return trim(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $alias), '-_');
        }

        return (string) Str::of(class_basename($class))->snake()->pluralStudly();
    }

    /**
     * @param  class-string<Model>  $class
     */
    public function rememberType(string $class, string $key): void
    {
        static::$types[$class] ??= $key;
    }

    /**
     * @param  class-string<Model>  $class
     */
    protected function registeredKey(string $class): ?string
    {
        foreach (static::$resources as $key => $resource) {
            if ((is_array($resource) ? $resource[0] : $resource) === $class) {
                return static::$types[$class] = (string) $key;
            }
        }

        return null;
    }
}
