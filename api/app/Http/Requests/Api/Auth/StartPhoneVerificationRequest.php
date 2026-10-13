<?php

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\Api\Auth\Concerns\NormalizesPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartPhoneVerificationRequest extends FormRequest
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
            'phone' => [
                'required', 'string', 'regex:/^\+[1-9]\d{7,14}$/',
                Rule::unique('users', 'phone')->ignore($this->user()?->id),
            ],
        ];
    }
}
