<?php

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Plan */
class PlanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'months' => $this->months,
            'active' => $this->active,
            'sort_order' => $this->sort_order,
            'prices' => $this->prices->map(fn ($price) => [
                'currency' => $price->currency,
                'amount' => $price->amount,
            ])->values(),
        ];
    }
}
