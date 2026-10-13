<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\AdminLog;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserSession;
use App\Notifications\AccountCreated;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->when($request->string('q')->trim()->value(), function ($query, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like));
            })
            ->when($request->enum('status', UserStatus::class), fn ($q, $status) => $q->where('status', $status))
            ->when($request->enum('role', UserRole::class), fn ($q, $role) => $q->where('role', $role))
            ->when($request->has('subscribed'), fn ($q) => $request->boolean('subscribed')
                ? $q->whereHas('subscriptions', fn ($s) => $s->current())
                : $q->whereDoesntHave('subscriptions', fn ($s) => $s->current()))
            ->latest('id')->paginate(30);

        return AdminUserResource::collection($users);
    }

    public function show(User $user): AdminUserResource
    {
        return new AdminUserResource($user->load([
            'subscriptions.plan.prices', 'payments.plan.prices', 'payments.method', 'sessions',
            'loginEvents' => fn ($q) => $q->latest('id')->limit(20),
        ]));
    }

    /** Create an account for someone who does not have one (e.g. a Telegram subscriber) and email them a set-password link. */
    public function store(StoreUserRequest $request, SubscriptionService $subscriptions): JsonResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'phone']));
        // The admin vouches for the address/number; no password exists until the person sets one.
        $user->forceFill([
            'email_verified_at' => $user->email ? now() : null,
            'phone_verified_at' => $user->phone ? now() : null,
        ])->save();

        if ($request->validated('plan_id')) {
            $startsAt = $request->validated('starts_at') ? Carbon::parse($request->validated('starts_at')) : null;
            $subscriptions->grant($user, Plan::findOrFail($request->validated('plan_id')), $startsAt, grantedBy: $request->user(), note: $request->validated('note'));
        }

        $user->email && $user->notify(new AccountCreated);
        AdminLog::record('user.create', $user);

        return (new AdminUserResource($user))->response()->setStatusCode(201);
    }

    /** Send the set-password link again (the first one expires). */
    public function sendPasswordLink(User $user): JsonResponse
    {
        abort_if($user->email === null, 422, __('users.no_email'));

        $user->notify(new AccountCreated);
        AdminLog::record('user.send_password_link', $user);

        return response()->json(['message' => __('users.link_sent')]);
    }

    public function block(Request $request, User $user): AdminUserResource
    {
        abort_if($user->is($request->user()), 422, __('users.cannot_block_self'));

        $user->forceFill(['status' => UserStatus::Blocked])->save();
        UserSession::destroyFor($user);
        AdminLog::record('user.block', $user);

        return new AdminUserResource($user);
    }

    public function unblock(User $user): AdminUserResource
    {
        $user->forceFill(['status' => UserStatus::Active])->save();
        AdminLog::record('user.unblock', $user);

        return new AdminUserResource($user);
    }

    public function logoutAll(User $user): JsonResponse
    {
        UserSession::destroyFor($user);
        AdminLog::record('user.logout_all', $user);

        return response()->json(null, 204);
    }
}
