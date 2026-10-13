<?php

namespace App\Http\Controllers\Api;

use App\Enums\PersonalizedFileStatus;
use App\Enums\PostAccess;
use App\Http\Controllers\Controller;
use App\Jobs\GeneratePersonalizedAudio;
use App\Models\PersonalizedFile;
use App\Models\Post;
use App\Support\PlaybackLock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class PostAudioController extends Controller
{
    /**
     * Where to stream a post's audio from. Free posts point at the public file; premium posts
     * at the caller's own watermarked copy (a temporary signed URL), or `preparing` while it is built.
     */
    public function __invoke(Request $request, int $id, PlaybackLock $lock): JsonResponse
    {
        $request->validate(['device' => ['required', 'string', 'max:64']]);

        $post = Post::visible()->whereNotNull('audio_path')->findOrFail($id);

        if ($post->access === PostAccess::Free) {
            return $this->ready(url("/api/posts/{$post->id}/free-audio"), $post);
        }

        $user = $request->user();
        abort_if($user === null, 401);
        abort_unless($user->can('viewContent', $post), 403, __('posts.subscription_required'));

        if (! $lock->acquire($user->id, $request->string('device')->value())) {
            return response()->json(['message' => __('posts.playing_elsewhere'), 'code' => 'playing_elsewhere'], 409);
        }

        $file = PersonalizedFile::firstWhere(['user_id' => $user->id, 'post_id' => $post->id]);

        if ($file?->isUsable()) {
            return $this->ready($this->signedUrl($file, $user->preferred_mirror->value), $post);
        }

        // Missing, failed or expired copies are (re)built; one that is already building is left alone.
        if ($file === null || $file->status !== PersonalizedFileStatus::Building) {
            GeneratePersonalizedAudio::dispatch($post->id, $user->id)->onQueue(GeneratePersonalizedAudio::PRIORITY_QUEUE);
        }

        return response()->json(['status' => 'preparing'], 202);
    }

    private function ready(string $url, Post $post): JsonResponse
    {
        return response()->json(['status' => 'ready', 'url' => $url, 'duration' => $post->audio_duration]);
    }

    /** Valid for ~2 hours; signed on the path only so the same URL works on the foreign mirror's proxy. */
    private function signedUrl(PersonalizedFile $file, string $mirror): string
    {
        $path = URL::temporarySignedRoute('audio.stream', now()->addMinutes(config('periscope.audio_url_ttl')), ['file' => $file->id], absolute: false);

        $root = $mirror === 'intl' && $file->mirrored_at && config('periscope.foreign_url')
            ? rtrim(config('periscope.foreign_url'), '/')
            : rtrim(config('app.url'), '/');

        return $root.$path;
    }
}
