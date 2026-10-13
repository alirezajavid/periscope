<?php

use App\Enums\UserRole;
use App\Models\User;

it('promotes an existing user to admin', function () {
    $user = User::factory()->create();

    $this->artisan('periscope:make-admin', ['email' => $user->email])->assertSuccessful();

    expect($user->fresh()->role)->toBe(UserRole::Admin);
});

it('creates a new verified admin', function () {
    $this->artisan('periscope:make-admin', [
        'email' => 'boss@example.com', '--name' => 'Boss', '--password' => 'secret-pass-1',
    ])->assertSuccessful();

    $admin = User::firstWhere('email', 'boss@example.com');
    expect($admin->role)->toBe(UserRole::Admin)
        ->and($admin->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('secret-pass-1', $admin->password))->toBeTrue();
});
