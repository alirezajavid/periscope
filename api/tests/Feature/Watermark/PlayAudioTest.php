<?php

use App\Jobs\GeneratePersonalizedAudio;
use App\Models\PersonalizedFile;
use App\Models\Post;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    Storage::disk('local')->put('audio/original/ep.mp3', 'ORIGINAL-PREMIUM-AUDIO');
    $this->post = Post::factory()->withAudio('audio/original/ep.mp3')->create();
    $this->user = User::factory()->create();
    Subscription::factory()->for($this->user)->create();
});

function readyCopy(User $user, Post $post, string $content = 'WATERMARKED-FOR-USER'): PersonalizedFile
{
    $path = "audio/personalized/{$post->id}/{$user->id}.mp3";
    Storage::disk('local')->put($path, $content);

    return PersonalizedFile::factory()->ready($path)->create(['user_id' => $user->id, 'post_id' => $post->id]);
}

it('tells a subscriber the copy is being prepared and queues it on the priority queue', function () {
    asUser($this->user)->getJson("/api/posts/{$this->post->id}/audio?device=phone")
        ->assertStatus(202)->assertJsonPath('status', 'preparing')->assertJsonMissingPath('url');

    Queue::assertPushedOn(GeneratePersonalizedAudio::PRIORITY_QUEUE, GeneratePersonalizedAudio::class,
        fn ($job) => $job->postId === $this->post->id && $job->userId === $this->user->id);
});

it('does not queue a second build while one is already running', function () {
    PersonalizedFile::factory()->create(['user_id' => $this->user->id, 'post_id' => $this->post->id]); // building

    asUser($this->user)->getJson("/api/posts/{$this->post->id}/audio?device=phone")->assertStatus(202);

    Queue::assertNothingPushed();
});

it('retries a failed build when the listener asks again', function () {
    PersonalizedFile::factory()->failed()->create(['user_id' => $this->user->id, 'post_id' => $this->post->id]);

    asUser($this->user)->getJson("/api/posts/{$this->post->id}/audio?device=phone")->assertStatus(202);

    Queue::assertPushed(GeneratePersonalizedAudio::class);
});

it('gives a signed URL to the subscriber\'s own copy, never to the original', function () {
    readyCopy($this->user, $this->post);

    $response = asUser($this->user)->getJson("/api/posts/{$this->post->id}/audio?device=phone")
        ->assertOk()->assertJsonPath('status', 'ready');

    expect($response->json('url'))->toContain('/api/audio/')->toContain('signature=')
        ->not->toContain('original')->not->toContain('ep.mp3');
});

it('streams the copy from the signed URL, with Range support, and it is the watermarked bytes', function () {
    readyCopy($this->user, $this->post);
    $url = asUser($this->user)->getJson("/api/posts/{$this->post->id}/audio?device=phone")->json('url');

    $full = $this->get($url)->assertOk();
    expect($full->streamedContent() ?: file_get_contents($full->baseResponse->getFile()->getPathname()))
        ->toBe('WATERMARKED-FOR-USER');
    $this->get($url, ['Range' => 'bytes=0-4'])->assertStatus(206);
});

it('refuses tampered, unsigned and expired URLs', function () {
    $copy = readyCopy($this->user, $this->post);
    $url = asUser($this->user)->getJson("/api/posts/{$this->post->id}/audio?device=phone")->json('url');

    $this->get("/api/audio/{$copy->id}")->assertForbidden();
    $this->get(preg_replace('/signature=[0-9a-f]+/', 'signature=deadbeef', $url))->assertForbidden();
    $this->get(str_replace("/audio/{$copy->id}", '/audio/'.($copy->id + 1), $url))->assertForbidden();

    $this->travel(3)->hours();
    $this->get($url)->assertForbidden();
});

it('keeps premium audio from anonymous visitors', function () {
    $this->getJson("/api/posts/{$this->post->id}/audio?device=phone")->assertUnauthorized();
    Queue::assertNothingPushed();
});

it('keeps premium audio from non-subscribers and from lapsed subscribers', function () {
    asUser(User::factory()->create())->getJson("/api/posts/{$this->post->id}/audio?device=phone")->assertForbidden();

    Subscription::query()->delete();
    asUser($this->user)->getJson("/api/posts/{$this->post->id}/audio?device=phone")->assertForbidden();
    Queue::assertNothingPushed();
});

it('does not hand out a copy that belongs to someone else', function () {
    $other = User::factory()->create();
    Subscription::factory()->for($other)->create();
    readyCopy($other, $this->post, 'WATERMARKED-FOR-OTHER');

    // The requesting user has no copy of their own: they must wait for theirs, not get the other one.
    asUser($this->user)->getJson("/api/posts/{$this->post->id}/audio?device=phone")
        ->assertStatus(202)->assertJsonMissingPath('url');
});

it('points free episodes at the public file', function () {
    $free = Post::factory()->free()->withAudio('audio/original/ep.mp3')->create();

    $this->getJson("/api/posts/{$free->id}/audio?device=phone")->assertOk()
        ->assertJsonPath('url', url("/api/posts/{$free->id}/free-audio"));
});

it('rebuilds after the keep period', function () {
    $copy = readyCopy($this->user, $this->post);
    $copy->update(['expires_at' => now()->subMinute()]);

    asUser($this->user)->getJson("/api/posts/{$this->post->id}/audio?device=phone")->assertStatus(202);
    Queue::assertPushed(GeneratePersonalizedAudio::class);
});

it('needs a device id', function () {
    asUser($this->user)->getJson("/api/posts/{$this->post->id}/audio")->assertUnprocessable()->assertJsonValidationErrors('device');
});

it('lets only one device play at a time', function () {
    readyCopy($this->user, $this->post);

    asUser($this->user)->getJson("/api/posts/{$this->post->id}/audio?device=phone")->assertOk();
    asUser($this->user)->getJson("/api/posts/{$this->post->id}/audio?device=laptop")
        ->assertStatus(409)->assertJsonPath('code', 'playing_elsewhere');
    asUser($this->user)->getJson("/api/posts/{$this->post->id}/audio?device=phone")->assertOk(); // same device is fine
});

it('frees the lock on release, or when heartbeats stop', function () {
    readyCopy($this->user, $this->post);
    $play = fn (string $device) => asUser($this->user)->getJson("/api/posts/{$this->post->id}/audio?device={$device}");

    $play('phone')->assertOk();
    asUser($this->user)->postJson('/api/playback/release', ['device' => 'laptop'])->assertNoContent(); // not the holder: no effect
    $play('laptop')->assertStatus(409);

    asUser($this->user)->postJson('/api/playback/release', ['device' => 'phone'])->assertNoContent();
    $play('laptop')->assertOk();

    $this->travel(91)->seconds(); // laptop stops sending heartbeats
    $play('phone')->assertOk();
});

it('keeps the lock alive with heartbeats and rejects other devices', function () {
    asUser($this->user)->postJson('/api/playback/heartbeat', ['device' => 'phone'])->assertNoContent();
    asUser($this->user)->postJson('/api/playback/heartbeat', ['device' => 'laptop'])->assertStatus(409);

    $this->travel(60)->seconds();
    asUser($this->user)->postJson('/api/playback/heartbeat', ['device' => 'phone'])->assertNoContent();
    $this->travel(60)->seconds(); // 120 s since the first beat, 60 s since the second
    asUser($this->user)->postJson('/api/playback/heartbeat', ['device' => 'laptop'])->assertStatus(409);
});

it('locks per account, not globally', function () {
    $other = User::factory()->create();

    asUser($this->user)->postJson('/api/playback/heartbeat', ['device' => 'phone'])->assertNoContent();
    asUser($other)->postJson('/api/playback/heartbeat', ['device' => 'laptop'])->assertNoContent();
});
