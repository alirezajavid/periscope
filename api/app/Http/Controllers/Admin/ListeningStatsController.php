<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;

class ListeningStatsController extends Controller
{
    /** Per episode: how many started, how many heard it to the end (≥ 90 %), completion rate, average time. */
    public function __invoke(): JsonResponse
    {
        $posts = Post::query()
            ->whereHas('progress')
            ->withCount([
                'progress as started',
                'progress as completed' => fn ($q) => $q->where('completed', true),
            ])
            ->withAvg('progress as average_listened', 'max_position')
            ->orderByDesc('published_at')
            ->paginate(30);

        return response()->json($posts->through(fn (Post $post) => [
            'post_id' => $post->id,
            'title' => $post->title,
            'episode_number' => $post->episode_number,
            'duration' => $post->audio_duration,
            'started' => $post->started,
            'completed' => $post->completed,
            'completion_percent' => $post->started > 0 ? round($post->completed / $post->started * 100, 1) : 0,
            'average_listened_seconds' => (int) round((float) $post->average_listened),
        ]));
    }
}
