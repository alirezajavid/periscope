<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->words(2, true),
            'months' => fake()->randomElement([3, 6, 12]),
            'active' => true,
            'sort_order' => 0,
        ];
    }

    /** Create the plan together with prices in the given currencies. */
    public function withPrices(array $amounts = ['IRT' => 300000, 'USD' => 10]): static
    {
        return $this->afterCreating(function (Plan $plan) use ($amounts) {
            foreach ($amounts as $currency => $amount) {
                $plan->prices()->create(['currency' => Currency::from($currency), 'amount' => $amount]);
            }
        });
    }
}
