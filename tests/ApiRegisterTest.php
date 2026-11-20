<?php

namespace SnowDigital\JsonApi\Tests;

use Illuminate\Database\Eloquent\Relations\Relation;
use PHPUnit\Framework\Attributes\Test;
use SnowDigital\JsonApi\Tests\Fixtures\EntryInternet;
use SnowDigital\JsonApi\Tests\Fixtures\EntryMobile;
use SnowDigital\JsonApi\Tests\Fixtures\Offer;
use SnowDigital\JsonApi\Tests\Fixtures\Rating;
use SnowDigital\JsonApi\Facades\JsonApi;

class ApiRegisterTest extends TestCase
{
    #[Test]
    public function morph_alias_types_only_use_member_name_characters(): void
    {
        Relation::morphMap(['.reviews::rating ' => Rating::class]);

        $this->assertSame('offers-entry-internet', JsonApi::type(EntryInternet::class));
        $this->assertSame('reviews-rating', JsonApi::type(Rating::class));
    }

    #[Test]
    public function explicit_and_registered_types_win_over_the_morph_alias(): void
    {
        $this->assertSame('mobiles', JsonApi::type(EntryMobile::class), 'registered key');
        $this->assertSame('offers-offer', JsonApi::type(Offer::class), 'only the alias is known');

        JsonApi::types([Offer::class => 'offers', EntryInternet::class => 'entry.internet']);

        $this->assertSame('offers', JsonApi::type(new Offer));
        $this->assertSame('entry.internet', JsonApi::type(EntryInternet::class), 'explicit types are used as given');
    }
}
