<?php

namespace App\Http\Requests\Api;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Anyone who may read the post's content may comment on it (free post: every user; premium: subscribers). */
class StoreCommentRequest extends FormRequest
{
    private ?Post $target = null;

    public function targetPost(): Post
    {
        return $this->target ??= Post::visible()->findOrFail($this->route('id'));
    }

    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('viewContent', $this->targetPost());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:2', 'max:1000'],
            'parent_id' => ['nullable', 'integer'],
        ];
    }

    /** The comment being answered: shown, on the same post. Null for a new top-level comment. */
    public function parentComment(): ?Comment
    {
        $id = $this->validated('parent_id');

        return $id === null ? null : $this->targetPost()->comments()->shown()->findOrFail($id);
    }
}
