<?php

namespace App\Http\Requests\Api\Auth\Concerns;

use App\Support\Phone;

trait NormalizesPhone
{
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => Phone::normalize($this->input('phone'))]);
    }
}
