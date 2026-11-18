<?php

namespace SnowDigital\JsonApi\Tests\V2;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use SnowDigital\JsonApi\Events\AfterIndex;
use SnowDigital\JsonApi\Tests\Fixtures\EntryInternet;
use SnowDigital\JsonApi\Tests\Fixtures\EntryMobile;
use SnowDigital\JsonApi\Tests\Fixtures\Offer;
use SnowDigital\JsonApi\Tests\Fixtures\Provider;
use SnowDigital\JsonApi\Tests\Fixtures\ProviderQueryBuilder;
use SnowDigital\JsonApi\Tests\Fixtures\Rating;
use SnowDigital\JsonApi\Tests\TestCase;
use SnowDigital\JsonApi\V2\JsonApiCollection;
use SnowDigital\JsonApi\V2\Schema;

class ApiControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::types([
            Offer::class => 'offers',
            Provider::class => 'providers',
            Rating::class => 'ratings',
        ]);
    }

    /**
     * 2 providers sharing 8 offers, 1 offer without provider, 1 offer of a third provider.
     *
     * @return array{0: Provider, 1: Provider, 2: Provider}
     */
    protected function seedOffers(int $perProvider = 4): array
    {
        $providers = [
            Provider::create(['name' => 'Alpha', 'slug' => 'alpha', 'data' => ['blurb' => str_repeat('a', 50)]]),
            Provider::create(['name' => 'Beta', 'slug' => 'beta', 'data' => ['blurb' => 'b']]),
            Provider::create(['name' => 'Gamma', 'slug' => 'gamma']),
        ];

        foreach ([0, 1] as $p) {
            for ($i = 0; $i < $perProvider; $i++) {
                $entry = $i % 2
                    ? EntryInternet::create(['speed' => 100 * $i])
                    : EntryMobile::create(['data_gb' => 10 * $i]);

                Offer::create([
                    'name' => "offer {$p}-{$i}",
                    'type' => $i % 2 ? 'internet' : 'mobile',
                    'provider_id' => $providers[$p]->id,
                    'entry_type' => $entry instanceof EntryInternet ? 'offers.entry-internet' : 'offers.entry-mobile',
                    'entry_id' => $entry->id,
                    'secret' => 'hidden',
                ]);
            }
        }

        Offer::create(['name' => 'orphan', 'provider_id' => null]);
        Offer::create(['name' => 'gamma offer', 'provider_id' => $providers[2]->id, 'duplicating_id' => 1]);

        return $providers;
    }

    #[Test]
    public function it_deduplicates_included_resources_across_the_page(): void
    {
        $this->seedOffers();

        $response = $this->getJson('/api/v2/offers?include=provider&page[size]=100')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.api+json');

        $this->assertCount(10, $response->json('data'));
        $this->assertSame(
            ['providers:1', 'providers:2', 'providers:3'],
            collect($response->json('included'))->map(fn ($i) => "{$i['type']}:{$i['id']}")->sort()->values()->all(),
        );
        $this->assertSame('Alpha', $response->json('included.0.attributes.name'));
        $this->assertSame(['blurb' => str_repeat('a', 50)], $response->json('included.0.attributes.data'));
        $this->assertSame(['id' => '1', 'type' => 'providers'], $response->json('data.0.relationships.provider.data'));
    }

    #[Test]
    public function belongs_to_identifiers_are_present_without_include(): void
    {
        $this->seedOffers();

        $response = $this->getJson('/api/v2/offers?page[size]=100')->assertOk();

        $first = $response->json('data.0');
        $this->assertSame('offers', $first['type']);
        $this->assertSame('1', $first['id']);
        $this->assertSame(['id' => '1', 'type' => 'providers'], $first['relationships']['provider']['data']);
        $this->assertSame(['id' => '1', 'type' => 'mobiles'], $first['relationships']['entry']['data']);
        $this->assertArrayNotHasKey('duplicating', $first['relationships'], 'not a declared include');
        $this->assertArrayNotHasKey('included', $response->json());
    }

    #[Test]
    public function relation_columns_are_not_attributes(): void
    {
        $this->seedOffers();

        $attributes = $this->getJson('/api/v2/offers?include=provider,entry')->json('data.0.attributes');

        foreach (['id', 'provider_id', 'entry_id', 'entry_type', 'secret', 'provider', 'entry'] as $key) {
            $this->assertArrayNotHasKey($key, $attributes);
        }

        $this->assertSame(['name', 'type', 'duplicating_id', 'created_at', 'updated_at'], array_keys($attributes));
    }

    #[Test]
    public function a_null_foreign_key_gives_null_data(): void
    {
        $this->seedOffers();

        $orphan = fn (string $query) => collect($this->getJson("/api/v2/offers?page[size]=100{$query}")->json('data'))
            ->firstWhere('attributes.name', 'orphan');

        $this->assertSame(['data' => null], $orphan('')['relationships']['provider']);
        $this->assertSame(['data' => null], $orphan('')['relationships']['entry']);
        $this->assertSame(['data' => null], $orphan('&include=provider,entry')['relationships']['provider']);
        $this->assertSame(['data' => null], $orphan('&include=provider,entry')['relationships']['entry']);
    }

    #[Test]
    public function morph_types_come_from_the_registered_key_then_the_morph_alias(): void
    {
        $this->seedOffers();

        $response = $this->getJson('/api/v2/offers?include=entry&page[size]=2')->assertOk();

        $this->assertSame(['id' => '1', 'type' => 'mobiles'], $response->json('data.0.relationships.entry.data'));
        $this->assertSame(['id' => '1', 'type' => 'offers-entry-internet'], $response->json('data.1.relationships.entry.data'));
        $this->assertSame(['data' => ['id' => '1', 'type' => 'mobiles']], $response->json('data.0.relationships.entry'));
        $this->assertEqualsCanonicalizing(
            [['mobiles', '1', 0], ['offers-entry-internet', '1', 100]],
            collect($response->json('included'))
                ->map(fn ($i) => [$i['type'], $i['id'], $i['attributes']['data_gb'] ?? $i['attributes']['speed']])
                ->all(),
        );
    }

    #[Test]
    public function eager_loaded_relations_of_included_resources_are_not_included(): void
    {
        $this->seedOffers();

        // EntryMobile eager loads its `parent` offer through `$with`.
        $response = $this->getJson('/api/v2/offers?include=entry&page[size]=1')->assertOk();

        $this->assertSame([['mobiles', '1']], collect($response->json('included'))->map(fn ($i) => [$i['type'], $i['id']])->all());
        $this->assertArrayNotHasKey('relationships', $response->json('included.0'));

        // Not an allowed include path of offers: dropped whole, as Spatie loaded nothing for it.
        $response = $this->getJson('/api/v2/offers?include=entry.parent&page[size]=1')->assertOk();

        $this->assertArrayNotHasKey('included', $response->json());
        $this->assertSame(['data' => ['id' => '1', 'type' => 'mobiles']], $response->json('data.0.relationships.entry'));
    }

    #[Test]
    public function primary_resources_are_never_repeated_in_included(): void
    {
        $this->seedOffers();

        // offers 1 and 3 are on the page and also reached again through entry.parent
        $response = $this->getJson('/api/v2/providers/1?include=offers.entry.parent')->assertOk();
        $this->assertSame([], collect($response->json('included'))->where('type', 'providers')->all());

        $response = $this->resourceWithParentInclude();

        $included = collect($response->json('included'))->map(fn ($i) => "{$i['type']}:{$i['id']}")->sort()->values()->all();
        $this->assertSame(['mobiles:1', 'mobiles:2', 'offers-entry-internet:1'], $included);
        $this->assertSame(['data' => ['id' => '1', 'type' => 'offers']], $response->json('included.0.relationships.parent'));

    }

    /** The builders don't allow `entry.parent`, so serialize directly, as a custom endpoint would. */
    protected function resourceWithParentInclude(): \Illuminate\Testing\TestResponse
    {
        \Illuminate\Support\Facades\Route::get('direct', fn () => \SnowDigital\JsonApi\V2\JsonApiResource::collection(Offer::with('entry')->paginate(3)));

        return $this->getJson('/direct?include=entry.parent')->assertOk();
    }

    #[Test]
    public function includes_the_builder_does_not_allow_are_not_lazy_loaded(): void
    {
        $this->seedOffers();

        DB::enableQueryLog();
        $response = $this->getJson('/api/v2/offers?include=provider.offers,duplicating,provider&page[size]=100')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(3, $queries, 'count + page + providers');
        $this->assertSame(['providers'], collect($response->json('included'))->pluck('type')->unique()->values()->all());
        $this->assertArrayNotHasKey('offers', $response->json('included.0.relationships') ?? []);
    }

    #[Test]
    public function a_pure_morph_to_uses_the_morph_type_column(): void
    {
        [$alpha] = $this->seedOffers();
        Rating::create(['rateable_type' => 'providers.provider', 'rateable_id' => $alpha->id, 'score' => 4]);
        Rating::create(['rateable_type' => 'offers.offer', 'rateable_id' => 3, 'score' => 2]);

        $response = $this->getJson('/api/v2/ratings')->assertOk();

        $this->assertSame(['id' => (string) $alpha->id, 'type' => 'providers'], $response->json('data.0.relationships.rateable.data'));
        $this->assertSame(['id' => '3', 'type' => 'offers'], $response->json('data.1.relationships.rateable.data'));
        $this->assertSame(['score', 'created_at', 'updated_at'], array_keys($response->json('data.0.attributes')));
    }

    #[Test]
    public function nested_includes_resolve_through_included_resources(): void
    {
        $this->seedOffers(2);

        $response = $this->getJson('/api/v2/providers?include=offers.entry&page[size]=2')->assertOk();

        $this->assertSame(
            [['id' => '1', 'type' => 'offers'], ['id' => '2', 'type' => 'offers']],
            $response->json('data.0.relationships.offers.data'),
        );
        $this->assertArrayNotHasKey('ratings', $response->json('data.0.relationships'), 'hasMany only when included');

        $included = collect($response->json('included'))->keyBy(fn ($i) => "{$i['type']}:{$i['id']}");

        $this->assertEqualsCanonicalizing(
            ['offers:1', 'offers:2', 'offers:3', 'offers:4', 'mobiles:1', 'offers-entry-internet:1', 'mobiles:2', 'offers-entry-internet:2'],
            $included->keys()->all(),
        );
        $this->assertSame(['id' => '1', 'type' => 'providers'], $included['offers:1']['relationships']['provider']['data']);
        $this->assertSame(['id' => '1', 'type' => 'mobiles'], $included['offers:1']['relationships']['entry']['data']);
        $this->assertArrayNotHasKey('provider_id', $included['offers:1']['attributes']);
    }

    #[Test]
    public function sparse_fieldsets_apply_per_type_and_keep_identifiers(): void
    {
        $this->seedOffers();

        $response = $this->getJson('/api/v2/offers?fields[offers]=name&include=provider&fields[providers]=name')->assertOk();

        $this->assertSame(['name' => 'offer 0-0'], $response->json('data.0.attributes'));
        $this->assertSame(['id' => '1', 'type' => 'providers'], $response->json('data.0.relationships.provider.data'));
        $this->assertSame(['name' => 'Alpha'], $response->json('included.0.attributes'));

        $response = $this->getJson('/api/v2/offers?fields[offers]=name')->assertOk();

        $this->assertSame(['name' => 'offer 0-0'], $response->json('data.0.attributes'));
        $this->assertSame(['id' => '1', 'type' => 'providers'], $response->json('data.0.relationships.provider.data'));
        $this->assertSame(['id' => '1', 'type' => 'mobiles'], $response->json('data.0.relationships.entry.data'));
    }

    #[Test]
    public function serializing_adds_no_query_per_row(): void
    {
        $this->seedOffers(10);

        $count = function (string $url): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($url)->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        // count + page
        $this->assertSame(2, $count('/api/v2/offers?page[size]=5'));
        $this->assertSame(2, $count('/api/v2/offers?page[size]=22'));
        // + providers + one per entry type + the mobile entries' `$with` parent
        $this->assertSame(6, $count('/api/v2/offers?include=provider,entry&page[size]=5'));
        $this->assertSame(6, $count('/api/v2/offers?include=provider,entry&page[size]=22'));
        // count + page + offers + one per entry type + `$with` parent
        $this->assertSame(6, $count('/api/v2/providers?include=offers.entry'));
    }

    #[Test]
    public function only_the_requested_builder_is_constructed(): void
    {
        $this->seedOffers();
        Schema::flush();

        $response = $this->getJson('/api/v2/offers?include=provider')->assertOk();

        $this->assertSame(0, ProviderQueryBuilder::$constructed);
        $this->assertSame('offers', $response->json('data.0.type'), 'remembered from the route key');
        $this->assertSame('providers-provider', $response->json('included.0.type'), 'a builder-registered model needs Schema::types()');
    }

    #[Test]
    public function show_returns_one_resource_with_its_includes(): void
    {
        $this->seedOffers();

        $response = $this->getJson('/api/v2/offers/2?include=provider,entry')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.api+json');

        $this->assertSame('2', $response->json('data.id'));
        $this->assertSame(['id' => '1', 'type' => 'providers'], $response->json('data.relationships.provider.data'));
        $this->assertSame(['id' => '1', 'type' => 'offers-entry-internet'], $response->json('data.relationships.entry.data'));
        $this->assertCount(2, $response->json('included'));

        $this->getJson('/api/v2/offers/999')->assertNotFound();
    }

    #[Test]
    public function show_by_slug_like_lookups_can_reuse_the_resource(): void
    {
        $this->seedOffers();

        $provider = Provider::where('slug', 'beta')->firstOrFail();
        $data = (new \SnowDigital\JsonApi\V2\JsonApiResource($provider))->resolve(request());

        $this->assertSame(['id' => '2', 'type' => 'providers'], array_intersect_key($data['data'], ['id' => 1, 'type' => 1]));
    }

    #[Test]
    public function append_and_filters_still_work(): void
    {
        $this->seedOffers();

        $response = $this->getJson('/api/v2/offers?append=label&filter[provider.slug]=beta&page[size]=100')->assertOk();

        $this->assertCount(4, $response->json('data'));
        $this->assertSame('OFFER 1-0', $response->json('data.0.attributes.label'));
    }

    #[Test]
    public function it_paginates_and_fires_after_index(): void
    {
        $this->seedOffers();

        Event::listen(AfterIndex::class, function (AfterIndex $event) {
            $this->assertInstanceOf(JsonApiCollection::class, $event->collection);
            $event->collection->additional(['meta' => ['resource' => $event->resource]]);
        });

        $response = $this->getJson('/api/v2/offers?page[size]=3&page[number]=2')->assertOk();

        $this->assertCount(3, $response->json('data'));
        $this->assertSame('4', $response->json('data.0.id'));
        $this->assertSame(10, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.current_page'));
        $this->assertSame('offers', $response->json('meta.resource'));
        $this->assertStringContainsString('page%5Bnumber%5D=3', $response->json('links.next'));
    }
}
