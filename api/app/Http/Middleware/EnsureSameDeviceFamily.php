<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Invalidates the session when the browser family or OS family differs from
 * the one the session was created with, and refreshes the session registry.
 */
class EnsureSameDeviceFamily
{
    public function handle(Request $request, Closure $next): Response
    {
        // Public endpoints also serve clients without a session (feed readers, curl).
        if (! $request->hasSession()) {
            return $next($request);
        }

        $stored = $request->session()->get('device');
        $current = UserSession::families($request->userAgent());

        if ($stored !== null && $stored !== $current) {
            UserSession::where('session_id', $request->session()->getId())->delete();
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => __('auth.device_changed')], 401);
        }

        UserSession::where('session_id', $request->session()->getId())
            ->where('last_activity_at', '<', now()->subMinutes(5))
            ->update(['last_activity_at' => now()]);

        return $next($request);
    }
}
