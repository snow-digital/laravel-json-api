<?php

namespace SnowDigital\JsonApi;

use Illuminate\Support\Facades\Config;
use SnowDigital\JsonApi\Facades\JsonApi;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class JsonApiServiceProvider extends PackageServiceProvider
{
    public array $singletons = [
        ApiRegister::class => ApiRegister::class,
    ];

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-json-api')
            ->hasConfigFile()
            ->hasRoute('api');
    }

    public function packageRegistered(): void
    {
        if (Config::get('json-api.auto_register.enabled')) {
            JsonApi::discoverResources(
                Config::get('json-api.auto_register.path'),
                Config::get('json-api.auto_register.namespace'),
            );
        }
    }
}
