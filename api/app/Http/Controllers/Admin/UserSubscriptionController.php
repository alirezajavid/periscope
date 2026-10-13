<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GrantSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\AdminLog;
use App\Models\Plan;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Support\Carbon;

class UserSubscriptionController extends Controller
{
    /** Manual subscription (Telegram subscribers, gifts, compensation). */
    public function store(GrantSubscriptionRequest $request, User $user, SubscriptionService $subscriptions): SubscriptionResource
    {
        $startsAt = $request->validated('starts_at') ? Carbon::parse($request->validated('starts_at')) : null;

        $subscription = $subscriptions->grant(
            $user,
            Plan::findOrFail($request->validated('plan_id')),
            $startsAt,
            grantedBy: $request->user(),
            note: $request->validated('note'),
        );
        AdminLog::record('subscription.grant', $user, ['subscription_id' => $subscription->id]);

        return new SubscriptionResource($subscription->load('plan.prices'));
    }
}
