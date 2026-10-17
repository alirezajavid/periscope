<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReactRequest;
use Illuminate\Http\JsonResponse;

/** One endpoint for posts (PUT /posts/{id}/reaction) and comments (PUT /comments/{comment}/reaction). */
class ReactionController extends Controller
{
    public function __invoke(ReactRequest $request): JsonResponse
    {
        $target = $request->target();
        $target->react($request->user(), $request->type());

        return response()->json(['data' => $target->reactionSummary($request->user())]);
    }
}
