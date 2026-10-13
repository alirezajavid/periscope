<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Signs the admin out after `periscope.admin_idle_timeout` minutes without a request. */
class AdminIdleTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();
        $last = $session->get('admin_last_activity');

        if ($last !== null && now()->timestamp - $last > config('periscope.admin_idle_timeout') * 60) {
            Auth::guard('web')->logout();
            $session->invalidate();

            return response()->json(['message' => __('auth.admin_idle')], 401);
        }

        $session->put('admin_last_activity', now()->timestamp);

        return $next($request);
    }
}
