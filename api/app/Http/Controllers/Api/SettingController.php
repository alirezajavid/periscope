<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\QrCode;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    /** Public content: links, about/terms/install pages and the maintenance switch. */
    public function index(): JsonResponse
    {
        return response()->json(['data' => collect(Setting::allKnown())->except('support_items')]);
    }

    /** Ways to support the podcast; crypto addresses come with a ready QR code. */
    public function support(): JsonResponse
    {
        $items = collect(Setting::read('support_items') ?? [])->map(fn (array $item) => $item + [
            'qr_svg' => ($item['kind'] ?? null) === 'crypto' ? QrCode::svg($item['value']) : null,
        ]);

        return response()->json(['data' => $items->values()]);
    }
}
