<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    /**
     * Newest threads first, each with its replies (oldest first). Only readers of the post's
     * content see its comments, so premium discussion doesn't leak to non-subscribers.
     */
    public function index(Request $request, int $id): AnonymousResourceCollection
    {
        $post = Post::visible()->findOrFail($id);
        Gate::authorize('viewContent', $post);
        $reader = $request->user();

        $comments = $post->comments()->withTrashed()->shown()->whereNull('parent_id')
            // A deleted comment stays only as a placeholder for its replies.
            ->where(fn ($q) => $q->whereNull('deleted_at')->orWhereHas('replies', fn ($r) => $r->shown()))
            ->with(['user', 'replies' => fn ($q) => $q->shown()->with('user')->withReactions($reader)->oldest()])
            ->withReactions($reader)
            ->latest()->latest('id')
            ->paginate(20);

        return CommentResource::collection($comments);
    }

    public function store(StoreCommentRequest $request, int $id): JsonResponse
    {
        $parent = $request->parentComment();

        $comment = $request->targetPost()->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $parent?->threadRootId(),
            'body' => $request->validated('body'),
        ]);

        return (new CommentResource($comment->load('user')))->response()->setStatusCode(201);
    }

    public function destroy(Comment $comment): Response
    {
        Gate::authorize('delete', $comment);
        $comment->delete();

        return response()->noContent();
    }
}
