<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\PlaybackLock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaybackLockController extends Controller
{
    /** The playing device calls this every ~30 s; another device holding the lock gets 409. */
    public function heartbeat(Request $request, PlaybackLock $lock): JsonResponse
    {
        $device = $request->validate(['device' => ['required', 'string', 'max:64']])['device'];

        if (! $lock->acquire($request->user()->id, $device)) {
            return response()->json(['message' => __('posts.playing_elsewhere'), 'code' => 'playing_elsewhere'], 409);
        }

        return response()->json(null, 204);
    }

    public function release(Request $request, PlaybackLock $lock): JsonResponse
    {
        $device = $request->validate(['device' => ['required', 'string', 'max:64']])['device'];

        $lock->release($request->user()->id, $device);

        return response()->json(null, 204);
    }
}
