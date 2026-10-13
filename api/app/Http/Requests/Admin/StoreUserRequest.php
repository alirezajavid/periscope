<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Api\Auth\Concerns\NormalizesPhone;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    use NormalizesPhone;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'required_without:phone', 'string', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['nullable', 'required_without:email', 'string', 'regex:/^\+[1-9]\d{7,14}$/', 'unique:users,phone'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'starts_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
