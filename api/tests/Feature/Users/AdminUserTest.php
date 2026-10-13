<?php

use App\Enums\UserStatus;
use App\Models\AdminLog;
use App\Models\LoginEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserSession;
use App\Notifications\AccountCreated;
use Illuminate\Support\Facades\Notification;

it('keeps non-admins out', function () {
    $user = User::factory()->create();

    asUser()->getJson('/api/admin/users')->assertForbidden();
    asUser()->getJson("/api/admin/users/{$user->id}")->assertForbidden();
    asUser()->postJson("/api/admin/users/{$user->id}/block")->assertForbidden();
    asUser()->postJson('/api/admin/users', ['name' => 'X', 'email' => 'x@example.com'])->assertForbidden();
    expect($user->fresh()->status)->toBe(UserStatus::Active);
});

it('lists and filters users', function () {
    User::factory()->create(['name' => 'Sara Ahmadi', 'email' => 'sara@example.com']);
    $subscriber = User::factory()->create(['name' => 'Ali Rezaei']);
    Subscription::factory()->for($subscriber)->create();
    User::factory()->blocked()->create();

    asAdmin()->getJson('/api/admin/users?q=Sara')->assertOk()->assertJsonCount(1, 'data');
    asAdmin()->getJson('/api/admin/users?subscribed=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $subscriber->id);
    asAdmin()->getJson('/api/admin/users?status=blocked')->assertOk()->assertJsonCount(1, 'data');
    asAdmin()->getJson('/api/admin/users?q=%25')->assertOk()->assertJsonCount(0, 'data');
});

it('shows the full detail including the watermark id, subscriptions, sessions and logins', function () {
    $user = User::factory()->create();
    Subscription::factory()->for($user)->create();
    UserSession::create(['user_id' => $user->id, 'session_id' => 's1', 'browser_family' => 'Chrome', 'os_family' => 'Android', 'last_activity_at' => now()]);
    LoginEvent::create(['user_id' => $user->id, 'method' => 'email', 'successful' => true, 'ip' => '1.2.3.4']);

    $data = asAdmin()->getJson("/api/admin/users/{$user->id}")->assertOk()->json('data');

    expect($data['watermark_payload'])->toBe($user->watermark_payload)
        ->and($data['subscribed'])->toBeTrue()
        ->and($data['subscriptions'])->toHaveCount(1)
        ->and($data['sessions'][0]['browser'])->toBe('Chrome')
        ->and($data['login_events'][0]['ip'])->toBe('1.2.3.4');
});

it('does not show the watermark id in the list or to the user themselves', function () {
    $user = User::factory()->create();

    expect(asAdmin()->getJson('/api/admin/users')->getContent())->not->toContain($user->watermark_payload);
    expect(asUser($user)->getJson('/api/user')->getContent())->not->toContain($user->watermark_payload);
});

it('blocks a user: status, sessions destroyed, logged', function () {
    $user = User::factory()->create();
    UserSession::create(['user_id' => $user->id, 'session_id' => 's1', 'browser_family' => 'Chrome', 'os_family' => 'Windows', 'last_activity_at' => now()]);

    asAdmin()->postJson("/api/admin/users/{$user->id}/block")->assertOk()->assertJsonPath('data.status', 'blocked');

    expect($user->fresh()->isBlocked())->toBeTrue()->and($user->sessions()->count())->toBe(0)
        ->and(AdminLog::where('action', 'user.block')->where('target_id', $user->id)->exists())->toBeTrue();

    asAdmin()->postJson("/api/admin/users/{$user->id}/unblock")->assertOk()->assertJsonPath('data.status', 'active');
});

it('does not let an admin block themselves', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();

    asAdmin($admin)->postJson("/api/admin/users/{$admin->id}/block")->assertUnprocessable();
    expect($admin->fresh()->isBlocked())->toBeFalse();
});

it('logs a user out of every device', function () {
    $user = User::factory()->create();
    foreach (['a', 'b'] as $id) {
        UserSession::create(['user_id' => $user->id, 'session_id' => $id, 'browser_family' => 'Chrome', 'os_family' => 'Windows', 'last_activity_at' => now()]);
    }

    asAdmin()->postJson("/api/admin/users/{$user->id}/logout-all")->assertNoContent();

    expect($user->sessions()->count())->toBe(0);
});

it('creates an account for a Telegram subscriber with a subscription and a set-password email', function () {
    Notification::fake();
    $plan = Plan::factory()->create(['months' => 6]);

    $response = asAdmin()->postJson('/api/admin/users', [
        'name' => 'Sara', 'email' => 'sara@example.com', 'plan_id' => $plan->id, 'note' => 'Telegram subscriber',
    ])->assertCreated();

    $user = User::findOrFail($response->json('data.id'));
    expect($user->password)->toBeNull()->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->hasActiveSubscription())->toBeTrue()->and($user->subscriptions()->first()->note)->toBe('Telegram subscriber');
    Notification::assertSentTo($user, AccountCreated::class);
});

it('creates a phone-only account without sending email', function () {
    Notification::fake();

    asAdmin()->postJson('/api/admin/users', ['name' => 'Ali', 'phone' => '09123456789'])->assertCreated()
        ->assertJsonPath('data.phone', '+989123456789');

    Notification::assertNothingSent();
});

it('needs an email or a phone and rejects duplicates', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    asAdmin()->postJson('/api/admin/users', ['name' => 'X'])->assertUnprocessable()->assertJsonValidationErrors(['email', 'phone']);
    asAdmin()->postJson('/api/admin/users', ['name' => 'X', 'email' => 'taken@example.com'])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('lets the new subscriber choose a password with the emailed link', function () {
    $mail = null;
    Notification::fake();
    asAdmin()->postJson('/api/admin/users', ['name' => 'Sara', 'email' => 'sara@example.com'])->assertCreated();
    $user = User::firstWhere('email', 'sara@example.com');
    Notification::assertSentTo($user, AccountCreated::class, function (AccountCreated $notification) use ($user, &$mail) {
        $mail = $notification->toMail($user);

        return true;
    });

    parse_str(parse_url(str_replace('#', '', $mail->actionUrl), PHP_URL_QUERY), $query);
    expect($mail->actionUrl)->toContain('/#/reset-password?token=')->toContain('welcome=1');

    $this->postJson('/api/reset-password', [
        'token' => $query['token'], 'email' => 'sara@example.com', 'password' => 'my-new-pass-1', 'password_confirmation' => 'my-new-pass-1',
    ])->assertOk();
    $this->postJson('/api/login', ['email' => 'sara@example.com', 'password' => 'my-new-pass-1'])->assertOk();
});

it('can resend the set-password link', function () {
    Notification::fake();
    $user = User::factory()->create(['password' => null]);

    asAdmin()->postJson("/api/admin/users/{$user->id}/send-password-link")->assertOk();

    Notification::assertSentTo($user, AccountCreated::class);
});
