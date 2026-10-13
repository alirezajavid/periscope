<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentMethodRequest;
use App\Http\Requests\Admin\UpdatePaymentMethodRequest;
use App\Http\Resources\PaymentMethodResource;
use App\Models\AdminLog;
use App\Models\PaymentMethod;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentMethodController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PaymentMethodResource::collection(PaymentMethod::orderBy('sort_order')->get());
    }

    public function store(StorePaymentMethodRequest $request): PaymentMethodResource
    {
        $method = PaymentMethod::create($request->validated());
        AdminLog::record('payment_method.create', $method);

        return new PaymentMethodResource($method);
    }

    /** Methods are deactivated (`active: false`), not deleted, because payments reference them. */
    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): PaymentMethodResource
    {
        $paymentMethod->update($request->validated());
        AdminLog::record('payment_method.update', $paymentMethod);

        return new PaymentMethodResource($paymentMethod);
    }
}
