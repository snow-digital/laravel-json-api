<?php

namespace SnowDigital\JsonApi\Tests;

use Illuminate\Database\Eloquent\Relations\Relation;
use PHPUnit\Framework\Attributes\Test;
use SnowDigital\JsonApi\Tests\Fixtures\Article;
use SnowDigital\JsonApi\Tests\Fixtures\Video;
use SnowDigital\JsonApi\Tests\Fixtures\Post;
use SnowDigital\JsonApi\Tests\Fixtures\Comment;
use SnowDigital\JsonApi\Facades\JsonApi;

class ApiRegisterTest extends TestCase
{
    #[Test]
    public function morph_alias_types_only_use_member_name_characters(): void
    {
        Relation::morphMap(['.feedback::comment ' => Comment::class]);

        $this->assertSame('posts-content-article', JsonApi::type(Article::class));
        $this->assertSame('feedback-comment', JsonApi::type(Comment::class));
    }

    #[Test]
    public function explicit_and_registered_types_win_over_the_morph_alias(): void
    {
        $this->assertSame('videos', JsonApi::type(Video::class), 'registered key');
        $this->assertSame('posts-post', JsonApi::type(Post::class), 'only the alias is known');

        JsonApi::types([Post::class => 'posts', Article::class => 'content.article']);

        $this->assertSame('posts', JsonApi::type(new Post));
        $this->assertSame('content.article', JsonApi::type(Article::class), 'explicit types are used as given');
    }
}
