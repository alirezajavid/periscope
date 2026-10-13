<?php

namespace App\Http\Resources;

use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Question */
class QuestionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'status' => $this->status,
            'answer' => $this->answer,
            'answered_at' => $this->answered_at,
            'answered_in_post' => $this->whenLoaded('answeredInPost', fn () => $this->answeredInPost
                ? ['id' => $this->answeredInPost->id, 'title' => $this->answeredInPost->title, 'episode_number' => $this->answeredInPost->episode_number]
                : null),
            'user' => $this->when($request->is('api/admin/*'), fn () => new UserResource($this->whenLoaded('user'))),
            'created_at' => $this->created_at,
        ];
    }
}
