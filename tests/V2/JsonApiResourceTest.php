<?php

namespace SnowDigital\JsonApi\Tests\V2;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use SnowDigital\JsonApi\Tests\Fixtures\Offer;
use SnowDigital\JsonApi\Tests\Fixtures\OfferResultResource;
use SnowDigital\JsonApi\Tests\Fixtures\Provider;
use SnowDigital\JsonApi\Tests\TestCase;
use SnowDigital\JsonApi\V2\JsonApiCollection;
use SnowDigital\JsonApi\V2\Schema;

class JsonApiResourceTest extends TestCase
{
    #[Test]
    public function a_custom_resource_keeps_types_identifiers_and_dedup(): void
    {
        Schema::types([Offer::class => 'offers', Provider::class => 'providers']);

        $provider = Provider::create(['name' => 'Alpha', 'slug' => 'alpha']);
        Offer::create(['name' => 'one', 'provider_id' => $provider->id]);
        Offer::create(['name' => 'two', 'provider_id' => $provider->id]);

        Route::get('results', function () {
            $collection = OfferResultResource::collection(Offer::with('provider')->paginate(10));
            $this->assertInstanceOf(JsonApiCollection::class, $collection);

            return $collection;
        });

        $response = $this->getJson('/results?include=provider')->assertOk();

        $this->assertSame('offers', $response->json('data.1.type'));
        $this->assertSame(
            ['name' => 'two', 'type' => 'mobile', 'duplicating_id' => null, 'created_at' => $response->json('data.1.attributes.created_at'), 'updated_at' => $response->json('data.1.attributes.updated_at'), 'computed' => 'two!'],
            $response->json('data.1.attributes'),
        );
        $this->assertCount(1, $response->json('included'));
        $this->assertArrayNotHasKey('computed', $response->json('included.0.attributes'), 'included models use the base resource');

        $response = $this->getJson('/results')->assertOk();

        $this->assertSame(['id' => '1', 'type' => 'providers'], $response->json('data.0.relationships.provider.data'), 'loaded, not included');
        $this->assertArrayNotHasKey('included', $response->json());
    }
}
