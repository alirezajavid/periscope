<?php

use App\Models\User;
use App\Support\SmsSender;

beforeEach(function () {
    $this->withHeader('Referer', 'http://localhost');
    $this->device = ['browser' => 'Other', 'os' => 'Other'];

    $this->smsCode = null;
    $this->mock(SmsSender::class, function ($mock) {
        $mock->shouldReceive('send')->andReturnUsing(function (string $phone, string $message) {
            preg_match('/\d{6}/', $message, $match);
            $this->smsCode = $match[0];
        });
    });
});

it('attaches and verifies a phone number for a signed-in user', function () {
    $user = User::factory()->create();
    $session = fn () => $this->actingAs($user)->withSession(['device' => $this->device]);

    $session()->postJson('/api/phone', ['phone' => '09123456789'])->assertOk();
    $session()->postJson('/api/phone/verify', ['phone' => '09123456789', 'code' => $this->smsCode])
        ->assertOk()->assertJsonPath('data.phone_verified', true);

    expect($user->fresh()->phone)->toBe('+989123456789');
});

it('refuses a phone number that belongs to another user', function () {
    User::factory()->create(['phone' => '+989123456789']);
    $user = User::factory()->create();

    $this->actingAs($user)->withSession(['device' => $this->device])
        ->postJson('/api/phone', ['phone' => '09123456789'])
        ->assertUnprocessable()->assertJsonValidationErrors('phone');
});

it('requires authentication', function () {
    $this->postJson('/api/phone', ['phone' => '09123456789'])->assertUnauthorized();
});
