<?php

use App\Jobs\GeneratePersonalizedAudio;
use App\Models\PersonalizedFile;
use App\Models\Post;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->post = Post::factory()->withAudio()->create();
});

it('shows watermark counts on the admin post list and detail', function () {
    PersonalizedFile::factory()->ready()->count(2)->create(['post_id' => $this->post->id]);
    PersonalizedFile::factory()->failed()->create(['post_id' => $this->post->id]);
    PersonalizedFile::factory()->create(['post_id' => $this->post->id]);

    $expected = ['ready' => 2, 'building' => 1, 'failed' => 1];
    asAdmin()->getJson('/api/admin/posts')->assertOk()->assertJsonPath('data.0.watermarks', $expected);
    asAdmin()->getJson("/api/admin/posts/{$this->post->id}")->assertOk()->assertJsonPath('data.watermarks', $expected);
});

it('lists failed copies with the error message', function () {
    PersonalizedFile::factory()->failed('audiowmark failed: disk full')->create(['post_id' => $this->post->id]);
    PersonalizedFile::factory()->ready()->create(['post_id' => $this->post->id]);

    asAdmin()->getJson("/api/admin/posts/{$this->post->id}/watermarks?status=failed")->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.error', 'audiowmark failed: disk full');
});

it('re-queues only the failed copies', function () {
    $failed = PersonalizedFile::factory()->failed()->create(['post_id' => $this->post->id]);
    PersonalizedFile::factory()->ready()->create(['post_id' => $this->post->id]);

    asAdmin()->postJson("/api/admin/posts/{$this->post->id}/watermarks/retry")->assertOk()->assertJsonPath('queued', 1);

    Queue::assertPushed(GeneratePersonalizedAudio::class, 1);
    Queue::assertPushed(GeneratePersonalizedAudio::class, fn ($job) => $job->userId === $failed->user_id);
});

it('is admin-only', function () {
    asUser()->getJson("/api/admin/posts/{$this->post->id}/watermarks")->assertForbidden();
    asUser()->postJson("/api/admin/posts/{$this->post->id}/watermarks/retry")->assertForbidden();
});
