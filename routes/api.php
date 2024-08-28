<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

if (Config::get('json-api.auto_routing.enabled')) {
    $prefix = Config::get('json-api.auto_routing.prefix') . '/{resource}';

    Route::name('json-api.')
        ->prefix($prefix)
        ->group(function () {
            if (Config::get('json-api.auto_routing.routes.index')) {
                Route::get('/', [SnowDigital\JsonApi\ApiController::class, 'index'])
                    ->name('browse');
            }

            if (Config::get('json-api.auto_routing.routes.show')) {
                Route::get('/{id}', [SnowDigital\JsonApi\ApiController::class, 'show'])
                    ->name('show');
            }

            if (Config::get('json-api.auto_routing.routes.patch')) {
                Route::patch('/{id}', [SnowDigital\JsonApi\ApiController::class, 'patch'])
                    ->where('id', '[0-9]+')
                    ->name('edit');
            }

            if (Config::get('json-api.auto_routing.routes.post')) {
                Route::post('/', [SnowDigital\JsonApi\ApiController::class, 'post'])
                    ->name('add');
            }

            if (Config::get('json-api.auto_routing.routes.delete')) {
                Route::delete('/{id}', [SnowDigital\JsonApi\ApiController::class, 'delete'])
                    ->where('id', '[0-9]+')
                    ->name('delete');
            }

            if (Config::get('json-api.auto_routing.routes.restore')) {
                Route::post('/{id}/restore', [SnowDigital\JsonApi\ApiController::class, 'restore'])
                    ->where('id', '[0-9]+')
                    ->name('restore');
            }
        })
        ->whereIn('resource', SnowDigital\JsonApi\Facades\JsonApi::keys());
}
