<?php

namespace App\Models;

use App\Enums\Currency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['plan_id', 'currency', 'amount'])]
class PlanPrice extends Model
{
    protected function casts(): array
    {
        return ['currency' => Currency::class, 'amount' => 'decimal:2'];
    }
}
