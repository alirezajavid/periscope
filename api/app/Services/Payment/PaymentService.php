<?php

namespace App\Services\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PaymentApproved;
use App\Notifications\PaymentRejected;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private SubscriptionService $subscriptions) {}

    /** A subscriber submits proof of a manual payment; the price comes from the plan, not the client. */
    public function submit(User $user, Plan $plan, PaymentMethod $method, string $trackingCode, UploadedFile $receipt): Payment
    {
        $price = $plan->prices()->where('currency', $method->currency)->first();

        if ($price === null) {
            throw ValidationException::withMessages(['payment_method_id' => __('payments.no_price')]);
        }

        if ($user->payments()->where('status', PaymentStatus::Pending)->exists()) {
            throw ValidationException::withMessages(['plan_id' => __('payments.already_pending')]);
        }

        return $user->payments()->create([
            'plan_id' => $plan->id,
            'payment_method_id' => $method->id,
            'amount' => $price->amount,
            'currency' => $price->currency,
            'tracking_code' => $trackingCode,
            'receipt_path' => $receipt->store('receipts', 'local'),
        ]);
    }

    /** Approve: activate the subscription (start date chosen by the admin) and email the user. */
    public function approve(Payment $payment, User $admin, ?Carbon $startsAt = null): Subscription
    {
        $subscription = DB::transaction(function () use ($payment, $admin, $startsAt) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            $this->ensurePending($payment);

            $payment->forceFill([
                'status' => PaymentStatus::Approved,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();

            return $this->subscriptions->grant($payment->user, $payment->plan, $startsAt, $payment, $admin);
        });

        $payment->refresh()->user->notify(new PaymentApproved($subscription));

        return $subscription;
    }

    public function reject(Payment $payment, User $admin, string $reason): void
    {
        DB::transaction(function () use ($payment, $admin, $reason) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            $this->ensurePending($payment);

            $payment->forceFill([
                'status' => PaymentStatus::Rejected,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'reject_reason' => $reason,
            ])->save();
        });

        $payment->refresh()->user->notify(new PaymentRejected($reason));
    }

    private function ensurePending(Payment $payment): void
    {
        abort_unless($payment->isPending(), 409, __('payments.already_reviewed'));
    }
}
