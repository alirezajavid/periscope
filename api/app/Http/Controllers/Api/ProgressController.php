<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlaybackProgress;
use App\Models\Post;
use App\Support\PlaybackLock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    /** Where to resume this episode (0 when never played). */
    public function show(Request $request, int $id): JsonResponse
    {
        $post = Post::visible()->findOrFail($id);
        $progress = PlaybackProgress::where('user_id', $request->user()->id)->where('post_id', $post->id)->first();

        return response()->json([
            'position' => $progress->last_position ?? 0,
            'completed' => $progress->completed ?? false,
        ]);
    }

    /** Called periodically while playing and on pause; doubles as the playback heartbeat. */
    public function update(Request $request, int $id, PlaybackLock $lock): JsonResponse
    {
        $data = $request->validate([
            'position' => ['required', 'integer', 'min:0', 'max:86400'],
            'device' => ['required', 'string', 'max:64'],
        ]);

        $post = Post::visible()->findOrFail($id);
        abort_unless($request->user()->can('viewContent', $post), 403, __('posts.subscription_required'));

        if (! $lock->acquire($request->user()->id, $data['device'])) {
            return response()->json(['message' => __('posts.playing_elsewhere'), 'code' => 'playing_elsewhere'], 409);
        }

        $progress = PlaybackProgress::report($request->user(), $post, $data['position']);

        return response()->json(['position' => $progress->last_position, 'completed' => $progress->completed]);
    }
}
