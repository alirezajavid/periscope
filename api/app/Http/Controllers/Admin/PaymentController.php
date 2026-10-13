<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApprovePaymentRequest;
use App\Http\Requests\Admin\RejectPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\AdminLog;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    /** The approval queue; pending first by default. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $payments = Payment::with(['user', 'plan.prices', 'method'])
            ->when($request->enum('status', PaymentStatus::class), fn ($q, $status) => $q->where('status', $status))
            ->oldest()
            ->paginate(20);

        return PaymentResource::collection($payments);
    }

    public function show(Payment $payment): PaymentResource
    {
        return new PaymentResource($payment->load(['user', 'plan.prices', 'method']));
    }

    public function approve(ApprovePaymentRequest $request, Payment $payment, PaymentService $payments): PaymentResource
    {
        $startsAt = $request->validated('starts_at') ? Carbon::parse($request->validated('starts_at')) : null;

        $subscription = $payments->approve($payment, $request->user(), $startsAt);
        AdminLog::record('payment.approve', $payment, ['subscription_id' => $subscription->id]);

        return new PaymentResource($payment->refresh()->load(['user', 'plan.prices', 'method']));
    }

    public function reject(RejectPaymentRequest $request, Payment $payment, PaymentService $payments): PaymentResource
    {
        $payments->reject($payment, $request->user(), $request->validated('reason'));
        AdminLog::record('payment.reject', $payment, ['reason' => $request->validated('reason')]);

        return new PaymentResource($payment->refresh()->load(['user', 'plan.prices', 'method']));
    }

    public function receipt(Payment $payment): StreamedResponse
    {
        return Storage::disk('local')->response($payment->receipt_path);
    }
}
