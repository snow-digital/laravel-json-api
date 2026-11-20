<?php

namespace SnowDigital\JsonApi\Tests\Docs;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\ScrambleServiceProvider;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use SnowDigital\JsonApi\ApiController;
use SnowDigital\JsonApi\Docs\QueryBuilderToSchema;
use SnowDigital\JsonApi\Tests\Fixtures\Offer;
use SnowDigital\JsonApi\Tests\Fixtures\OfferQueryBuilder;
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
        // One route per resource, the way telecom declares them.
        Route::get('api/v1/offers', [ApiController::class, 'index'])->name('offers.browse')->defaults('resource', 'offers');
        Route::get('api/v1/offers/{id}', [ApiController::class, 'show'])->name('offers.read')->defaults('resource', 'offers');
        Route::get('api/v2/offers', [ResourceObjectController::class, 'index'])->name('v2.offers.index')->defaults('resource', 'offers');
        Route::get('api/v2/offers/{id}', [ResourceObjectController::class, 'show'])->name('v2.offers.show')->defaults('resource', 'offers');
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(OfferQueryBuilder::class, 'tableColumns'))->setValue(null, []);

        parent::tearDown();
    }

    #[Test]
    public function both_formats_are_documented_from_the_controllers_return_types(): void
    {
        // The package's sqlite column introspection is broken (`raw()` returns an Expression).
        (new \ReflectionProperty(OfferQueryBuilder::class, 'tableColumns'))
            ->setValue(null, [Offer::class => ['id' => 'bigint', 'name' => 'varchar', 'provider_id' => 'bigint']]);
        QueryBuilderToSchema::$referenceNamePrefix = 'SnowDigital\\JsonApi\\Tests\\Fixtures\\';

        $doc = app(Generator::class)->setThrowExceptions(true)(Scramble::getGeneratorConfig(Scramble::DEFAULT_API));

        $browse = $doc['paths']['/v1/offers']['get']['responses'];
        $this->assertSame('Paginated set of `JsonApiResource`', $browse[200]['description']);
        $this->assertSame(['$ref' => '#/components/schemas/Offer'], $browse[200]['content']['application/json']['schema']['properties']['data']['items']);
        $this->assertArrayHasKey(404, $browse);
        $this->assertSame(['$ref' => '#/components/schemas/Offer'], $doc['paths']['/v1/offers/{id}']['get']['responses'][200]['content']['application/json']['schema']['properties']['data']);

        $index = $doc['paths']['/v2/offers']['get']['responses'][200];
        $this->assertSame('Array of `ResourceObject`', $index['description']);
        $this->assertArrayHasKey('application/vnd.api+json', $index['content']);
        $this->assertSame('`ResourceObject`', $doc['paths']['/v2/offers/{id}']['get']['responses'][200]['description']);
    }
}
