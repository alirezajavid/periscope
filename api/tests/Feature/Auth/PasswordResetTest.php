<?php

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(fn () => $this->withHeader('Referer', 'http://localhost'));

it('answers the same way for known and unknown emails', function () {
    Notification::fake();
    $user = User::factory()->create();

    $known = $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
    $unknown = $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

    expect($known->json())->toBe($unknown->json());
    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertCount(1);
});

it('points the reset link at the frontend app', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->postJson('/api/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, config('periscope.app_url').'/#/reset-password?token=');
    });
});

it('resets the password and signs the user out everywhere', function () {
    $user = User::factory()->create();
    UserSession::create([
        'user_id' => $user->id, 'session_id' => 'old', 'browser_family' => 'Chrome',
        'os_family' => 'Windows', 'last_activity_at' => now(),
    ]);
    $token = Password::createToken($user);

    $this->postJson('/api/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'brand-new-pass-1',
        'password_confirmation' => 'brand-new-pass-1',
    ])->assertOk();

    expect(Hash::check('brand-new-pass-1', $user->fresh()->password))->toBeTrue()
        ->and($user->sessions()->count())->toBe(0);
});

it('rejects an invalid reset token', function () {
    $user = User::factory()->create();

    $this->postJson('/api/reset-password', [
        'token' => 'bad-token',
        'email' => $user->email,
        'password' => 'brand-new-pass-1',
        'password_confirmation' => 'brand-new-pass-1',
    ])->assertUnprocessable();
});
