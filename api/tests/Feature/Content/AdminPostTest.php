<?php

use App\Enums\ReactionType;
use App\Jobs\MirrorFileToForeignHost;
use App\Models\AdminLog;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Support\EmbedUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function postPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Episode 146',
        'excerpt' => 'Teaser',
        'body' => '<p>Body</p>',
        'access' => 'premium',
        'status' => 'draft',
        'tags' => ['Iran', 'Geopolitics'],
    ], $overrides);
}

it('keeps non-admins out of the post admin', function () {
    $this->getJson('/api/admin/posts')->assertUnauthorized();
    asUser()->getJson('/api/admin/posts')->assertForbidden();
    asUser()->postJson('/api/admin/posts', postPayload())->assertForbidden();
});

it('creates a post with hashtags and records who did it', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();

    asAdmin($admin)->postJson('/api/admin/posts', postPayload())
        ->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonCount(2, 'data.tags');

    $post = Post::first();
    expect($post->created_by)->toBe($admin->id)
        ->and(Tag::count())->toBe(2)
        ->and(AdminLog::where('action', 'post.create')->where('target_id', $post->id)->exists())->toBeTrue();
});

it('sanitizes the body to the allowed tags', function () {
    $dirty = '<p onclick="x()">Hi <script>alert(1)</script><img src="x"><strong>bold</strong> <a href="https://example.com">link</a></p><h1>big</h1><iframe src="https://evil.test"></iframe>';

    asAdmin()->postJson('/api/admin/posts', postPayload(['body' => $dirty]))->assertCreated();

    $stored = Post::first()->body;
    expect($stored)->toContain('<strong>bold</strong>')
        ->not->toContain('script')->not->toContain('onclick')->not->toContain('<img')->not->toContain('<h1')->not->toContain('iframe');
});

it('opens links in a new tab with noopener noreferrer and drops javascript: links', function () {
    asAdmin()->postJson('/api/admin/posts', postPayload(['body' => '<p><a href="https://example.com">ok</a> <a href="javascript:alert(1)">bad</a></p>']))->assertCreated();

    $stored = Post::first()->body;
    expect($stored)->toContain('target="_blank"')->toContain('noopener')->toContain('noreferrer')->not->toContain('javascript');
});

it('never changes the Persian half-space (ZWNJ, U+200C)', function () {
    $text = "<p>می\u{200C}خواهم کتاب\u{200C}ها را بخوانم</p>";

    asAdmin()->postJson('/api/admin/posts', postPayload(['body' => $text]))->assertCreated();

    expect(Post::first()->body)->toBe($text);
});

it('only extracts embed URLs from allowed video hosts', function (string $code, ?string $expected) {
    expect(EmbedUrl::extract($code))->toBe($expected);
})->with([
    'aparat iframe' => ['<iframe src="https://www.aparat.com/video/video/embed/videohash/xyz/vt/frame"></iframe>', 'https://www.aparat.com/video/video/embed/videohash/xyz/vt/frame'],
    'plain url' => ['https://aparat.com/v/xyz', 'https://aparat.com/v/xyz'],
    'other host' => ['<iframe src="https://evil.test/embed"></iframe>', null],
    'script tag' => ['<script src="https://evil.test/x.js"></script>', null],
    'lookalike host' => ['https://aparat.com.evil.test/x', null],
    'empty' => ['', null],
]);

it('refuses to publish an empty post and defaults the publish time', function () {
    asAdmin()->postJson('/api/admin/posts', postPayload(['status' => 'published', 'body' => null]))
        ->assertUnprocessable()->assertJsonValidationErrors('status');

    asAdmin()->postJson('/api/admin/posts', postPayload(['status' => 'published']))->assertCreated();
    expect(Post::first()->published_at)->not->toBeNull();
});

it('keeps episode numbers unique', function () {
    Post::factory()->create(['episode_number' => 145]);

    asAdmin()->postJson('/api/admin/posts', postPayload(['episode_number' => 145]))
        ->assertUnprocessable()->assertJsonValidationErrors('episode_number');
});

it('updates a post and replaces its hashtags', function () {
    $post = Post::factory()->create();
    $post->syncTagNames(['old']);

    asAdmin()->putJson("/api/admin/posts/{$post->id}", postPayload(['title' => 'New title', 'status' => 'published', 'tags' => ['new']]))
        ->assertOk()->assertJsonPath('data.title', 'New title');

    expect($post->fresh()->tags->pluck('name')->all())->toBe(['new']);
});

it('soft deletes and restores', function () {
    $post = Post::factory()->create();

    asAdmin()->deleteJson("/api/admin/posts/{$post->id}")->assertNoContent();
    expect(Post::count())->toBe(0)->and(Post::withTrashed()->count())->toBe(1);

    asAdmin()->getJson('/api/admin/posts?trashed=1')->assertOk()->assertJsonCount(1, 'data');
    asAdmin()->postJson("/api/admin/posts/{$post->id}/restore")->assertOk();
    expect(Post::count())->toBe(1);
});

it('filters the admin list', function () {
    Post::factory()->create(['title' => 'Alpha']);
    Post::factory()->draft()->create(['title' => 'Beta']);
    Post::factory()->free()->create(['title' => 'Gamma']);

    asAdmin()->getJson('/api/admin/posts?status=draft')->assertOk()->assertJsonCount(1, 'data');
    asAdmin()->getJson('/api/admin/posts?access=free')->assertOk()->assertJsonCount(1, 'data');
    asAdmin()->getJson('/api/admin/posts?q=Alp')->assertOk()->assertJsonCount(1, 'data');
    asAdmin()->getJson('/api/admin/posts')->assertOk()->assertJsonCount(3, 'data');
});

it('uploads a cover to the public disk and queues the mirror copy', function () {
    Storage::fake('public');
    Queue::fake();
    $post = Post::factory()->create();

    asAdmin()->postJson("/api/admin/posts/{$post->id}/cover", ['cover' => UploadedFile::fake()->image('c.jpg')])->assertOk();

    Storage::disk('public')->assertExists($post->fresh()->cover_path);
    Queue::assertPushed(MirrorFileToForeignHost::class, fn ($job) => $job->path === $post->fresh()->cover_path);
});

it('uploads audio to the private disk, resets derived data and replaces the old file', function () {
    Storage::fake('local');
    Queue::fake();
    Storage::disk('local')->put('audio/original/old.mp3', 'old');
    $post = Post::factory()->withAudio('audio/original/old.mp3')->create(['audio_duration' => 99]);

    asAdmin()->postJson("/api/admin/posts/{$post->id}/audio", ['audio' => UploadedFile::fake()->create('ep.mp3', 500, 'audio/mpeg')])
        ->assertOk()->assertJsonPath('data.has_audio', true)->assertJsonPath('data.audio_duration', null);

    $post->refresh();
    Storage::disk('local')->assertExists($post->audio_path);
    Storage::disk('local')->assertMissing('audio/original/old.mp3');
    expect($post->audio_path)->toStartWith('audio/original/');
    Queue::assertPushed(MirrorFileToForeignHost::class, fn ($job) => $job->postId === $post->id);
});

it('rejects non-audio uploads', function () {
    $post = Post::factory()->create();

    asAdmin()->postJson("/api/admin/posts/{$post->id}/audio", ['audio' => UploadedFile::fake()->create('x.exe', 10, 'application/x-msdownload')])
        ->assertUnprocessable()->assertJsonValidationErrors('audio');
});

it('mirrors a file to the foreign disk and stamps the post', function () {
    Storage::fake('local');
    Storage::fake('foreign');
    config(['periscope.foreign_disk' => 'foreign']);
    Storage::disk('local')->put('audio/original/a.mp3', 'sound');
    $post = Post::factory()->withAudio('audio/original/a.mp3')->create();

    (new MirrorFileToForeignHost('local', 'audio/original/a.mp3', $post->id))->handle();

    Storage::disk('foreign')->assertExists('audio/original/a.mp3');
    expect($post->fresh()->audio_mirrored_at)->not->toBeNull();
});

it('skips mirroring a file that no longer exists', function () {
    Storage::fake('local');
    Storage::fake('foreign');
    config(['periscope.foreign_disk' => 'foreign']);

    (new MirrorFileToForeignHost('local', 'audio/original/gone.mp3'))->handle();

    Storage::disk('foreign')->assertMissing('audio/original/gone.mp3');
});

it('lists hashtags for the editor', function () {
    Tag::create(['name' => 'B']);
    Tag::create(['name' => 'A']);

    asAdmin()->getJson('/api/admin/tags')->assertOk()->assertExactJson(['data' => ['A', 'B']]);
});

it('lists likes, dislikes and comments per post', function () {
    $post = Post::factory()->free()->create();
    $post->react(User::factory()->create(), ReactionType::Like);
    $post->react(User::factory()->create(), ReactionType::Like);
    $post->react(User::factory()->create(), ReactionType::Dislike);
    Comment::factory()->count(3)->for($post)->create();

    asAdmin()->getJson('/api/admin/posts')->assertOk()
        ->assertJsonPath('data.0.feedback', ['likes' => 2, 'dislikes' => 1, 'comments' => 3]);
});

it('sorts the post list by an allowed column and ignores unknown ones', function () {
    $quiet = Post::factory()->create(['episode_number' => 2]);
    $liked = Post::factory()->create(['episode_number' => 1]);
    $liked->react(User::factory()->create(), ReactionType::Like);

    $ids = fn (string $query) => collect(asAdmin()->getJson("/api/admin/posts?{$query}")->assertOk()->json('data'))->pluck('id')->all();

    expect($ids('sort=likes_count&descending=1'))->toBe([$liked->id, $quiet->id])
        ->and($ids('sort=episode_number&descending=0'))->toBe([$liked->id, $quiet->id])
        ->and($ids('sort=episode_number&descending=1'))->toBe([$quiet->id, $liked->id])
        ->and($ids('sort=password'))->toBe([$liked->id, $quiet->id]);
});
