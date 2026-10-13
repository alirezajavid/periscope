<?php

use App\Models\AdminLog;
use App\Models\LoginEvent;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->withHeader('Referer', 'http://localhost');
});

/** Test requests don't keep cookies; carry the admin session cookie to the next request by hand. */
function keepSession(object $test, TestResponse $response): TestResponse
{
    foreach ($response->headers->getCookies() as $cookie) {
        $test->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
    }

    return $response;
}

function totp(): string
{
    return (new Google2FA)->getCurrentOtp(UserFactory::TOTP_SECRET);
}

it('uses its own session cookie for admin routes', function () {
    $admin = User::factory()->admin()->create(['password' => 'Secret-pass-1']);

    $response = $this->postJson('/api/admin/login', ['email' => $admin->email, 'password' => 'Secret-pass-1']);

    $sessionCookies = collect($response->headers->getCookies())
        ->map->getName()->filter(fn (string $name) => str_ends_with($name, '_session'))->values();

    expect($sessionCookies->all())->toBe(['periscope_admin_session']);
});

it('gives the same answer for unknown users, non-admins and wrong passwords', function () {
    $subscriber = User::factory()->create(['password' => 'Secret-pass-1']);
    $admin = User::factory()->admin()->create(['password' => 'Secret-pass-1']);

    $unknown = $this->postJson('/api/admin/login', ['email' => 'nobody@example.com', 'password' => 'x']);
    $notAdmin = $this->postJson('/api/admin/login', ['email' => $subscriber->email, 'password' => 'Secret-pass-1']);
    $wrong = $this->postJson('/api/admin/login', ['email' => $admin->email, 'password' => 'wrong']);

    foreach ([$unknown, $notAdmin, $wrong] as $response) {
        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }
    expect($notAdmin->json())->toBe($unknown->json());
    expect(LoginEvent::where('successful', false)->count())->toBe(3);
});

it('walks a new admin through TOTP setup and logs them in only after the first code', function () {
    $admin = User::factory()->admin()->create(['password' => 'Secret-pass-1']);

    keepSession($this, $this->postJson('/api/admin/login', ['email' => $admin->email, 'password' => 'Secret-pass-1']))
        ->assertOk()->assertJsonPath('status', 'two_factor_setup_required');

    $this->getJson('/api/admin/user')->assertUnauthorized(); // password alone is not a login

    $setup = $this->postJson('/api/admin/two-factor/setup')->assertOk()
        ->assertJsonStructure(['secret', 'qr_svg', 'qr_url', 'recovery_codes']);
    $secret = $setup->json('secret');
    expect($setup->json('recovery_codes'))->toHaveCount(8);

    $this->postJson('/api/admin/two-factor/confirm', ['code' => '000000'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');
    expect($admin->fresh()->two_factor_confirmed_at)->toBeNull();

    $this->postJson('/api/admin/two-factor/confirm', ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertOk()->assertJsonPath('data.id', $admin->id);

    expect($admin->fresh()->two_factor_confirmed_at)->not->toBeNull()
        ->and(AdminLog::where('action', 'admin.two_factor_enabled')->exists())->toBeTrue()
        ->and(AdminLog::where('action', 'admin.login')->where('admin_id', $admin->id)->exists())->toBeTrue();
    $this->getJson('/api/admin/user')->assertOk();
});

it('asks an admin with TOTP for a code and rejects a wrong one', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create(['password' => 'Secret-pass-1']);

    keepSession($this, $this->postJson('/api/admin/login', ['email' => $admin->email, 'password' => 'Secret-pass-1']))
        ->assertJsonPath('status', 'two_factor_required');

    $this->postJson('/api/admin/two-factor/challenge', ['code' => '000000'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->getJson('/api/admin/user')->assertUnauthorized();

    $this->postJson('/api/admin/two-factor/challenge', ['code' => totp()])->assertOk();
    $this->getJson('/api/admin/user')->assertOk()->assertJsonPath('data.email', $admin->email);
});

it('accepts a recovery code once', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create(['password' => 'Secret-pass-1']);

    keepSession($this, $this->postJson('/api/admin/login', ['email' => $admin->email, 'password' => 'Secret-pass-1']));
    $this->postJson('/api/admin/two-factor/challenge', ['recovery_code' => 'recovery-one'])->assertOk();

    expect($admin->fresh()->recoveryCodes())->not->toContain('recovery-one')->toHaveCount(2);
});

it('does not allow the TOTP step without the password step', function () {
    User::factory()->admin()->withTwoFactor()->create();

    $this->postJson('/api/admin/two-factor/challenge', ['code' => totp()])->assertUnauthorized();
    $this->postJson('/api/admin/two-factor/setup')->assertUnauthorized();
});

it('does not allow a subscriber session on admin routes', function () {
    $subscriber = User::factory()->create();

    $this->actingAs($subscriber)->withSession(['admin_two_factor_passed' => true])
        ->getJson('/api/admin/user')->assertForbidden();
});

it('refuses an admin whose TOTP step was not completed in this session', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();

    $this->actingAs($admin)->getJson('/api/admin/user')->assertUnauthorized();
});

it('signs the admin out after 30 idle minutes and keeps active sessions alive', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create(['password' => 'Secret-pass-1']);
    keepSession($this, $this->postJson('/api/admin/login', ['email' => $admin->email, 'password' => 'Secret-pass-1']));
    $this->postJson('/api/admin/two-factor/challenge', ['code' => totp()])->assertOk();

    $this->travel(29)->minutes();
    $this->getJson('/api/admin/user')->assertOk();

    $this->travel(29)->minutes(); // 58 min since login, 29 since last request
    $this->getJson('/api/admin/user')->assertOk();

    $this->travel(31)->minutes();
    $this->getJson('/api/admin/user')->assertUnauthorized();
});

it('logs out and records it', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();

    $this->actingAs($admin)->withSession(['admin_two_factor_passed' => true])
        ->postJson('/api/admin/logout')->assertNoContent();

    expect(AdminLog::where('action', 'admin.logout')->where('admin_id', $admin->id)->exists())->toBeTrue();
});

it('does not let a blocked admin start a login', function () {
    $admin = User::factory()->admin()->blocked()->create(['password' => 'Secret-pass-1']);

    $this->postJson('/api/admin/login', ['email' => $admin->email, 'password' => 'Secret-pass-1'])->assertForbidden();
});

it('hands out a CSRF token in the response body', function () {
    $this->getJson('/api/admin/csrf-token')->assertOk()->assertJsonStructure(['token']);
});
