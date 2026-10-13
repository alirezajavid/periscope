<?php

namespace App\Models;

use App\Enums\Currency;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'months', 'active', 'sort_order'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return HasMany<PlanPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    /** @param Builder<Plan> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true)->orderBy('sort_order')->orderBy('months');
    }

    public function priceIn(Currency $currency): ?PlanPrice
    {
        return $this->prices->firstWhere('currency', $currency);
    }
}
