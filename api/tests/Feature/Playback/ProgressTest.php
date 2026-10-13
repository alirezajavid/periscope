<?php

use App\Models\PlaybackProgress;
use App\Models\Post;
use App\Models\Subscription;
use App\Models\User;

beforeEach(function () {
    $this->post = Post::factory()->withAudio()->create(['audio_duration' => 1000]);
    $this->user = User::factory()->create();
    Subscription::factory()->for($this->user)->create();
});

function reportPosition(int $position, string $device = 'phone', ?Post $post = null, ?User $user = null)
{
    return asUser($user ?? test()->user)->putJson('/api/posts/'.($post ?? test()->post)->id.'/progress', ['position' => $position, 'device' => $device]);
}

it('stores the position and lets the listener resume from it', function () {
    reportPosition(120)->assertOk()->assertJsonPath('position', 120);

    asUser($this->user)->getJson("/api/posts/{$this->post->id}/progress")->assertOk()
        ->assertJsonPath('position', 120)->assertJsonPath('completed', false);
});

it('resumes from 0 when never played', function () {
    asUser($this->user)->getJson("/api/posts/{$this->post->id}/progress")->assertOk()->assertJsonPath('position', 0);
});

it('keeps one row per user and post, and the furthest point never shrinks', function () {
    reportPosition(600);
    reportPosition(100); // rewound

    $progress = PlaybackProgress::sole();
    expect($progress->last_position)->toBe(100)->and($progress->max_position)->toBe(600);
});

it('marks an episode completed at 90 percent', function () {
    reportPosition(899)->assertJsonPath('completed', false);
    reportPosition(900)->assertJsonPath('completed', true);

    reportPosition(10); // rewinding does not un-complete it
    expect(PlaybackProgress::sole()->completed)->toBeTrue();
});

it('does not complete an episode of unknown duration', function () {
    $post = Post::factory()->withAudio()->create(['audio_duration' => null]);

    reportPosition(5000, post: $post)->assertJsonPath('completed', false);
});

it('belongs to the post, not to a personalized copy', function () {
    reportPosition(50);

    expect(PlaybackProgress::sole()->post_id)->toBe($this->post->id);
});

it('does not let a non-subscriber report progress on premium content', function () {
    $stranger = User::factory()->create();

    reportPosition(10, user: $stranger)->assertForbidden();
    expect(PlaybackProgress::count())->toBe(0);
});

it('lets a signed-in user report progress on a free episode', function () {
    $free = Post::factory()->free()->withAudio()->create(['audio_duration' => 100]);

    reportPosition(20, post: $free, user: User::factory()->create())->assertOk();
});

it('refuses progress from a second device while another is playing', function () {
    reportPosition(10, 'phone')->assertOk();
    reportPosition(20, 'laptop')->assertStatus(409)->assertJsonPath('code', 'playing_elsewhere');

    expect(PlaybackProgress::sole()->last_position)->toBe(10);
});

it('validates the report', function () {
    asUser($this->user)->putJson("/api/posts/{$this->post->id}/progress", ['position' => -1, 'device' => 'x'])
        ->assertUnprocessable()->assertJsonValidationErrors('position');
    asUser($this->user)->putJson("/api/posts/{$this->post->id}/progress", ['position' => 5])
        ->assertUnprocessable()->assertJsonValidationErrors('device');
});

it('needs a login', function () {
    $this->putJson("/api/posts/{$this->post->id}/progress", ['position' => 1, 'device' => 'x'])->assertUnauthorized();
    $this->getJson('/api/continue')->assertUnauthorized();
});

it('lists unfinished episodes to continue, newest first, and hides finished ones', function () {
    $second = Post::factory()->withAudio()->create(['audio_duration' => 1000]);
    $finished = Post::factory()->withAudio()->create(['audio_duration' => 1000]);

    reportPosition(100);
    $this->travel(5)->minutes();
    reportPosition(300, post: $second);
    reportPosition(950, post: $finished);

    $response = asUser($this->user)->getJson('/api/continue')->assertOk();

    expect($response->json('data.*.post.id'))->toBe([$second->id, $this->post->id])
        ->and($response->json('data.0.remaining'))->toBe(700);
});

it('does not suggest continuing an episode the user may no longer open', function () {
    reportPosition(100);
    Subscription::query()->delete();

    asUser($this->user)->getJson('/api/continue')->assertOk()->assertJsonCount(0, 'data');
});
