<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PersonalizedFileStatus;
use App\Http\Controllers\Controller;
use App\Jobs\GeneratePersonalizedAudio;
use App\Models\AdminLog;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatermarkController extends Controller
{
    /** Per-user copies of a post, with the error message of failed ones. */
    public function index(Request $request, Post $post): JsonResponse
    {
        $files = $post->personalizedFiles()->with('user:id,name,email')
            ->when($request->enum('status', PersonalizedFileStatus::class), fn ($q, $status) => $q->where('status', $status))
            ->latest('updated_at')->paginate(50);

        return response()->json($files->through(fn ($file) => [
            'id' => $file->id,
            'user' => ['id' => $file->user->id, 'name' => $file->user->name, 'email' => $file->user->email],
            'status' => $file->status,
            'error' => $file->error,
            'mirrored_at' => $file->mirrored_at,
            'expires_at' => $file->expires_at,
            'updated_at' => $file->updated_at,
        ]));
    }

    /** Re-queue every failed copy of this post. */
    public function retry(Post $post): JsonResponse
    {
        $userIds = $post->personalizedFiles()->where('status', PersonalizedFileStatus::Failed)->pluck('user_id');

        foreach ($userIds as $userId) {
            GeneratePersonalizedAudio::dispatch($post->id, $userId);
        }
        AdminLog::record('post.watermark_retry', $post, ['count' => $userIds->count()]);

        return response()->json(['queued' => $userIds->count()]);
    }
}
