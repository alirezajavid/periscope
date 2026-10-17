<?php

namespace App\Http\Resources;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A comment as readers see it. Only the author's name and whether they are an admin are public;
 * a deleted comment that still has replies stays as an empty placeholder.
 *
 * @mixin Comment
 */
class CommentResource extends JsonResource
{
    /** @return array<int|string, mixed> */
    public function toArray(Request $request): array
    {
        $deleted = $this->trashed();
        $admin = $request->is('api/admin/*');

        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'deleted' => $deleted,
            'body' => $deleted ? null : $this->body,
            'author' => $deleted ? null : $this->whenLoaded('user', fn () => [
                'name' => $this->user->name,
                'is_admin' => $this->user->isAdmin(),
            ]),
            'mine' => ! $deleted && $this->user_id === $request->user()?->id,
            'reactions' => $this->when(! $deleted && ! $admin, fn () => $this->reactionSummary($request->user())),
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
            'created_at' => $this->created_at,
            $this->mergeWhen($admin, fn () => [
                'hidden' => $this->isHidden(),
                'hidden_at' => $this->hidden_at,
                'post' => $this->whenLoaded('post', fn () => $this->post
                    ? ['id' => $this->post->id, 'title' => $this->post->title]
                    : null),
                'user' => $this->whenLoaded('user', fn () => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ]),
            ]),
        ];
    }
}
