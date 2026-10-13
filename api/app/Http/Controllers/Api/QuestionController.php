<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuestionController extends Controller
{
    /** The user's own questions with the answers they received. */
    public function index(Request $request): AnonymousResourceCollection
    {
        return QuestionResource::collection(
            $request->user()->questions()->with('answeredInPost')->latest()->paginate(20)
        );
    }

    /** Only subscribers can ask; there is no anonymous mode and no limit on how many. */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->canReadPremium(), 403, __('questions.subscribers_only'));

        $data = $request->validate(['body' => ['required', 'string', 'min:5', 'max:500']]);
        $question = $request->user()->questions()->create($data);

        return (new QuestionResource($question))->response()->setStatusCode(201);
    }
}
