<?php

namespace SnowDigital\JsonApi;

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
}
