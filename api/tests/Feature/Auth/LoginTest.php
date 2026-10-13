<?php

use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->withHeader('Referer', 'http://localhost');
    RateLimiter::clear('login');
});

it('logs in with email and password and records the session and event', function () {
    $user = User::factory()->create(['password' => 'Secret-pass-1']);

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Secret-pass-1'])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);

    expect($user->fresh()->last_login_at)->not->toBeNull()
        ->and($user->sessions()->count())->toBe(1)
        ->and(LoginEvent::where('user_id', $user->id)->where('successful', true)->count())->toBe(1);
});

it('rejects wrong credentials and records the failed attempt', function () {
    $user = User::factory()->create(['password' => 'Secret-pass-1']);

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect(LoginEvent::where('user_id', $user->id)->where('successful', false)->count())->toBe(1)
        ->and($user->sessions()->count())->toBe(0);
});

it('does not let a blocked user in', function () {
    $user = User::factory()->blocked()->create(['password' => 'Secret-pass-1']);

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Secret-pass-1'])
        ->assertForbidden();

    expect($user->sessions()->count())->toBe(0);
    $this->getJson('/api/user')->assertUnauthorized();
});

it('throttles repeated login attempts', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $i) {
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
    }

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])->assertTooManyRequests();
});
