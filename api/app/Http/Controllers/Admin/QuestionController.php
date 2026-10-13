<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuestionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionResource;
use App\Models\AdminLog;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class QuestionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $questions = Question::with(['user', 'answeredInPost'])
            ->when($request->enum('status', QuestionStatus::class), fn ($q, $status) => $q->where('status', $status))
            ->latest()->paginate(30);

        return QuestionResource::collection($questions);
    }

    /** Star / un-star (answered questions keep their status). */
    public function star(Question $question): QuestionResource
    {
        if ($question->status !== QuestionStatus::Answered) {
            $question->forceFill(['status' => $question->status === QuestionStatus::Starred ? QuestionStatus::New : QuestionStatus::Starred])->save();
        }
        AdminLog::record('question.star', $question);

        return new QuestionResource($question->load(['user', 'answeredInPost']));
    }

    /** Mark as answered: a written answer, "answered in episode X", or both. */
    public function answer(Request $request, Question $question): QuestionResource
    {
        $data = $request->validate([
            'answer' => ['nullable', 'string', 'max:5000'],
            'answered_in_post_id' => ['nullable', 'exists:posts,id'],
        ]);

        if (blank($data['answer'] ?? null) && blank($data['answered_in_post_id'] ?? null)) {
            throw ValidationException::withMessages(['answer' => __('questions.answer_required')]);
        }

        $question->forceFill([
            'status' => QuestionStatus::Answered,
            'answer' => $data['answer'] ?? null,
            'answered_in_post_id' => $data['answered_in_post_id'] ?? null,
            'answered_by' => $request->user()->id,
            'answered_at' => now(),
        ])->save();
        AdminLog::record('question.answer', $question);

        return new QuestionResource($question->load(['user', 'answeredInPost']));
    }
}
