<?php

namespace App\Services\Subscription;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;

class SubscriptionService
{
    /** A renewal starts when the current subscription ends; with none running, right now. */
    public function defaultStartFor(User $user): Carbon
    {
        $latestEnd = $user->subscriptions()->where('ends_at', '>', now())->max('ends_at');

        return $latestEnd ? Carbon::parse($latestEnd) : now();
    }

    public function grant(
        User $user,
        Plan $plan,
        ?Carbon $startsAt = null,
        ?Payment $payment = null,
        ?User $grantedBy = null,
        ?string $note = null,
    ): Subscription {
        $startsAt ??= $this->defaultStartFor($user);

        return $user->subscriptions()->create([
            'plan_id' => $plan->id,
            'payment_id' => $payment?->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMonthsNoOverflow($plan->months),
            'granted_by' => $grantedBy?->id,
            'note' => $note,
        ]);
    }
}
