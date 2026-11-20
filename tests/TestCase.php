<?php

namespace SnowDigital\JsonApi\Tests;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Orchestra\Testbench\TestCase as Orchestra;
use SnowDigital\JsonApi\ApiRegister;
use SnowDigital\JsonApi\Facades\JsonApi;
use SnowDigital\JsonApi\JsonApiServiceProvider;
use SnowDigital\JsonApi\Tests\Fixtures\EntryInternet;
use SnowDigital\JsonApi\Tests\Fixtures\EntryMobile;
use SnowDigital\JsonApi\Tests\Fixtures\Offer;
use SnowDigital\JsonApi\Tests\Fixtures\OfferQueryBuilder;
use SnowDigital\JsonApi\Tests\Fixtures\Provider;
use SnowDigital\JsonApi\Tests\Fixtures\ProviderQueryBuilder;
use SnowDigital\JsonApi\Tests\Fixtures\RatingQueryBuilder;
use SnowDigital\JsonApi\Resources\ResourceRelationships;
use SnowDigital\JsonApi\Tests\Fixtures\ResourceObjectController;
use Spatie\JsonApiPaginate\JsonApiPaginateServiceProvider;
use Spatie\QueryBuilder\QueryBuilderServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
    }

    protected function tearDown(): void
    {
        ApiRegister::$resources = [];
        $this->flushTypes();
        Relation::morphMap([], false);
        ProviderQueryBuilder::$constructed = 0;

        parent::tearDown();
    }

    protected function flushTypes(): void
    {
        ApiRegister::$types = [];
        ResourceRelationships::flush();
    }

    protected function getPackageProviders($app): array
    {
        return [
            QueryBuilderServiceProvider::class,
            JsonApiPaginateServiceProvider::class,
            JsonApiServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('json-api.auto_register.enabled', false);
        $app['config']->set('json-api.auto_routing.prefix', 'api/v1');
        $app['config']->set('query-builder.disable_invalid_includes_query_exception', true);

        JsonApi::setResources([
            'offers' => [OfferQueryBuilder::class, ['browse', 'show']],
            'providers' => [ProviderQueryBuilder::class, ['browse', 'show']],
            'ratings' => RatingQueryBuilder::class,
            'mobiles' => EntryMobile::class,
        ]);

        Relation::morphMap([
            'offers.entry-mobile' => EntryMobile::class,
            'offers.entry-internet' => EntryInternet::class,
            'offers.offer' => Offer::class,
            'providers.provider' => Provider::class,
        ]);
    }

    protected function defineRoutes($router): void
    {
        Route::prefix('api/v2')
            ->name('v2.')
            ->group(function () {
                Route::get('{resource}', [ResourceObjectController::class, 'index'])->name('browse');
                Route::get('{resource}/{id}', [ResourceObjectController::class, 'show'])->name('show');
            });
    }

    protected function createTables(): void
    {
        DatabaseSchema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->json('data')->nullable();
            $table->timestamps();
        });

        DatabaseSchema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('mobile');
            $table->foreignId('provider_id')->nullable();
            $table->nullableMorphs('entry');
            $table->foreignId('duplicating_id')->nullable();
            $table->string('secret')->nullable();
            $table->timestamps();
        });

        DatabaseSchema::create('entry_mobiles', function (Blueprint $table) {
            $table->id();
            $table->integer('data_gb');
            $table->timestamps();
        });

        DatabaseSchema::create('entry_internets', function (Blueprint $table) {
            $table->id();
            $table->integer('speed');
            $table->timestamps();
        });

        DatabaseSchema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->morphs('rateable');
            $table->integer('score');
            $table->timestamps();
        });
    }
}
