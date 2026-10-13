<?php

use App\Models\SocialAccount;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

beforeEach(fn () => $this->withHeader('Referer', 'http://localhost'));

function fakeGoogle(string $id, ?string $email, string $name = 'Sara'): void
{
    $google = (new GoogleUser)->map(['id' => $id, 'name' => $name, 'email' => $email]);
    Socialite::shouldReceive('driver->user')->andReturn($google);
}

it('creates a verified user on first Google sign-in and sends them to the app', function () {
    fakeGoogle('g-1', 'sara@example.com');

    $this->get('/api/auth/google/callback')->assertRedirect(config('periscope.app_url').'/#/');

    $user = User::firstWhere('email', 'sara@example.com');
    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->password)->toBeNull()
        ->and($user->sessions()->count())->toBe(1)
        ->and(SocialAccount::where('user_id', $user->id)->where('provider_id', 'g-1')->exists())->toBeTrue();
});

it('links Google to an existing account with the same email', function () {
    $user = User::factory()->unverified()->create(['email' => 'sara@example.com']);
    fakeGoogle('g-1', 'sara@example.com');

    $this->get('/api/auth/google/callback')->assertRedirect();

    expect(User::count())->toBe(1)
        ->and($user->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and(SocialAccount::where('user_id', $user->id)->exists())->toBeTrue();
});

it('reuses the linked account on later sign-ins', function () {
    fakeGoogle('g-1', 'sara@example.com');
    $this->get('/api/auth/google/callback');
    fakeGoogle('g-1', 'sara@example.com');
    $this->get('/api/auth/google/callback');

    expect(User::count())->toBe(1)->and(SocialAccount::count())->toBe(1);
});

it('does not sign a blocked user in', function () {
    User::factory()->blocked()->create(['email' => 'sara@example.com']);
    fakeGoogle('g-1', 'sara@example.com');

    $this->get('/api/auth/google/callback')->assertRedirect(config('periscope.app_url').'/#/login?error=blocked');
});

it('sends the user back to login when Google fails', function () {
    Socialite::shouldReceive('driver->user')->andThrow(new RuntimeException('denied'));

    $this->get('/api/auth/google/callback')->assertRedirect(config('periscope.app_url').'/#/login?error=google');
});
