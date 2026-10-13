<?php

use App\Models\Post;
use App\Models\User;
use App\Notifications\NewEpisode;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('emails opted-in users about a post that just went live, once', function () {
    $in = User::factory()->create();
    User::factory()->create(['notify_new_episode' => false]);
    User::factory()->unverified()->create();
    User::factory()->blocked()->create();
    User::factory()->create(['email' => null, 'phone' => '+989123456789']);
    $post = Post::factory()->create(['published_at' => now()->subMinutes(2)]);

    $this->artisan('periscope:notify-new-episodes')->assertSuccessful();
    $this->artisan('periscope:notify-new-episodes')->assertSuccessful();

    Notification::assertSentTo($in, NewEpisode::class, 1);
    Notification::assertCount(1);
    expect($post->fresh()->notified_at)->not->toBeNull();
});

it('does not email about drafts, scheduled posts or old posts', function () {
    User::factory()->create();
    Post::factory()->draft()->create();
    Post::factory()->scheduled()->create();
    Post::factory()->create(['published_at' => now()->subDays(5)]);

    $this->artisan('periscope:notify-new-episodes')->assertSuccessful();

    Notification::assertNothingSent();
});

it('emails about a scheduled post once its time has come', function () {
    User::factory()->create();
    Post::factory()->create(['published_at' => now()->addHour()]);

    $this->artisan('periscope:notify-new-episodes');
    Notification::assertNothingSent();

    $this->travel(61)->minutes();
    $this->artisan('periscope:notify-new-episodes');
    Notification::assertCount(1);
});

it('puts a working one-click unsubscribe link in the email', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create();
    $url = null;
    $mail = (new NewEpisode($post))->toMail($user);
    foreach ($mail->outroLines as $line) {
        if (preg_match('~(https?://\S+/api/unsubscribe/\d+\?\S+)~', $line, $match)) {
            $url = $match[1];
        }
    }

    expect($url)->not->toBeNull();
    $this->get($url)->assertRedirect(config('periscope.app_url').'/#/unsubscribed');
    expect($user->fresh()->notify_new_episode)->toBeFalse();
});

it('refuses an unsubscribe link that was not signed', function () {
    $user = User::factory()->create();

    $this->get("/api/unsubscribe/{$user->id}")->assertForbidden();
    expect($user->fresh()->notify_new_episode)->toBeTrue();
});

it('lets a user change their profile and turn the emails off', function () {
    $user = User::factory()->create();

    asUser($user)->putJson('/api/profile', ['name' => 'New Name', 'notify_new_episode' => false, 'preferred_mirror' => 'intl'])
        ->assertOk()->assertJsonPath('data.name', 'New Name')->assertJsonPath('data.preferred_mirror', 'intl');

    expect($user->fresh()->notify_new_episode)->toBeFalse();
});

it('does not let a profile update change role, status or email', function () {
    $user = User::factory()->create(['email' => 'me@example.com']);

    asUser($user)->putJson('/api/profile', ['role' => 'admin', 'status' => 'blocked', 'email' => 'other@example.com'])->assertOk();

    $user->refresh();
    expect($user->role->value)->toBe('user')->and($user->status->value)->toBe('active')->and($user->email)->toBe('me@example.com');
});
