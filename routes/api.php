<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

if (Config::get('json-api.auto_routing.enabled')) {
    $prefix = Config::get('json-api.auto_routing.prefix') . '/{resource}';

    Route::name('json-api.')
        ->prefix($prefix)
        ->group(function () {
            if (Config::get('json-api.auto_routing.routes.index')) {
                Route::get('/', [SnowDigital\JsonApi\JsonApiController::class, 'index'])
                    ->name('browse');
            }

            if (Config::get('json-api.auto_routing.routes.show')) {
                Route::get('/{id}', [SnowDigital\JsonApi\JsonApiController::class, 'show'])
                    ->name('show');
            }
        })
        ->whereIn('resource', SnowDigital\JsonApi\JsonApi::keys());
}
