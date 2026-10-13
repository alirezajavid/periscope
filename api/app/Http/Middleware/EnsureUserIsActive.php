<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Kicks out a user an admin has blocked while they still hold a session. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isBlocked()) {
            UserSession::where('session_id', $request->session()->getId())->delete();
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return response()->json(['message' => __('auth.blocked')], 403);
        }

        return $next($request);
    }
}
