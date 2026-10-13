<?php

use App\Models\LoginEvent;
use App\Models\OtpCode;
use App\Models\User;
use App\Support\Phone;
use App\Support\SmsSender;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->withHeader('Referer', 'http://localhost');
    RateLimiter::clear('login');

    // Capture the code the SMS would carry.
    $this->smsCode = null;
    $this->mock(SmsSender::class, function ($mock) {
        $mock->shouldReceive('send')->andReturnUsing(function (string $phone, string $message) {
            preg_match('/\d{6}/', $message, $match);
            $this->smsCode = $match[0];
        });
    });
});

it('normalises phone numbers to E.164', function (string $input, string $expected) {
    expect(Phone::normalize($input))->toBe($expected);
})->with([
    ['09123456789', '+989123456789'],
    ['۰۹۱۲۳۴۵۶۷۸۹', '+989123456789'],
    ['9123456789', '+989123456789'],
    ['0098 912 345 6789', '+989123456789'],
    ['+44 7700 900123', '+447700900123'],
]);

it('sends a code and signs a new phone number up', function () {
    $this->postJson('/api/otp/send', ['phone' => '09123456789'])->assertOk();
    expect($this->smsCode)->toMatch('/^\d{6}$/');

    $this->postJson('/api/otp/login', ['phone' => '09123456789', 'code' => $this->smsCode, 'name' => 'Sara'])
        ->assertCreated()
        ->assertJsonPath('data.phone', '+989123456789')
        ->assertJsonPath('data.phone_verified', true);

    $user = User::firstWhere('phone', '+989123456789');
    expect($user->email)->toBeNull()
        ->and($user->sessions()->count())->toBe(1)
        ->and(LoginEvent::where('user_id', $user->id)->where('method', 'phone')->where('successful', true)->exists())->toBeTrue();
});

it('signs an existing phone user in without needing a name', function () {
    $user = User::factory()->create(['phone' => '+989123456789', 'email' => null]);

    $this->postJson('/api/otp/send', ['phone' => '09123456789']);
    $this->postJson('/api/otp/login', ['phone' => '09123456789', 'code' => $this->smsCode])
        ->assertOk()->assertJsonPath('data.id', $user->id);
});

it('asks for a name on first sign-up without spending the code', function () {
    $this->postJson('/api/otp/send', ['phone' => '09123456789']);

    $this->postJson('/api/otp/login', ['phone' => '09123456789', 'code' => $this->smsCode])
        ->assertUnprocessable()->assertJsonValidationErrors('name');

    $this->postJson('/api/otp/login', ['phone' => '09123456789', 'code' => $this->smsCode, 'name' => 'Sara'])
        ->assertCreated();
});

it('rejects wrong, expired and reused codes', function () {
    $this->postJson('/api/otp/send', ['phone' => '09123456789']);
    $good = $this->smsCode;
    $wrong = $good === '111111' ? '222222' : '111111';

    $this->postJson('/api/otp/login', ['phone' => '09123456789', 'code' => $wrong, 'name' => 'Sara'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');

    $this->postJson('/api/otp/login', ['phone' => '09123456789', 'code' => $good, 'name' => 'Sara'])->assertCreated();
    $this->postJson('/api/logout');

    // Reuse of a spent code
    $this->postJson('/api/otp/login', ['phone' => '09123456789', 'code' => $good, 'name' => 'Sara'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');

    // Expired code
    $this->travel(OtpCode::LIFETIME_MINUTES + 1)->minutes();
    $this->postJson('/api/otp/send', ['phone' => '09120000000']);
    $fresh = $this->smsCode;
    $this->travel(OtpCode::LIFETIME_MINUTES + 1)->minutes();
    $this->postJson('/api/otp/login', ['phone' => '09120000000', 'code' => $fresh, 'name' => 'Ali'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');
});

it('locks a code after too many wrong guesses', function () {
    $this->postJson('/api/otp/send', ['phone' => '09123456789']);
    $good = $this->smsCode;
    $wrong = $good === '111111' ? '222222' : '111111';

    foreach (range(1, OtpCode::MAX_ATTEMPTS) as $i) {
        $this->postJson('/api/otp/login', ['phone' => '09123456789', 'code' => $wrong, 'name' => 'Sara'])
            ->assertUnprocessable();
    }

    $this->travel(61)->seconds(); // clear the request throttle; the code itself stays locked
    $this->postJson('/api/otp/login', ['phone' => '09123456789', 'code' => $good, 'name' => 'Sara'])
        ->assertUnprocessable();
    expect(User::where('phone', '+989123456789')->exists())->toBeFalse();
});

it('does not send another code within the cooldown', function () {
    $this->postJson('/api/otp/send', ['phone' => '09123456789'])->assertOk();
    $this->postJson('/api/otp/send', ['phone' => '09123456789'])->assertStatus(429);

    $this->travel(OtpCode::RESEND_AFTER_SECONDS + 1)->seconds();
    $this->postJson('/api/otp/send', ['phone' => '09123456789'])->assertOk();
});

it('does not let a blocked user in by phone', function () {
    User::factory()->blocked()->create(['phone' => '+989123456789', 'email' => null]);

    $this->postJson('/api/otp/send', ['phone' => '09123456789']);
    $this->postJson('/api/otp/login', ['phone' => '09123456789', 'code' => $this->smsCode])->assertForbidden();
});

it('rejects an invalid phone number', function () {
    $this->postJson('/api/otp/send', ['phone' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors('phone');
});
