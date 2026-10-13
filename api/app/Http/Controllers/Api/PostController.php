<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $posts = Post::visible()->with('tags')
            ->when($request->string('tag')->trim()->value(), fn ($q, $tag) => $q->whereHas('tags', fn ($t) => $t->where('name', $tag)))
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $this->search($q, $term, $request))
            ->orderByDesc('published_at')->orderByDesc('id')
            ->paginate(20);

        return PostResource::collection($posts);
    }

    public function show(Request $request, int $id): PostResource
    {
        return new PostResource(Post::visible()->with('tags')->findOrFail($id));
    }

    /**
     * Title, excerpt and hashtags are public. The body is searched only in posts the
     * reader may open: otherwise a non-subscriber could probe premium text one query at a time.
     *
     * @param  Builder<Post>  $query
     */
    private function search(Builder $query, string $term, Request $request): void
    {
        $like = '%'.addcslashes($term, '%_\\').'%';
        $readsAll = $request->user()?->canReadPremium() ?? false;

        $query->where(function ($where) use ($like, $readsAll) {
            $where->where('title', 'like', $like)
                ->orWhere('excerpt', 'like', $like)
                ->orWhereHas('tags', fn ($tags) => $tags->where('name', 'like', $like));

            $readsAll
                ? $where->orWhere('body', 'like', $like)
                : $where->orWhere(fn ($free) => $free->where('access', 'free')->where('body', 'like', $like));
        });
    }
}
