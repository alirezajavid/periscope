<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminLogoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        AdminLog::record('admin.logout');

        Auth::guard('web')->logout();
        $request->session()->invalidate();

        return response()->json(null, 204);
    }
}
