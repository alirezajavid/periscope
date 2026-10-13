<?php

it('counts each throttled route separately', function () {
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/forgot-password', ['email' => "nobody{$i}@example.com"])->assertOk();
    }
    $this->postJson('/api/forgot-password', ['email' => 'nobody6@example.com'])->assertStatus(429);

    // Exhausting one route must not lock the others (they used to share a single counter).
    $this->postJson('/api/reset-password', ['token' => 'x', 'email' => 'a@example.com', 'password' => 'Aa1aaaaa', 'password_confirmation' => 'Aa1aaaaa'])
        ->assertUnprocessable();
    $this->postJson('/api/register', [])->assertUnprocessable();
});
