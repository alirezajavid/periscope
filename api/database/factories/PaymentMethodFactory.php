<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentMethod> */
class PaymentMethodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => 'card',
            'title' => 'Card to card',
            'instructions' => 'Pay to card 6037-0000-0000-0000 and keep the receipt.',
            'currency' => 'IRT',
            'active' => true,
            'sort_order' => 0,
        ];
    }

    public function paypal(): static
    {
        return $this->state(['type' => 'paypal', 'title' => 'PayPal', 'currency' => 'USD']);
    }
}
