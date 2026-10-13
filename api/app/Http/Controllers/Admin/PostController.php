<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostAccess;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePostRequest;
use App\Http\Requests\Admin\UpdatePostRequest;
use App\Http\Requests\Admin\UploadAudioRequest;
use App\Http\Requests\Admin\UploadCoverRequest;
use App\Http\Resources\AdminPostResource;
use App\Jobs\MirrorFileToForeignHost;
use App\Models\AdminLog;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $posts = Post::with('tags')->withWatermarkCounts()
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed())
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where('title', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->when($request->enum('status', PostStatus::class), fn ($q, $status) => $q->where('status', $status))
            ->when($request->enum('access', PostAccess::class), fn ($q, $access) => $q->where('access', $access))
            ->latest('id')
            ->paginate(20);

        return AdminPostResource::collection($posts);
    }

    public function show(int $id): AdminPostResource
    {
        return new AdminPostResource(Post::withTrashed()->with('tags')->withWatermarkCounts()->findOrFail($id));
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = new Post($request->safe()->except('tags'));
        $post->created_by = $request->user()->id;
        $this->prepareForPublishing($post);
        $post->save();
        $post->syncTagNames($request->validated('tags', []));

        AdminLog::record('post.create', $post);

        return (new AdminPostResource($post->load('tags')))->response()->setStatusCode(201);
    }

    public function update(UpdatePostRequest $request, Post $post): AdminPostResource
    {
        $post->fill($request->safe()->except('tags'));
        $this->prepareForPublishing($post);
        $post->save();

        if ($request->has('tags')) {
            $post->syncTagNames($request->validated('tags'));
        }

        AdminLog::record('post.update', $post);

        return new AdminPostResource($post->load('tags'));
    }

    /** Soft delete: stats and leak checks for earlier files keep working. */
    public function destroy(Post $post): JsonResponse
    {
        $post->delete();
        AdminLog::record('post.delete', $post);

        return response()->json(null, 204);
    }

    public function restore(int $id): AdminPostResource
    {
        $post = Post::onlyTrashed()->findOrFail($id);
        $post->restore();
        AdminLog::record('post.restore', $post);

        return new AdminPostResource($post->load('tags'));
    }

    public function cover(UploadCoverRequest $request, Post $post): AdminPostResource
    {
        $old = $post->cover_path;
        $post->cover_path = $request->file('cover')->store('covers', 'public');
        $post->save();

        if ($old !== null) {
            Storage::disk('public')->delete($old);
        }
        MirrorFileToForeignHost::dispatch('public', $post->cover_path);
        AdminLog::record('post.cover', $post);

        return new AdminPostResource($post->load('tags'));
    }

    /** The original audio stays on the private disk; subscribers only ever get a watermarked copy. */
    public function audio(UploadAudioRequest $request, Post $post): AdminPostResource
    {
        $file = $request->file('audio');
        $old = $post->audio_path;

        $post->forceFill([
            'audio_path' => $file->store('audio/original', 'local'),
            'audio_size' => $file->getSize(),
            'audio_duration' => null,
            'audio_mirrored_at' => null,
        ])->save();

        if ($old !== null) {
            Storage::disk('local')->delete($old);
        }
        MirrorFileToForeignHost::dispatch('local', $post->audio_path, $post->id);
        AdminLog::record('post.audio', $post);

        return new AdminPostResource($post->load('tags'));
    }

    /** Publishing needs something to show, and defaults the publish time to now. */
    private function prepareForPublishing(Post $post): void
    {
        if ($post->status !== PostStatus::Published) {
            return;
        }

        if (blank($post->body) && ! $post->hasAudio() && blank($post->embed_code)) {
            throw ValidationException::withMessages(['status' => __('posts.nothing_to_publish')]);
        }

        $post->published_at ??= now();
    }
}
