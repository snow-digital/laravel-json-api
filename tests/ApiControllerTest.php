<?php

namespace SnowDigital\JsonApi\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use SnowDigital\JsonApi\Events\AfterIndex;
use SnowDigital\JsonApi\Tests\Fixtures\Article;
use SnowDigital\JsonApi\Tests\Fixtures\Video;
use SnowDigital\JsonApi\Tests\Fixtures\Post;
use SnowDigital\JsonApi\Tests\Fixtures\Author;
use SnowDigital\JsonApi\Tests\Fixtures\AuthorQueryBuilder;
use SnowDigital\JsonApi\Tests\Fixtures\Comment;
use SnowDigital\JsonApi\Facades\JsonApi;
use SnowDigital\JsonApi\Resources\CompoundDocument;
use SnowDigital\JsonApi\Resources\ResourceObject;

class ApiControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        JsonApi::types([
            Post::class => 'posts',
            Author::class => 'authors',
            Comment::class => 'comments',
        ]);
    }

    #[Test]
    public function attributes_resources_nest_relations_inside_attributes(): void
    {
        $author = Author::create(['name' => 'Alpha', 'slug' => 'alpha']);
        $content = Video::create(['seconds' => 5]);
        Post::create(['name' => 'one', 'author_id' => $author->id, 'content_type' => 'posts.content-video', 'content_id' => $content->id]);

        $response = $this->getJson('/api/v1/posts?include=author,content')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json');

        $this->assertSame(['id', 'attributes'], array_keys($response->json('data.0')));
        $this->assertSame(1, $response->json('data.0.attributes.id'));
        $this->assertSame(1, $response->json('data.0.attributes.author_id'));
        $this->assertSame('Alpha', $response->json('data.0.attributes.author.name'));
        $this->assertSame(5, $response->json('data.0.attributes.content.seconds'));
        $this->assertArrayNotHasKey('included', $response->json());

        $this->getJson('/api/v1/posts/1')
            ->assertOk()
            ->assertJsonPath('data.id', '1')
            ->assertJsonPath('data.attributes.name', 'one');
    }

    #[Test]
    public function a_reused_controller_serves_the_resource_of_each_request(): void
    {
        Author::create(['name' => 'Alpha', 'slug' => 'alpha']);
        Post::create(['name' => 'one']);

        // One `{resource}` route keeps one controller instance across requests.
        $this->getJson('/api/v1/posts')->assertOk()->assertJsonPath('data.0.attributes.name', 'one');
        $this->getJson('/api/v1/authors')->assertOk()->assertJsonPath('data.0.attributes.name', 'Alpha');
        $this->getJson('/api/v1/posts/1')->assertOk()->assertJsonPath('data.attributes.name', 'one');
        $this->getJson('/api/v2/authors/1')->assertOk()->assertJsonPath('data.type', 'authors');
        $this->getJson('/api/v2/posts/1')->assertOk()->assertJsonPath('data.type', 'posts');
    }

    /**
     * 2 authors sharing 8 posts, 1 post without author, 1 post of a third author.
     *
     * @return array{0: Author, 1: Author, 2: Author}
     */
    protected function seedPosts(int $perAuthor = 4): array
    {
        $authors = [
            Author::create(['name' => 'Alpha', 'slug' => 'alpha', 'data' => ['blurb' => str_repeat('a', 50)]]),
            Author::create(['name' => 'Beta', 'slug' => 'beta', 'data' => ['blurb' => 'b']]),
            Author::create(['name' => 'Gamma', 'slug' => 'gamma']),
        ];

        foreach ([0, 1] as $p) {
            for ($i = 0; $i < $perAuthor; $i++) {
                $content = $i % 2
                    ? Article::create(['words' => 100 * $i])
                    : Video::create(['seconds' => 10 * $i]);

                Post::create([
                    'name' => "post {$p}-{$i}",
                    'type' => $i % 2 ? 'article' : 'video',
                    'author_id' => $authors[$p]->id,
                    'content_type' => $content instanceof Article ? 'posts.content-article' : 'posts.content-video',
                    'content_id' => $content->id,
                    'secret' => 'hidden',
                ]);
            }
        }

        Post::create(['name' => 'orphan', 'author_id' => null]);
        Post::create(['name' => 'gamma post', 'author_id' => $authors[2]->id, 'original_id' => 1]);

        return $authors;
    }

    #[Test]
    public function it_deduplicates_included_resources_across_the_page(): void
    {
        $this->seedPosts();

        $response = $this->getJson('/api/v2/posts?include=author&page[size]=100')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.api+json');

        $this->assertCount(10, $response->json('data'));
        $this->assertSame(
            ['authors:1', 'authors:2', 'authors:3'],
            collect($response->json('included'))->map(fn ($i) => "{$i['type']}:{$i['id']}")->sort()->values()->all(),
        );
        $this->assertSame('Alpha', $response->json('included.0.attributes.name'));
        $this->assertSame(['blurb' => str_repeat('a', 50)], $response->json('included.0.attributes.data'));
        $this->assertSame(['id' => '1', 'type' => 'authors'], $response->json('data.0.relationships.author.data'));
    }

    #[Test]
    public function belongs_to_identifiers_are_present_without_include(): void
    {
        $this->seedPosts();

        $response = $this->getJson('/api/v2/posts?page[size]=100')->assertOk();

        $first = $response->json('data.0');
        $this->assertSame('posts', $first['type']);
        $this->assertSame('1', $first['id']);
        $this->assertSame(['id' => '1', 'type' => 'authors'], $first['relationships']['author']['data']);
        $this->assertSame(['id' => '1', 'type' => 'videos'], $first['relationships']['content']['data']);
        $this->assertArrayNotHasKey('original', $first['relationships'], 'not a declared include');
        $this->assertArrayNotHasKey('included', $response->json());
    }

    #[Test]
    public function relation_columns_are_not_attributes(): void
    {
        $this->seedPosts();

        $attributes = $this->getJson('/api/v2/posts?include=author,content')->json('data.0.attributes');

        foreach (['id', 'author_id', 'content_id', 'content_type', 'secret', 'author', 'content'] as $key) {
            $this->assertArrayNotHasKey($key, $attributes);
        }

        $this->assertSame(['name', 'type', 'original_id', 'created_at', 'updated_at'], array_keys($attributes));
    }

    #[Test]
    public function a_null_foreign_key_gives_null_data(): void
    {
        $this->seedPosts();

        $orphan = fn (string $query) => collect($this->getJson("/api/v2/posts?page[size]=100{$query}")->json('data'))
            ->firstWhere('attributes.name', 'orphan');

        $this->assertSame(['data' => null], $orphan('')['relationships']['author']);
        $this->assertSame(['data' => null], $orphan('')['relationships']['content']);
        $this->assertSame(['data' => null], $orphan('&include=author,content')['relationships']['author']);
        $this->assertSame(['data' => null], $orphan('&include=author,content')['relationships']['content']);
    }

    #[Test]
    public function morph_types_come_from_the_registered_key_then_the_morph_alias(): void
    {
        $this->seedPosts();

        $response = $this->getJson('/api/v2/posts?include=content&page[size]=2')->assertOk();

        $this->assertSame(['id' => '1', 'type' => 'videos'], $response->json('data.0.relationships.content.data'));
        $this->assertSame(['id' => '1', 'type' => 'posts-content-article'], $response->json('data.1.relationships.content.data'));
        $this->assertSame(['data' => ['id' => '1', 'type' => 'videos']], $response->json('data.0.relationships.content'));
        $this->assertEqualsCanonicalizing(
            [['videos', '1', 0], ['posts-content-article', '1', 100]],
            collect($response->json('included'))
                ->map(fn ($i) => [$i['type'], $i['id'], $i['attributes']['seconds'] ?? $i['attributes']['words']])
                ->all(),
        );
    }

    #[Test]
    public function eager_loaded_relations_of_included_resources_are_not_included(): void
    {
        $this->seedPosts();

        // Video eager loads its `post` through `$with`.
        $response = $this->getJson('/api/v2/posts?include=content&page[size]=1')->assertOk();

        $this->assertSame([['videos', '1']], collect($response->json('included'))->map(fn ($i) => [$i['type'], $i['id']])->all());
        $this->assertArrayNotHasKey('relationships', $response->json('included.0'));

        // Not an allowed include path: dropped.
        $response = $this->getJson('/api/v2/posts?include=content.post&page[size]=1')->assertOk();

        $this->assertArrayNotHasKey('included', $response->json());
        $this->assertSame(['data' => ['id' => '1', 'type' => 'videos']], $response->json('data.0.relationships.content'));
    }

    #[Test]
    public function primary_resources_are_never_repeated_in_included(): void
    {
        $this->seedPosts();

        // posts 1 and 3 are on the page and also reached again through content.post
        $response = $this->getJson('/api/v2/authors/1?include=posts.content.post')->assertOk();
        $this->assertSame([], collect($response->json('included'))->where('type', 'authors')->all());

        $response = $this->resourceWithOwnerInclude();

        $included = collect($response->json('included'))->map(fn ($i) => "{$i['type']}:{$i['id']}")->sort()->values()->all();
        $this->assertSame(['posts-content-article:1', 'videos:1', 'videos:2'], $included);
        $this->assertSame(['data' => ['id' => '1', 'type' => 'posts']], $response->json('included.0.relationships.post'));

    }

    /** The builders don't allow `content.post`, so serialize directly, as a custom endpoint would. */
    protected function resourceWithOwnerInclude(): \Illuminate\Testing\TestResponse
    {
        \Illuminate\Support\Facades\Route::get('direct', fn () => ResourceObject::collection(Post::with('content')->paginate(3)));

        return $this->getJson('/direct?include=content.post')->assertOk();
    }

    #[Test]
    public function includes_the_builder_does_not_allow_are_not_lazy_loaded(): void
    {
        $this->seedPosts();

        DB::enableQueryLog();
        $response = $this->getJson('/api/v2/posts?include=author.posts,original,author&page[size]=100')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(3, $queries, 'count + page + authors');
        $this->assertSame(['authors'], collect($response->json('included'))->pluck('type')->unique()->values()->all());
        $this->assertArrayNotHasKey('posts', $response->json('included.0.relationships') ?? []);
    }

    #[Test]
    public function a_pure_morph_to_uses_the_morph_type_column(): void
    {
        [$alpha] = $this->seedPosts();
        Comment::create(['commentable_type' => 'authors.author', 'commentable_id' => $alpha->id, 'score' => 4]);
        Comment::create(['commentable_type' => 'posts.post', 'commentable_id' => 3, 'score' => 2]);

        $response = $this->getJson('/api/v2/comments')->assertOk();

        $this->assertSame(['id' => (string) $alpha->id, 'type' => 'authors'], $response->json('data.0.relationships.commentable.data'));
        $this->assertSame(['id' => '3', 'type' => 'posts'], $response->json('data.1.relationships.commentable.data'));
        $this->assertSame(['score', 'created_at', 'updated_at'], array_keys($response->json('data.0.attributes')));
    }

    #[Test]
    public function nested_includes_resolve_through_included_resources(): void
    {
        $this->seedPosts(2);

        $response = $this->getJson('/api/v2/authors?include=posts.content&page[size]=2')->assertOk();

        $this->assertSame(
            [['id' => '1', 'type' => 'posts'], ['id' => '2', 'type' => 'posts']],
            $response->json('data.0.relationships.posts.data'),
        );
        $this->assertArrayNotHasKey('comments', $response->json('data.0.relationships'), 'hasMany only when included');

        $included = collect($response->json('included'))->keyBy(fn ($i) => "{$i['type']}:{$i['id']}");

        $this->assertEqualsCanonicalizing(
            ['posts:1', 'posts:2', 'posts:3', 'posts:4', 'videos:1', 'posts-content-article:1', 'videos:2', 'posts-content-article:2'],
            $included->keys()->all(),
        );
        $this->assertSame(['id' => '1', 'type' => 'authors'], $included['posts:1']['relationships']['author']['data']);
        $this->assertSame(['id' => '1', 'type' => 'videos'], $included['posts:1']['relationships']['content']['data']);
        $this->assertArrayNotHasKey('author_id', $included['posts:1']['attributes']);
    }

    #[Test]
    public function sparse_fieldsets_apply_per_type_and_keep_identifiers(): void
    {
        $this->seedPosts();

        $response = $this->getJson('/api/v2/posts?fields[posts]=name&include=author&fields[authors]=name')->assertOk();

        $this->assertSame(['name' => 'post 0-0'], $response->json('data.0.attributes'));
        $this->assertSame(['id' => '1', 'type' => 'authors'], $response->json('data.0.relationships.author.data'));
        $this->assertSame(['name' => 'Alpha'], $response->json('included.0.attributes'));

        $response = $this->getJson('/api/v2/posts?fields[posts]=name')->assertOk();

        $this->assertSame(['name' => 'post 0-0'], $response->json('data.0.attributes'));
        $this->assertSame(['id' => '1', 'type' => 'authors'], $response->json('data.0.relationships.author.data'));
        $this->assertSame(['id' => '1', 'type' => 'videos'], $response->json('data.0.relationships.content.data'));
    }

    #[Test]
    public function serializing_adds_no_query_per_row(): void
    {
        $this->seedPosts(10);

        $count = function (string $url): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($url)->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        // count + page
        $this->assertSame(2, $count('/api/v2/posts?page[size]=5'));
        $this->assertSame(2, $count('/api/v2/posts?page[size]=22'));
        // + authors + one per content type + the videos' `$with` post
        $this->assertSame(6, $count('/api/v2/posts?include=author,content&page[size]=5'));
        $this->assertSame(6, $count('/api/v2/posts?include=author,content&page[size]=22'));
        // count + page + posts + one per content type + `$with` post
        $this->assertSame(6, $count('/api/v2/authors?include=posts.content'));
    }

    #[Test]
    public function only_the_requested_builder_is_constructed(): void
    {
        $this->seedPosts();
        $this->flushTypes();

        $response = $this->getJson('/api/v2/posts?include=author')->assertOk();

        $this->assertSame(0, AuthorQueryBuilder::$constructed);
        $this->assertSame('posts', $response->json('data.0.type'), 'remembered from the route key');
        $this->assertSame('authors-author', $response->json('included.0.type'), 'a builder-registered model needs JsonApi::types()');
    }

    #[Test]
    public function show_returns_one_resource_with_its_includes(): void
    {
        $this->seedPosts();

        $response = $this->getJson('/api/v2/posts/2?include=author,content')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.api+json');

        $this->assertSame('2', $response->json('data.id'));
        $this->assertSame(['id' => '1', 'type' => 'authors'], $response->json('data.relationships.author.data'));
        $this->assertSame(['id' => '1', 'type' => 'posts-content-article'], $response->json('data.relationships.content.data'));
        $this->assertCount(2, $response->json('included'));

        $this->getJson('/api/v2/posts/999')->assertNotFound();
    }

    #[Test]
    public function show_by_slug_like_lookups_can_reuse_the_resource(): void
    {
        $this->seedPosts();

        $author = Author::where('slug', 'beta')->firstOrFail();
        $data = (new ResourceObject($author))->resolve(request());

        $this->assertSame(['id' => '2', 'type' => 'authors'], array_intersect_key($data['data'], ['id' => 1, 'type' => 1]));
    }

    #[Test]
    public function append_and_filters_still_work(): void
    {
        $this->seedPosts();

        $response = $this->getJson('/api/v2/posts?append=label&filter[author.slug]=beta&page[size]=100')->assertOk();

        $this->assertCount(4, $response->json('data'));
        $this->assertSame('POST 1-0', $response->json('data.0.attributes.label'));
    }

    #[Test]
    public function it_paginates_and_fires_after_index(): void
    {
        $this->seedPosts();

        Event::listen(AfterIndex::class, function (AfterIndex $event) {
            $this->assertInstanceOf(CompoundDocument::class, $event->collection);
            $event->collection->additional(['meta' => ['resource' => $event->resource]]);
        });

        $response = $this->getJson('/api/v2/posts?page[size]=3&page[number]=2')->assertOk();

        $this->assertCount(3, $response->json('data'));
        $this->assertSame('4', $response->json('data.0.id'));
        $this->assertSame(10, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.current_page'));
        $this->assertSame('posts', $response->json('meta.resource'));
        $this->assertStringContainsString('page%5Bnumber%5D=3', $response->json('links.next'));
    }
}
