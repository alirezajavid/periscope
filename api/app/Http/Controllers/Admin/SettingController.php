<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mews\Purifier\Facades\Purifier;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Setting::allKnown()]);
    }

    /** Update any subset of the known settings. */
    public function update(Request $request): JsonResponse
    {
        $rules = config('periscope.settings');
        $input = $request->validate(array_merge(
            collect($rules)->mapWithKeys(fn ($rule, $key) => [$key => array_merge(['sometimes'], $rule)])->all(),
            [
                'social_links.*.title' => ['required', 'string', 'max:100'],
                'social_links.*.url' => ['required', 'url:http,https', 'max:500'],
                'support_items.*.title' => ['required', 'string', 'max:100'],
                'support_items.*.kind' => ['required', 'in:link,crypto'],
                'support_items.*.value' => ['required', 'string', 'max:500'],
                'support_items.*.network' => ['nullable', 'string', 'max:30'],
                'support_items.*.note' => ['nullable', 'string', 'max:500'],
            ],
        ));

        foreach (array_intersect_key($input, $rules) as $key => $value) {
            Setting::write($key, str_starts_with($key, 'page_') && $value !== null ? Purifier::clean($value) : $value);
        }
        AdminLog::record('settings.update', details: ['keys' => array_keys(array_intersect_key($input, $rules))]);

        return response()->json(['data' => Setting::allKnown()]);
    }
}
