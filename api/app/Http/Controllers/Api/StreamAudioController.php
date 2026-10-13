<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalizedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StreamAudioController extends Controller
{
    /** Streams a personalized file (Range supported). The signed URL is the only credential. */
    public function __invoke(int $file): BinaryFileResponse
    {
        $personalized = PersonalizedFile::findOrFail($file);
        abort_unless($personalized->isUsable() && Storage::disk('local')->exists($personalized->path), 404);

        if ($personalized->last_accessed_at === null || $personalized->last_accessed_at->lt(now()->subHour())) {
            $personalized->forceFill(['last_accessed_at' => now()])->save();
        }

        return response()->file(Storage::disk('local')->path($personalized->path), ['Content-Type' => 'audio/mpeg']);
    }
}
