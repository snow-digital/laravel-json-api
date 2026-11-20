<?php

namespace SnowDigital\JsonApi\Tests\Resources;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use SnowDigital\JsonApi\Tests\Fixtures\Post;
use SnowDigital\JsonApi\Tests\Fixtures\ExtendedPostResource;
use SnowDigital\JsonApi\Tests\Fixtures\Author;
use SnowDigital\JsonApi\Tests\TestCase;
use SnowDigital\JsonApi\Facades\JsonApi;
use SnowDigital\JsonApi\Resources\CompoundDocument;

class ResourceObjectTest extends TestCase
{
    #[Test]
    public function a_custom_resource_keeps_types_identifiers_and_dedup(): void
    {
        JsonApi::types([Post::class => 'posts', Author::class => 'authors']);

        $author = Author::create(['name' => 'Alpha', 'slug' => 'alpha']);
        Post::create(['name' => 'one', 'author_id' => $author->id]);
        Post::create(['name' => 'two', 'author_id' => $author->id]);

        Route::get('results', function () {
            $collection = ExtendedPostResource::collection(Post::with('author')->paginate(10));
            $this->assertInstanceOf(CompoundDocument::class, $collection);

            return $collection;
        });

        $response = $this->getJson('/results?include=author')->assertOk();

        $this->assertSame('posts', $response->json('data.1.type'));
        $this->assertSame(
            ['name' => 'two', 'type' => 'article', 'original_id' => null, 'created_at' => $response->json('data.1.attributes.created_at'), 'updated_at' => $response->json('data.1.attributes.updated_at'), 'computed' => 'two!'],
            $response->json('data.1.attributes'),
        );
        $this->assertCount(1, $response->json('included'));
        $this->assertArrayNotHasKey('computed', $response->json('included.0.attributes'), 'included models use the base resource');

        $response = $this->getJson('/results')->assertOk();

        $this->assertSame(['id' => '1', 'type' => 'authors'], $response->json('data.0.relationships.author.data'), 'loaded, not included');
        $this->assertArrayNotHasKey('included', $response->json());
    }
}
