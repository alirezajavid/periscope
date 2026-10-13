<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class AdminUserResource extends JsonResource
{
    /** @return array<int|string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'status' => $this->status,
            'preferred_mirror' => $this->preferred_mirror,
            'notify_new_episode' => $this->notify_new_episode,
            'email_verified_at' => $this->email_verified_at,
            'phone_verified_at' => $this->phone_verified_at,
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
            'subscribed' => $this->hasActiveSubscription(),
            // Detail view only: the 128-bit id embedded in this user's audio, and their history.
            $this->mergeWhen($this->relationLoaded('subscriptions'), fn () => [
                'watermark_payload' => $this->watermark_payload,
                'subscriptions' => SubscriptionResource::collection($this->subscriptions),
                'payments' => PaymentResource::collection($this->payments),
                'sessions' => $this->sessions->map(fn ($session) => [
                    'id' => $session->id,
                    'browser' => $session->browser_family,
                    'os' => $session->os_family,
                    'ip' => $session->ip,
                    'last_activity_at' => $session->last_activity_at,
                ]),
                'login_events' => $this->loginEvents->map(fn ($event) => [
                    'method' => $event->method,
                    'successful' => $event->successful,
                    'ip' => $event->ip,
                    'user_agent' => $event->user_agent,
                    'created_at' => $event->created_at,
                ]),
            ]),
        ];
    }
}
