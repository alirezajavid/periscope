<?php

use App\Models\User;
use App\Models\UserSession;

const CHROME_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
const CHROME_WINDOWS_NEWER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';
const FIREFOX_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:128.0) Gecko/20100101 Firefox/128.0';
const CHROME_ANDROID = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36';

beforeEach(function () {
    $this->withHeader('Referer', 'http://localhost');
    $this->device = ['browser' => 'Chrome', 'os' => 'Windows'];
});

it('requires authentication', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

it('keeps the session when only the browser version changes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['device' => $this->device])
        ->withHeader('User-Agent', CHROME_WINDOWS_NEWER)
        ->getJson('/api/user')
        ->assertOk();
});

it('invalidates the session when the browser family changes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['device' => $this->device])
        ->withHeader('User-Agent', FIREFOX_WINDOWS)
        ->getJson('/api/user')
        ->assertUnauthorized();
});

it('invalidates the session when the OS family changes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['device' => $this->device])
        ->withHeader('User-Agent', CHROME_ANDROID)
        ->getJson('/api/user')
        ->assertUnauthorized();
});

it('signs a blocked user out of an existing session', function () {
    $user = User::factory()->blocked()->create();

    $this->actingAs($user)
        ->withSession(['device' => $this->device])
        ->withHeader('User-Agent', CHROME_WINDOWS)
        ->getJson('/api/user')
        ->assertForbidden();
});

it('logs out of every device', function () {
    $user = User::factory()->create();
    foreach (['a', 'b', 'c'] as $id) {
        UserSession::create([
            'user_id' => $user->id, 'session_id' => $id, 'browser_family' => 'Chrome',
            'os_family' => 'Windows', 'last_activity_at' => now(),
        ]);
    }
    $other = User::factory()->create();
    UserSession::create([
        'user_id' => $other->id, 'session_id' => 'z', 'browser_family' => 'Chrome',
        'os_family' => 'Windows', 'last_activity_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['device' => $this->device])
        ->withHeader('User-Agent', CHROME_WINDOWS)
        ->postJson('/api/logout-all')
        ->assertNoContent();

    expect($user->sessions()->count())->toBe(0)
        ->and($other->sessions()->count())->toBe(1);
});

it('signs out of all devices after a password change', function () {
    $user = User::factory()->create(['password' => 'old-secret-1']);
    UserSession::create([
        'user_id' => $user->id, 'session_id' => 'other-device', 'browser_family' => 'Safari',
        'os_family' => 'iOS', 'last_activity_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['device' => $this->device])
        ->withHeader('User-Agent', CHROME_WINDOWS)
        ->putJson('/api/password', [
            'current_password' => 'old-secret-1',
            'password' => 'new-secret-2',
            'password_confirmation' => 'new-secret-2',
        ])->assertOk();

    expect($user->sessions()->count())->toBe(0)
        ->and(Hash::check('new-secret-2', $user->fresh()->password))->toBeTrue();
});

it('refuses a password change with the wrong current password', function () {
    $user = User::factory()->create(['password' => 'old-secret-1']);

    $this->actingAs($user)
        ->withSession(['device' => $this->device])
        ->withHeader('User-Agent', CHROME_WINDOWS)
        ->putJson('/api/password', [
            'current_password' => 'nope',
            'password' => 'new-secret-2',
            'password_confirmation' => 'new-secret-2',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
});

it('derives browser and OS families, ignoring versions', function (string $ua, string $browser, string $os) {
    expect(UserSession::families($ua))->toBe(['browser' => $browser, 'os' => $os]);
})->with([
    'chrome windows' => [CHROME_WINDOWS, 'Chrome', 'Windows'],
    'chrome windows newer' => [CHROME_WINDOWS_NEWER, 'Chrome', 'Windows'],
    'firefox windows' => [FIREFOX_WINDOWS, 'Firefox', 'Windows'],
    'chrome android' => [CHROME_ANDROID, 'Chrome', 'Android'],
    'safari iOS' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', 'Safari', 'iOS'],
    'installed iOS PWA' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148', 'Safari', 'iOS'],
    'edge' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0', 'Edge', 'Windows'],
]);
