<?php

namespace App\Http\Controllers\Api;

use App\Enums\PostAccess;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FreeAudioController extends Controller
{
    /** Audio of a free episode is public (it is also what the RSS feed points to); supports Range requests. */
    public function __invoke(int $id): BinaryFileResponse
    {
        $post = Post::visible()->where('access', PostAccess::Free)->whereNotNull('audio_path')->findOrFail($id);

        return response()->file(Storage::disk('local')->path($post->audio_path), ['Content-Type' => 'audio/mpeg']);
    }
}
