<?php

use App\Jobs\GeneratePersonalizedAudio;
use App\Models\PersonalizedFile;
use App\Models\Post;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function activeSubscriber(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    Subscription::factory()->for($user)->create();

    return $user;
}

beforeEach(fn () => Queue::fake());

it('queues copies for active subscribers of a premium post going live within 3 hours', function () {
    $post = Post::factory()->withAudio()->create(['published_at' => now()->addHours(2)]);
    $a = activeSubscriber();
    $b = activeSubscriber();

    $this->artisan('periscope:build-watermarks')->assertSuccessful();

    Queue::assertPushed(GeneratePersonalizedAudio::class, 2);
    Queue::assertPushed(GeneratePersonalizedAudio::class, fn ($job) => $job->postId === $post->id && $job->userId === $a->id);
    Queue::assertPushed(GeneratePersonalizedAudio::class, fn ($job) => $job->userId === $b->id);
});

it('does not queue for non-subscribers, expired subscriptions or blocked users', function () {
    Post::factory()->withAudio()->create(['published_at' => now()->addHour()]);
    User::factory()->create();
    Subscription::factory()->expired()->for(User::factory()->create())->create();
    $blocked = User::factory()->blocked()->create();
    Subscription::factory()->for($blocked)->create();
    $valid = activeSubscriber();

    $this->artisan('periscope:build-watermarks')->assertSuccessful();

    Queue::assertPushed(GeneratePersonalizedAudio::class, 1);
    Queue::assertPushed(GeneratePersonalizedAudio::class, fn ($job) => $job->userId === $valid->id);
});

it('skips free posts, posts without audio, drafts and posts far in the future', function () {
    activeSubscriber();
    Post::factory()->free()->withAudio()->create(['published_at' => now()->addHour()]);
    Post::factory()->create(['published_at' => now()->addHour()]); // no audio
    Post::factory()->draft()->withAudio()->create();
    Post::factory()->withAudio()->create(['published_at' => now()->addHours(10)]);
    Post::factory()->withAudio()->create(['published_at' => now()->subDays(3)]);

    $this->artisan('periscope:build-watermarks')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('does not queue again for a user who already has a copy', function () {
    $post = Post::factory()->withAudio()->create(['published_at' => now()->addHour()]);
    $user = activeSubscriber();
    PersonalizedFile::factory()->create(['user_id' => $user->id, 'post_id' => $post->id]);

    $this->artisan('periscope:build-watermarks')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('serves the most recently active listeners first', function () {
    Post::factory()->withAudio()->create(['published_at' => now()->addHour()]);
    $old = activeSubscriber(['last_login_at' => now()->subMonths(2)]);
    $recent = activeSubscriber(['last_login_at' => now()->subHour()]);
    $never = activeSubscriber(['last_login_at' => null]);

    $this->artisan('periscope:build-watermarks')->assertSuccessful();

    $order = Queue::pushed(GeneratePersonalizedAudio::class)->map(fn ($job) => $job->userId)->values()->all();
    expect($order)->toBe([$recent->id, $old->id, $never->id]);
});

it('removes expired copies and keeps current ones', function () {
    Storage::fake('local');
    Storage::fake('foreign');
    config(['periscope.foreign_disk' => 'foreign']);
    Storage::disk('local')->put('audio/personalized/1/1.mp3', 'a');
    Storage::disk('local')->put('audio/personalized/1/2.mp3', 'b');
    $expired = PersonalizedFile::factory()->ready('audio/personalized/1/1.mp3')->create(['expires_at' => now()->subHour()]);
    $current = PersonalizedFile::factory()->ready('audio/personalized/1/2.mp3')->create();

    $this->artisan('periscope:cleanup-watermarks')->assertSuccessful();

    expect(PersonalizedFile::find($expired->id))->toBeNull()->and(PersonalizedFile::find($current->id))->not->toBeNull();
    Storage::disk('local')->assertMissing('audio/personalized/1/1.mp3');
    Storage::disk('local')->assertExists('audio/personalized/1/2.mp3');
});
