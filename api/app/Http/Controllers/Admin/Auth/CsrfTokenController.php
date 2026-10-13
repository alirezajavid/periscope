<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CsrfTokenController extends Controller
{
    /**
     * Admin and subscriber apps live on sibling subdomains, so they would share the
     * XSRF-TOKEN cookie. The admin app takes its token from this response instead
     * and sends it in the X-CSRF-TOKEN header.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json(['token' => csrf_token()]);
    }
}
