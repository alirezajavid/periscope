<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /** Current status: whether the user is a subscriber now, until when, and what is queued after. */
    public function __invoke(Request $request): JsonResponse
    {
        $subscriptions = $request->user()->subscriptions()->with('plan')
            ->where('ends_at', '>', now())->orderBy('starts_at')->get();

        $current = $subscriptions->first(fn ($s) => $s->starts_at <= now());

        return response()->json([
            'active' => $current !== null,
            'ends_at' => $subscriptions->max('ends_at'),
            'subscriptions' => SubscriptionResource::collection($subscriptions),
        ]);
    }
}
