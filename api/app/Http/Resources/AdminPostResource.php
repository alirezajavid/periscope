<?php

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Post */
class AdminPostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'episode_number' => $this->episode_number,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'cover_url' => $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null,
            'access' => $this->access,
            'status' => $this->status,
            'published_at' => $this->published_at,
            'sponsor_name' => $this->sponsor_name,
            'sponsor_url' => $this->sponsor_url,
            'embed_code' => $this->embed_code,
            'has_audio' => $this->hasAudio(),
            'audio_size' => $this->audio_size,
            'audio_duration' => $this->audio_duration,
            'audio_mirrored_at' => $this->audio_mirrored_at,
            'watermarks' => $this->when(isset($this->watermarks_ready), fn () => [
                'ready' => (int) $this->watermarks_ready,
                'building' => (int) $this->watermarks_building,
                'failed' => (int) $this->watermarks_failed,
            ]),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
