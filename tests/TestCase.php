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
use SnowDigital\JsonApi\Tests\Fixtures\Article;
use SnowDigital\JsonApi\Tests\Fixtures\Video;
use SnowDigital\JsonApi\Tests\Fixtures\Post;
use SnowDigital\JsonApi\Tests\Fixtures\PostQueryBuilder;
use SnowDigital\JsonApi\Tests\Fixtures\Author;
use SnowDigital\JsonApi\Tests\Fixtures\AuthorQueryBuilder;
use SnowDigital\JsonApi\Tests\Fixtures\CommentQueryBuilder;
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
        AuthorQueryBuilder::$constructed = 0;

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
            'posts' => [PostQueryBuilder::class, ['browse', 'show']],
            'authors' => [AuthorQueryBuilder::class, ['browse', 'show']],
            'comments' => CommentQueryBuilder::class,
            'videos' => Video::class,
        ]);

        Relation::morphMap([
            'posts.content-video' => Video::class,
            'posts.content-article' => Article::class,
            'posts.post' => Post::class,
            'authors.author' => Author::class,
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
        DatabaseSchema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->json('data')->nullable();
            $table->timestamps();
        });

        DatabaseSchema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('article');
            $table->foreignId('author_id')->nullable();
            $table->nullableMorphs('content');
            $table->foreignId('original_id')->nullable();
            $table->string('secret')->nullable();
            $table->timestamps();
        });

        DatabaseSchema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->integer('seconds');
            $table->timestamps();
        });

        DatabaseSchema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->integer('words');
            $table->timestamps();
        });

        DatabaseSchema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable');
            $table->integer('score');
            $table->timestamps();
        });
    }
}
