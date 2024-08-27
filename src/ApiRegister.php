<?php

namespace SnowDigital\JsonApi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Spatie\QueryBuilder\QueryBuilder;

class ApiRegister
{
    public static array $resources = [];

    public function resource(string $key): mixed
    {
        return Arr::get(ApiRegister::$resources, $key);
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
}
