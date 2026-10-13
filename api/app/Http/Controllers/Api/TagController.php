<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;

class TagController extends Controller
{
    /** Hashtags that have at least one visible post, most used first. */
    public function index(): JsonResponse
    {
        $tags = Tag::withCount(['posts' => fn ($q) => $q->visible()])
            ->having('posts_count', '>', 0)
            ->orderByDesc('posts_count')->orderBy('name')
            ->get()
            ->map(fn (Tag $tag) => ['name' => $tag->name, 'posts_count' => $tag->posts_count]);

        return response()->json(['data' => $tags]);
    }
}
