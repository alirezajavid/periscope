<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentMethodType;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['type', 'title', 'instructions', 'currency', 'active', 'sort_order'])]
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => PaymentMethodType::class,
            'currency' => Currency::class,
            'active' => 'boolean',
        ];
    }

    /** @param Builder<PaymentMethod> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true)->orderBy('sort_order');
    }
}
