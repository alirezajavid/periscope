<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_id' => Plan::factory()->withPrices(),
            'payment_method_id' => PaymentMethod::factory(),
            'amount' => 300000,
            'currency' => 'IRT',
            'tracking_code' => (string) fake()->numerify('##########'),
            'receipt_path' => 'receipts/example.jpg',
            'status' => 'pending',
        ];
    }
}
