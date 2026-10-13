<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'method' => new PaymentMethodResource($this->whenLoaded('method')),
            'user' => new UserResource($this->whenLoaded('user')),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'tracking_code' => $this->tracking_code,
            'status' => $this->status,
            'reject_reason' => $this->reject_reason,
            'reviewed_at' => $this->reviewed_at,
            'created_at' => $this->created_at,
        ];
    }
}
