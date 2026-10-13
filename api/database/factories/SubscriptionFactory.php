<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subscription> */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonths(2),
        ];
    }

    public function expired(): static
    {
        return $this->state(['starts_at' => now()->subMonths(4), 'ends_at' => now()->subMonth()]);
    }
}
