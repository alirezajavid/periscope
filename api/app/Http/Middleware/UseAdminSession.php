<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives /api/admin/* its own session cookie and a short lifetime so an admin
 * login can never be mixed up with (or ride on) a subscriber's session.
 * Must run before the session middleware, hence it is a global middleware.
 */
class UseAdminSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/admin', 'api/admin/*')) {
            config([
                'session.cookie' => 'periscope_admin_session',
                'session.lifetime' => config('periscope.admin_idle_timeout'),
                'session.expire_on_close' => true,
            ]);
        }

        return $next($request);
    }
}
