<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->withHeader('Referer', 'http://localhost'));

it('registers a user, signs them in and sends the verification email', function () {
    Notification::fake();

    $this->postJson('/api/register', [
        'name' => 'Sara',
        'email' => 'sara@example.com',
        'password' => 'secret-pass-1',
        'password_confirmation' => 'secret-pass-1',
    ])->assertCreated()
        ->assertJsonPath('data.email', 'sara@example.com')
        ->assertJsonPath('data.email_verified', false)
        ->assertJsonMissingPath('data.watermark_payload');

    $user = User::firstWhere('email', 'sara@example.com');
    expect($user->watermark_payload)->toMatch('/^[0-9a-f]{32}$/')
        ->and($user->sessions()->count())->toBe(1);

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejects a duplicate email and a weak password', function () {
    User::factory()->create(['email' => 'sara@example.com']);

    $this->postJson('/api/register', [
        'name' => 'Sara',
        'email' => 'sara@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
});
