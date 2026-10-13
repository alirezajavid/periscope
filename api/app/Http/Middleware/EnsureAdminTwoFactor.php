<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Admin routes: the user must be an active admin who passed the TOTP step in this session. */
class EnsureAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isAdmin() || $user->isBlocked()) {
            return response()->json(['message' => __('auth.admin_only')], 403);
        }

        if ($user->two_factor_confirmed_at === null || ! $request->session()->get('admin_two_factor_passed')) {
            return response()->json(['message' => __('auth.two_factor_required')], 401);
        }

        return $next($request);
    }
}
