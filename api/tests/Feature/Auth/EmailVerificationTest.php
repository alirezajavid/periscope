<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

beforeEach(fn () => $this->withHeader('Referer', 'http://localhost'));

function verificationUrl(User $user, ?string $hash = null): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => $hash ?? sha1($user->email),
    ]);
}

it('verifies the email from the signed link and redirects to the app', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verificationUrl($user))
        ->assertRedirect(config('periscope.app_url').'/#/email-verified');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects a link with the wrong hash or no signature', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verificationUrl($user, 'wrong'))->assertForbidden();
    $this->get("/api/email/verify/{$user->id}/".sha1($user->email))->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification email only to unverified users', function () {
    Notification::fake();
    $unverified = User::factory()->unverified()->create();
    $verified = User::factory()->create();
    $device = ['browser' => 'Other', 'os' => 'Other'];

    $this->actingAs($unverified)->withSession(['device' => $device])->postJson('/api/email/resend')->assertOk();
    $this->actingAs($verified)->withSession(['device' => $device])->postJson('/api/email/resend')->assertOk();

    Notification::assertSentTo($unverified, VerifyEmail::class);
    Notification::assertNotSentTo($verified, VerifyEmail::class);
});
