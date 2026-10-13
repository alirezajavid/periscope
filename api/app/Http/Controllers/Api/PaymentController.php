<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return PaymentResource::collection(
            $request->user()->payments()->with(['plan.prices', 'method'])->latest()->paginate(20)
        );
    }

    public function store(StorePaymentRequest $request, PaymentService $payments): PaymentResource
    {
        $payment = $payments->submit(
            $request->user(),
            Plan::findOrFail($request->validated('plan_id')),
            PaymentMethod::findOrFail($request->validated('payment_method_id')),
            $request->validated('tracking_code'),
            $request->file('receipt'),
        );

        return new PaymentResource($payment->load(['plan.prices', 'method']));
    }
}
