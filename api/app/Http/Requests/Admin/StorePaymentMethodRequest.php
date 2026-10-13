<?php

namespace App\Http\Requests\Admin;

use App\Enums\Currency;
use App\Enums\PaymentMethodType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(PaymentMethodType::class)],
            'title' => ['required', 'string', 'max:100'],
            'instructions' => ['required', 'string', 'max:5000'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
