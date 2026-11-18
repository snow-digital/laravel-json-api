<?php

namespace SnowDigital\JsonApi\Tests\V2;

use Illuminate\Database\Eloquent\Relations\Relation;
use PHPUnit\Framework\Attributes\Test;
use SnowDigital\JsonApi\Tests\Fixtures\EntryInternet;
use SnowDigital\JsonApi\Tests\Fixtures\EntryMobile;
use SnowDigital\JsonApi\Tests\Fixtures\Offer;
use SnowDigital\JsonApi\Tests\Fixtures\Rating;
use SnowDigital\JsonApi\Tests\TestCase;
use SnowDigital\JsonApi\V2\Schema;

class SchemaTest extends TestCase
{
    #[Test]
    public function morph_alias_types_only_use_member_name_characters(): void
    {
        Relation::morphMap(['.reviews::rating ' => Rating::class]);

        $this->assertSame('offers-entry-internet', Schema::type(EntryInternet::class));
        $this->assertSame('reviews-rating', Schema::type(Rating::class));
    }

    #[Test]
    public function explicit_and_registered_types_win_over_the_morph_alias(): void
    {
        $this->assertSame('mobiles', Schema::type(EntryMobile::class), 'registered key');
        $this->assertSame('offers-offer', Schema::type(Offer::class), 'only the alias is known');

        Schema::types([Offer::class => 'offers', EntryInternet::class => 'entry.internet']);

        $this->assertSame('offers', Schema::type(new Offer));
        $this->assertSame('entry.internet', Schema::type(EntryInternet::class), 'explicit types are used as given');
    }
}
