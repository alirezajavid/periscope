<?php

namespace App\Http\Requests\Admin;

use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'months' => ['required', 'integer', 'between:1,36'],
            'active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.currency' => ['required', Rule::enum(Currency::class), 'distinct'],
            'prices.*.amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
