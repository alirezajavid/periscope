<?php

use App\Models\Post;
use App\Models\Subscription;
use App\Models\Tag;
use App\Models\User;

const SECRET_BODY = '<p>The secret premium analysis nobody else sees.</p>';

function premiumPost(array $attributes = []): Post
{
    return Post::factory()->withAudio()->create(array_merge([
        'title' => 'Episode 145',
        'excerpt' => 'Public teaser.',
        'body' => SECRET_BODY,
        'embed_code' => '<iframe src="https://www.aparat.com/video/video/embed/videohash/abc/vt/frame"></iframe>',
        'access' => 'premium',
    ], $attributes));
}

function subscriber(): User
{
    $user = User::factory()->create();
    Subscription::factory()->for($user)->create();

    return $user;
}

it('shows a non-subscriber only the preview of a premium post', function (?User $viewer) {
    $post = premiumPost();

    $response = ($viewer ? asUser($viewer) : $this)->getJson("/api/posts/{$post->id}")->assertOk();

    $response->assertJsonPath('data.title', 'Episode 145')
        ->assertJsonPath('data.excerpt', 'Public teaser.')
        ->assertJsonPath('data.locked', true)
        ->assertJsonMissingPath('data.body')
        ->assertJsonMissingPath('data.embed_url');
    expect($response->getContent())->not->toContain('secret premium')->not->toContain('audio/original')->not->toContain('aparat');
})->with([
    'anonymous' => [fn () => null],
    'logged in without subscription' => [fn () => User::factory()->create()],
    'expired subscription' => [fn () => tap(User::factory()->create(), fn ($u) => Subscription::factory()->for($u)->expired()->create())],
]);

it('gives subscribers and admins the full content', function (User $viewer) {
    $post = premiumPost();

    asUser($viewer)->getJson("/api/posts/{$post->id}")->assertOk()
        ->assertJsonPath('data.locked', false)
        ->assertJsonPath('data.body', SECRET_BODY)
        ->assertJsonPath('data.embed_url', 'https://www.aparat.com/video/video/embed/videohash/abc/vt/frame');
})->with([
    'subscriber' => [fn () => subscriber()],
    'admin' => [fn () => User::factory()->admin()->create()],
]);

it('never puts an audio URL or path in a post response, for anyone', function () {
    $post = premiumPost();

    $json = asUser(subscriber())->getJson("/api/posts/{$post->id}")->assertOk()->assertJsonPath('data.has_audio', true)->getContent();

    expect($json)->not->toContain('audio_path')->not->toContain('audio/original')->not->toContain('.mp3');
});

it('gives everyone the body of a free post', function () {
    $post = premiumPost(['access' => 'free']);

    $this->getJson("/api/posts/{$post->id}")->assertOk()->assertJsonPath('data.locked', false)->assertJsonPath('data.body', SECRET_BODY);
});

it('hides drafts and scheduled posts from the public API', function () {
    $draft = Post::factory()->draft()->create();
    $scheduled = Post::factory()->scheduled()->create();
    $live = Post::factory()->create();

    $this->getJson("/api/posts/{$draft->id}")->assertNotFound();
    $this->getJson("/api/posts/{$scheduled->id}")->assertNotFound();
    $this->getJson('/api/posts')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $live->id);

    $this->travel(2)->days();
    $this->getJson("/api/posts/{$scheduled->id}")->assertOk();
});

it('hides soft-deleted posts', function () {
    $post = Post::factory()->create();
    $post->delete();

    $this->getJson("/api/posts/{$post->id}")->assertNotFound();
});

it('lists posts newest first with locked flags', function () {
    $old = Post::factory()->create(['published_at' => now()->subDays(3), 'access' => 'free']);
    $new = Post::factory()->create(['published_at' => now()->subDay()]);

    $response = $this->getJson('/api/posts')->assertOk();

    expect($response->json('data.*.id'))->toBe([$new->id, $old->id])
        ->and($response->json('data.*.locked'))->toBe([true, false]);
    expect($response->getContent())->not->toContain('"body":"<p>'.strip_tags($new->body)); // premium body absent from the list
});

it('searches titles, excerpts and hashtags for everyone', function () {
    $post = premiumPost(['title' => 'Geopolitics of Germany']);
    $post->tags()->attach(Tag::create(['name' => 'Berlin']));
    Post::factory()->create(['title' => 'Something else']);

    $this->getJson('/api/posts?q=Germany')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/posts?q=Berlin')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/posts?tag=Berlin')->assertOk()->assertJsonCount(1, 'data');
});

it('searches the body only where the reader may read it', function () {
    premiumPost(['body' => '<p>needle in premium text</p>']);
    Post::factory()->free()->create(['body' => '<p>needle in free text</p>']);

    $this->getJson('/api/posts?q=needle')->assertOk()->assertJsonCount(1, 'data');
    asUser(User::factory()->create())->getJson('/api/posts?q=needle')->assertOk()->assertJsonCount(1, 'data');
    asUser(subscriber())->getJson('/api/posts?q=needle')->assertOk()->assertJsonCount(2, 'data');
});

it('treats LIKE wildcards in a search as plain characters', function () {
    Post::factory()->create(['title' => 'Plain title']);

    $this->getJson('/api/posts?q=%25')->assertOk()->assertJsonCount(0, 'data');
});

it('lists hashtags that have visible posts', function () {
    $tag = Tag::create(['name' => 'Iran']);
    Post::factory()->create()->tags()->attach($tag);
    Tag::create(['name' => 'Unused']);
    Post::factory()->draft()->create()->tags()->attach(Tag::create(['name' => 'OnlyDraft']));

    $this->getJson('/api/tags')->assertOk()->assertExactJson(['data' => [['name' => 'Iran', 'posts_count' => 1]]]);
});

it('does not show blocked users anything', function () {
    $post = Post::factory()->free()->create();

    asUser(User::factory()->blocked()->create())->getJson("/api/posts/{$post->id}")->assertForbidden();
});
