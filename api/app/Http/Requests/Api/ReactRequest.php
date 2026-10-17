<?php

namespace App\Http\Requests\Api;

use App\Enums\ReactionType;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Like / dislike a post (route {id}) or a comment (route {comment}); type null removes it. */
class ReactRequest extends FormRequest
{
    private Post|Comment|null $resolved = null;

    public function target(): Post|Comment
    {
        if ($this->resolved) {
            return $this->resolved;
        }

        // The invokable controller doesn't type-hint {comment}, so the route gives the raw id.
        $commentId = $this->route('comment');
        if ($commentId !== null) {
            $comment = $commentId instanceof Comment ? $commentId : Comment::findOrFail($commentId);
            abort_if($comment->isHidden() || ! $comment->post?->isVisible(), 404);

            return $this->resolved = $comment;
        }

        return $this->resolved = Post::visible()->findOrFail($this->route('id'));
    }

    public function authorize(): bool
    {
        $target = $this->target();
        $post = $target instanceof Comment ? $target->post : $target;

        return Gate::forUser($this->user())->allows('viewContent', $post);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['type' => ['present', 'nullable', Rule::enum(ReactionType::class)]];
    }

    public function type(): ?ReactionType
    {
        return $this->enum('type', ReactionType::class);
    }
}
