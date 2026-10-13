<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeakCheckResult;
use App\Enums\LeakCheckType;
use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\LeakCheck;
use App\Models\User;
use App\Services\TextFingerprint\TextFingerprint;
use App\Services\Watermark\AudioWatermarker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LeakCheckController extends Controller
{
    /** History of checks, newest first. */
    public function index(): JsonResponse
    {
        $checks = LeakCheck::with(['foundUser:id,name,email', 'admin:id,name'])->latest('id')->paginate(30);

        return response()->json($checks);
    }

    /** Paste text that leaked; finds the subscribers whose invisible mark it carries. */
    public function text(Request $request, TextFingerprint $fingerprint): JsonResponse
    {
        $text = $request->validate(['text' => ['required', 'string', 'max:200000']])['text'];

        return $this->report($request, LeakCheckType::Text, Str::limit($text, 200, ''), $fingerprint->identify($text));
    }

    /** Upload a leaked recording; reads the audiowmark payload and looks the user up. */
    public function audio(Request $request, AudioWatermarker $watermarker): JsonResponse
    {
        $file = $request->validate(['audio' => ['required', 'file', 'max:307200']])['audio'];

        $payload = $watermarker->extract($file->getRealPath());
        $users = $payload ? User::where('watermark_payload', $payload)->get() : collect();

        return $this->report($request, LeakCheckType::Audio, $file->getClientOriginalName(), $users);
    }

    /** @param Collection<int, User> $users */
    private function report(Request $request, LeakCheckType $type, string $subject, Collection $users): JsonResponse
    {
        $result = match ($users->count()) {
            0 => LeakCheckResult::NotFound,
            1 => LeakCheckResult::Found,
            default => LeakCheckResult::Ambiguous,
        };

        $check = LeakCheck::create([
            'admin_id' => $request->user()->id,
            'type' => $type,
            'subject' => $subject,
            'found_user_id' => $result === LeakCheckResult::Found ? $users->first()->id : null,
            'result' => $result,
        ]);
        AdminLog::record('leak_check.'.$type->value, $check, ['result' => $result->value]);

        return response()->json([
            'result' => $result,
            'users' => $users->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone])->values(),
        ]);
    }
}
