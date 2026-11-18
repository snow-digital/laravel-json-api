<?php

namespace SnowDigital\JsonApi\Tests\V1;

use PHPUnit\Framework\Attributes\Test;
use SnowDigital\JsonApi\Tests\Fixtures\EntryMobile;
use SnowDigital\JsonApi\Tests\Fixtures\Offer;
use SnowDigital\JsonApi\Tests\Fixtures\Provider;
use SnowDigital\JsonApi\Tests\TestCase;

class ApiControllerTest extends TestCase
{
    #[Test]
    public function v1_still_nests_relations_inside_attributes(): void
    {
        $provider = Provider::create(['name' => 'Alpha', 'slug' => 'alpha']);
        $entry = EntryMobile::create(['data_gb' => 5]);
        Offer::create(['name' => 'one', 'provider_id' => $provider->id, 'entry_type' => 'offers.entry-mobile', 'entry_id' => $entry->id]);

        $response = $this->getJson('/api/v1/offers?include=provider,entry')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json');

        $this->assertSame(['id', 'attributes'], array_keys($response->json('data.0')));
        $this->assertSame(1, $response->json('data.0.attributes.id'));
        $this->assertSame(1, $response->json('data.0.attributes.provider_id'));
        $this->assertSame('Alpha', $response->json('data.0.attributes.provider.name'));
        $this->assertSame(5, $response->json('data.0.attributes.entry.data_gb'));
        $this->assertArrayNotHasKey('included', $response->json());

        $this->getJson('/api/v1/offers/1')
            ->assertOk()
            ->assertJsonPath('data.id', '1')
            ->assertJsonPath('data.attributes.name', 'one');
    }
}
