<?php

namespace SnowDigital\JsonApi\Tests\Docs;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\ScrambleServiceProvider;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use SnowDigital\JsonApi\ApiController;
use SnowDigital\JsonApi\Docs\QueryBuilderToSchema;
use SnowDigital\JsonApi\Tests\Fixtures\Post;
use SnowDigital\JsonApi\Tests\Fixtures\PostQueryBuilder;
use SnowDigital\JsonApi\Tests\Fixtures\ResourceObjectController;
use SnowDigital\JsonApi\Tests\TestCase;

class QueryBuilderToSchemaTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), ScrambleServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('scramble.api_path', 'api');
        $app['config']->set('scramble.extensions', [QueryBuilderToSchema::class]);
        $app['config']->set('json-api.auto_routing.enabled', false);
    }

    protected function defineRoutes($router): void
    {
        // One route per resource rather than a `{resource}` parameter.
        Route::get('api/v1/posts', [ApiController::class, 'index'])->name('posts.browse')->defaults('resource', 'posts');
        Route::get('api/v1/posts/{id}', [ApiController::class, 'show'])->name('posts.read')->defaults('resource', 'posts');
        Route::get('api/v2/posts', [ResourceObjectController::class, 'index'])->name('v2.posts.index')->defaults('resource', 'posts');
        Route::get('api/v2/posts/{id}', [ResourceObjectController::class, 'show'])->name('v2.posts.show')->defaults('resource', 'posts');
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(PostQueryBuilder::class, 'tableColumns'))->setValue(null, []);

        parent::tearDown();
    }

    #[Test]
    public function both_formats_are_documented_from_the_controllers_return_types(): void
    {
        // The package's sqlite column introspection is broken (`raw()` returns an Expression).
        (new \ReflectionProperty(PostQueryBuilder::class, 'tableColumns'))
            ->setValue(null, [Post::class => ['id' => 'bigint', 'name' => 'varchar', 'author_id' => 'bigint']]);
        QueryBuilderToSchema::$referenceNamePrefix = 'SnowDigital\\JsonApi\\Tests\\Fixtures\\';

        $doc = app(Generator::class)->setThrowExceptions(true)(Scramble::getGeneratorConfig(Scramble::DEFAULT_API));

        $browse = $doc['paths']['/v1/posts']['get']['responses'];
        $this->assertSame('Paginated set of `JsonApiResource`', $browse[200]['description']);
        $this->assertSame(['$ref' => '#/components/schemas/Post'], $browse[200]['content']['application/json']['schema']['properties']['data']['items']);
        $this->assertArrayHasKey(404, $browse);
        $this->assertSame(['$ref' => '#/components/schemas/Post'], $doc['paths']['/v1/posts/{id}']['get']['responses'][200]['content']['application/json']['schema']['properties']['data']);

        $index = $doc['paths']['/v2/posts']['get']['responses'][200];
        $this->assertSame('Array of `ResourceObject`', $index['description']);
        $this->assertArrayHasKey('application/vnd.api+json', $index['content']);
        $this->assertSame('`ResourceObject`', $doc['paths']['/v2/posts/{id}']['get']['responses'][200]['description']);
    }
}
