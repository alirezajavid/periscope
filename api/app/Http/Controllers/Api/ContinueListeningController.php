<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\PlaybackProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContinueListeningController extends Controller
{
    /** Unfinished episodes the user started, most recent first (the home screen shows the first). */
    public function __invoke(Request $request): JsonResponse
    {
        $items = PlaybackProgress::with(['post' => fn ($q) => $q->visible()->with('tags')])
            ->where('user_id', $request->user()->id)
            ->where('completed', false)->where('last_position', '>', 0)
            ->latest('last_played_at')->limit(5)->get()
            ->filter(fn (PlaybackProgress $progress) => $progress->post !== null && $request->user()->can('viewContent', $progress->post))
            ->values();

        return response()->json(['data' => $items->map(fn (PlaybackProgress $progress) => [
            'post' => new PostResource($progress->post),
            'position' => $progress->last_position,
            'remaining' => $progress->post->audio_duration ? max(0, $progress->post->audio_duration - $progress->last_position) : null,
        ])]);
    }
}
