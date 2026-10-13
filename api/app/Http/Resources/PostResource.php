<?php

namespace App\Http\Resources;

use App\Enums\PostAccess;
use App\Models\Post;
use App\Services\TextFingerprint\TextFingerprint;
use App\Support\EmbedUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * What a reader gets. Without access only title, cover, excerpt (and the public
 * metadata) are included: body and embed are never serialised, and no audio URL
 * is ever part of a post (audio has its own endpoint).
 *
 * @mixin Post
 */
class PostResource extends JsonResource
{
    /** Premium text carries the reader's invisible mark; free text is public and stays clean. */
    private function bodyFor(Request $request): ?string
    {
        $user = $request->user();

        if ($this->body === null || $user === null || $this->access === PostAccess::Free) {
            return $this->body;
        }

        return app(TextFingerprint::class)->mark($this->body, $user);
    }

    /** @return array<int|string, mixed> */
    public function toArray(Request $request): array
    {
        $canView = Gate::forUser($request->user())->allows('viewContent', $this->resource);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'episode_number' => $this->episode_number,
            'excerpt' => $this->excerpt,
            'cover_url' => $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null,
            'access' => $this->access,
            'published_at' => $this->published_at,
            'sponsor_name' => $this->sponsor_name,
            'sponsor_url' => $this->sponsor_url,
            'has_audio' => $this->hasAudio(),
            'audio_duration' => $this->audio_duration,
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')),
            'locked' => ! $canView,
            $this->mergeWhen($canView, fn () => [
                'body' => $this->bodyFor($request),
                'embed_url' => EmbedUrl::extract($this->embed_code),
            ]),
        ];
    }
}
