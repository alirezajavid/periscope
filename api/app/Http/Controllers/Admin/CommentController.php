<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReplyCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\AdminLog;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommentController extends Controller
{
    /** Newest first; filter by ?status=shown|hidden and ?post_id=. Deleted comments are left out. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $comments = Comment::with(['user', 'post'])
            ->when($request->query('status') === 'hidden', fn ($q) => $q->whereNotNull('hidden_at'))
            ->when($request->query('status') === 'shown', fn ($q) => $q->whereNull('hidden_at'))
            ->when($request->integer('post_id'), fn ($q, $postId) => $q->where('post_id', $postId))
            ->latest()->latest('id')
            ->paginate(30);

        return CommentResource::collection($comments);
    }

    /** Hiding a top-level comment hides its whole thread for readers. */
    public function hide(Request $request, Comment $comment): CommentResource
    {
        $comment->forceFill(['hidden_at' => now(), 'hidden_by' => $request->user()->id])->save();
        AdminLog::record('comment.hide', $comment);

        return new CommentResource($comment->load(['user', 'post']));
    }

    public function unhide(Comment $comment): CommentResource
    {
        $comment->forceFill(['hidden_at' => null, 'hidden_by' => null])->save();
        AdminLog::record('comment.unhide', $comment);

        return new CommentResource($comment->load(['user', 'post']));
    }

    /** The admin's reply goes into the same thread and is highlighted in the app. */
    public function reply(ReplyCommentRequest $request, Comment $comment): JsonResponse
    {
        $reply = Comment::create([
            'post_id' => $comment->post_id,
            'user_id' => $request->user()->id,
            'parent_id' => $comment->threadRootId(),
            'body' => $request->validated('body'),
        ]);
        AdminLog::record('comment.reply', $reply, ['in_reply_to' => $comment->id]);

        return (new CommentResource($reply->load(['user', 'post'])))->response()->setStatusCode(201);
    }
}
