<?php

use App\Enums\PaymentStatus;
use App\Models\AdminLog;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PaymentApproved;
use App\Notifications\PaymentRejected;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Notification::fake();
    $this->payment = Payment::factory()->create([
        'plan_id' => Plan::factory()->withPrices()->create(['months' => 3]),
    ]);
});

it('keeps non-admins and anonymous users out', function () {
    $this->getJson('/api/admin/payments')->assertUnauthorized();
    asUser()->getJson('/api/admin/payments')->assertForbidden();
    asUser()->postJson("/api/admin/payments/{$this->payment->id}/approve")->assertForbidden();
});

it('lists the queue and filters by status', function () {
    Payment::factory()->create(['status' => 'approved']);

    asAdmin()->getJson('/api/admin/payments?status=pending')->assertOk()->assertJsonCount(1, 'data');
    asAdmin()->getJson('/api/admin/payments')->assertOk()->assertJsonCount(2, 'data');
});

it('approves a payment: activates a 3 month subscription starting now and emails the user', function () {
    $this->freezeTime();
    $admin = User::factory()->admin()->withTwoFactor()->create();

    asAdmin($admin)->postJson("/api/admin/payments/{$this->payment->id}/approve")
        ->assertOk()->assertJsonPath('data.status', 'approved');

    $subscription = Subscription::firstWhere('user_id', $this->payment->user_id);
    expect($subscription->payment_id)->toBe($this->payment->id)
        ->and($subscription->granted_by)->toBe($admin->id)
        ->and($subscription->starts_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($subscription->ends_at->toDateTimeString())->toBe(now()->addMonthsNoOverflow(3)->toDateTimeString())
        ->and($this->payment->fresh()->reviewed_by)->toBe($admin->id)
        ->and($this->payment->user->hasActiveSubscription())->toBeTrue()
        ->and(AdminLog::where('action', 'payment.approve')->where('admin_id', $admin->id)->exists())->toBeTrue();

    Notification::assertSentTo($this->payment->user, PaymentApproved::class);
});

it('starts a renewal when the current subscription ends', function () {
    $current = Subscription::factory()->for($this->payment->user)->create([
        'starts_at' => now()->subMonth(), 'ends_at' => now()->addDays(10),
    ]);

    asAdmin()->postJson("/api/admin/payments/{$this->payment->id}/approve")->assertOk();

    $renewal = Subscription::where('payment_id', $this->payment->id)->first();
    expect($renewal->starts_at->equalTo($current->ends_at))->toBeTrue();
});

it('lets the admin pick the start date', function () {
    asAdmin()->postJson("/api/admin/payments/{$this->payment->id}/approve", ['starts_at' => '2027-01-01 00:00:00'])->assertOk();

    expect(Subscription::where('payment_id', $this->payment->id)->first()->starts_at->toDateString())->toBe('2027-01-01');
});

it('rejects with a reason, tells the user and creates no subscription', function () {
    asAdmin()->postJson("/api/admin/payments/{$this->payment->id}/reject", ['reason' => 'Receipt unreadable'])
        ->assertOk()->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.reject_reason', 'Receipt unreadable');

    expect(Subscription::count())->toBe(0);
    Notification::assertSentTo($this->payment->user, PaymentRejected::class, fn ($n) => $n->reason === 'Receipt unreadable');
    expect(AdminLog::where('action', 'payment.reject')->exists())->toBeTrue();
});

it('requires a reason to reject', function () {
    asAdmin()->postJson("/api/admin/payments/{$this->payment->id}/reject")
        ->assertUnprocessable()->assertJsonValidationErrors('reason');
});

it('refuses to review a payment twice', function () {
    asAdmin()->postJson("/api/admin/payments/{$this->payment->id}/approve")->assertOk();

    asAdmin()->postJson("/api/admin/payments/{$this->payment->id}/approve")->assertStatus(409);
    asAdmin()->postJson("/api/admin/payments/{$this->payment->id}/reject", ['reason' => 'x'])->assertStatus(409);
    expect(Subscription::where('payment_id', $this->payment->id)->count())->toBe(1)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Approved);
});

it('serves the receipt to admins only', function () {
    Storage::fake('local');
    Storage::disk('local')->put('receipts/example.jpg', 'fake-image');

    asAdmin()->get("/api/admin/payments/{$this->payment->id}/receipt")->assertOk();
    asUser()->getJson("/api/admin/payments/{$this->payment->id}/receipt")->assertForbidden();
});

it('grants a manual subscription and logs it', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['months' => 12]);

    asAdmin()->postJson("/api/admin/users/{$user->id}/subscriptions", ['plan_id' => $plan->id, 'note' => 'Telegram subscriber'])
        ->assertCreated();

    $subscription = $user->subscriptions()->first();
    expect($subscription->payment_id)->toBeNull()
        ->and($subscription->note)->toBe('Telegram subscriber')
        ->and($user->hasActiveSubscription())->toBeTrue()
        ->and(AdminLog::where('action', 'subscription.grant')->exists())->toBeTrue();
});

it('creates and updates plans with prices', function () {
    $created = asAdmin()->postJson('/api/admin/plans', [
        'title' => 'Three months', 'months' => 3, 'active' => true, 'sort_order' => 1,
        'prices' => [['currency' => 'IRT', 'amount' => 250000], ['currency' => 'USD', 'amount' => 8]],
    ])->assertCreated()->assertJsonCount(2, 'data.prices');

    asAdmin()->putJson('/api/admin/plans/'.$created->json('data.id'), [
        'title' => 'Three months', 'months' => 3, 'active' => false, 'prices' => [['currency' => 'IRT', 'amount' => 270000]],
    ])->assertOk()->assertJsonCount(1, 'data.prices')->assertJsonPath('data.active', false);
});

it('rejects duplicate currencies in a plan', function () {
    asAdmin()->postJson('/api/admin/plans', [
        'title' => 'X', 'months' => 3, 'prices' => [['currency' => 'IRT', 'amount' => 1], ['currency' => 'IRT', 'amount' => 2]],
    ])->assertUnprocessable();
});

it('manages payment methods', function () {
    $created = asAdmin()->postJson('/api/admin/payment-methods', [
        'type' => 'paypal', 'title' => 'PayPal', 'instructions' => 'paypal.me/periscope', 'currency' => 'USD',
    ])->assertCreated();

    asAdmin()->putJson('/api/admin/payment-methods/'.$created->json('data.id'), [
        'type' => 'paypal', 'title' => 'PayPal', 'instructions' => 'new text', 'currency' => 'USD', 'active' => false,
    ])->assertOk()->assertJsonPath('data.active', false);

    expect(PaymentMethod::count())->toBe(2); // the factory one from beforeEach + this one
});
