<?php

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->plan = Plan::factory()->withPrices(['IRT' => 300000, 'USD' => 10])->create(['months' => 3]);
    $this->card = PaymentMethod::factory()->create(); // IRT
});

function submission(array $overrides = []): array
{
    return array_merge([
        'plan_id' => test()->plan->id,
        'payment_method_id' => test()->card->id,
        'tracking_code' => '1234567890',
        'receipt' => UploadedFile::fake()->image('receipt.jpg'),
    ], $overrides);
}

it('lists only active plans with their prices to anyone', function () {
    Plan::factory()->withPrices()->create(['active' => false]);

    $this->getJson('/api/plans')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->plan->id)
        ->assertJsonCount(2, 'data.0.prices');
});

it('records a payment with the price taken from the plan, not from the client', function () {
    $user = User::factory()->create();

    asUser($user)->postJson('/api/payments', submission(['amount' => 1]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.amount', '300000.00')
        ->assertJsonPath('data.currency', 'IRT');

    $payment = Payment::firstWhere('user_id', $user->id);
    Storage::disk('local')->assertExists($payment->receipt_path);
});

it('rejects a payment without a tracking code or receipt image', function () {
    asUser()->postJson('/api/payments', submission(['tracking_code' => '']))
        ->assertUnprocessable()->assertJsonValidationErrors('tracking_code');
    asUser()->postJson('/api/payments', submission(['receipt' => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf')]))
        ->assertUnprocessable()->assertJsonValidationErrors('receipt');
});

it('rejects a method whose currency the plan has no price for', function () {
    $crypto = PaymentMethod::factory()->create(['type' => 'crypto', 'currency' => 'USDT']);

    asUser()->postJson('/api/payments', submission(['payment_method_id' => $crypto->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('payment_method_id');
});

it('allows only one pending payment per user', function () {
    $user = User::factory()->create();

    asUser($user)->postJson('/api/payments', submission())->assertCreated();
    asUser($user)->postJson('/api/payments', submission(['receipt' => UploadedFile::fake()->image('b.jpg')]))
        ->assertUnprocessable()->assertJsonValidationErrors('plan_id');
});

it('does not accept inactive plans or methods', function () {
    $this->plan->update(['active' => false]);

    asUser()->postJson('/api/payments', submission())->assertUnprocessable()->assertJsonValidationErrors('plan_id');
});

it('requires a signed-in user', function () {
    $this->postJson('/api/payments', submission())->assertUnauthorized();
    $this->getJson('/api/payment-methods')->assertUnauthorized();
});

it('shows only the user\'s own payments', function () {
    $user = User::factory()->create();
    Payment::factory()->create(['user_id' => $user->id]);
    Payment::factory()->create();

    asUser($user)->getJson('/api/payments')->assertOk()->assertJsonCount(1, 'data');
});

it('reports subscription status', function () {
    $user = User::factory()->create();
    asUser($user)->getJson('/api/subscription')->assertOk()->assertJsonPath('active', false);

    Subscription::factory()->for($user)->create(['starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
    asUser($user)->getJson('/api/subscription')->assertOk()->assertJsonPath('active', true);
});

it('counts a subscriber only between the start and end dates', function (string $start, string $end, bool $expected) {
    $user = User::factory()->create();
    Subscription::factory()->for($user)->create(['starts_at' => now()->modify($start), 'ends_at' => now()->modify($end)]);

    expect($user->hasActiveSubscription())->toBe($expected);
})->with([
    'running' => ['-1 day', '+1 day', true],
    'ended' => ['-2 months', '-1 minute', false],
    'not started yet' => ['+1 day', '+2 months', false],
]);
