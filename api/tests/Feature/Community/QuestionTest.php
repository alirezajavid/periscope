<?php

use App\Models\AdminLog;
use App\Models\Post;
use App\Models\Question;
use App\Models\Subscription;
use App\Models\User;

function subscribedUser(): User
{
    $user = User::factory()->create();
    Subscription::factory()->for($user)->create();

    return $user;
}

it('lets a subscriber ask a question', function () {
    $user = subscribedUser();

    asUser($user)->postJson('/api/questions', ['body' => 'Why is Germany a prisoner of geography?'])
        ->assertCreated()->assertJsonPath('data.status', 'new');

    expect(Question::sole()->user_id)->toBe($user->id);
});

it('does not let non-subscribers or anonymous visitors ask', function () {
    asUser(User::factory()->create())->postJson('/api/questions', ['body' => 'May I ask something?'])->assertForbidden();
    asUser(User::factory()->create())->postJson('/api/questions', ['body' => 'May I ask something?'])->assertForbidden();
    expect(Question::count())->toBe(0);
});

it('needs a login to ask', function () {
    $this->postJson('/api/questions', ['body' => 'May I ask something?'])->assertUnauthorized();
});

it('validates the question text', function () {
    asUser(subscribedUser())->postJson('/api/questions', ['body' => 'hi'])->assertUnprocessable()->assertJsonValidationErrors('body');
});

it('shows users only their own questions, with answers', function () {
    $user = subscribedUser();
    Question::factory()->for($user)->create(['body' => 'Mine', 'status' => 'answered', 'answer' => 'Here is the answer']);
    Question::factory()->create(['body' => 'Someone else\'s']);

    $response = asUser($user)->getJson('/api/questions')->assertOk()->assertJsonCount(1, 'data');

    expect($response->json('data.0.answer'))->toBe('Here is the answer')
        ->and($response->getContent())->not->toContain('Someone else');
});

it('never leaks other askers\' identity to the asker', function () {
    $user = subscribedUser();
    Question::factory()->for($user)->create();

    expect(asUser($user)->getJson('/api/questions')->assertOk()->json('data.0'))->not->toHaveKey('user');
});

it('lets an admin list, filter and star questions', function () {
    Question::factory()->create();
    $starred = Question::factory()->create(['status' => 'starred']);

    asAdmin()->getJson('/api/admin/questions?status=starred')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $starred->id);

    $new = Question::where('status', 'new')->first();
    asAdmin()->postJson("/api/admin/questions/{$new->id}/star")->assertOk()->assertJsonPath('data.status', 'starred');
    asAdmin()->postJson("/api/admin/questions/{$new->id}/star")->assertOk()->assertJsonPath('data.status', 'new');
});

it('answers with text, with an episode, or both, and records who and when', function () {
    $post = Post::factory()->create();
    $text = Question::factory()->create();
    $episode = Question::factory()->create();
    $admin = User::factory()->admin()->withTwoFactor()->create();

    asAdmin($admin)->postJson("/api/admin/questions/{$text->id}/answer", ['answer' => 'Short answer'])
        ->assertOk()->assertJsonPath('data.status', 'answered');
    asAdmin($admin)->postJson("/api/admin/questions/{$episode->id}/answer", ['answered_in_post_id' => $post->id])
        ->assertOk()->assertJsonPath('data.answered_in_post.id', $post->id);

    expect($text->fresh()->answered_by)->toBe($admin->id)->and($text->fresh()->answered_at)->not->toBeNull()
        ->and(AdminLog::where('action', 'question.answer')->count())->toBe(2);
});

it('requires an answer or an episode', function () {
    $question = Question::factory()->create();

    asAdmin()->postJson("/api/admin/questions/{$question->id}/answer", [])->assertUnprocessable()->assertJsonValidationErrors('answer');
});

it('keeps an answered question answered when starring', function () {
    $question = Question::factory()->create(['status' => 'answered', 'answer' => 'x']);

    asAdmin()->postJson("/api/admin/questions/{$question->id}/star")->assertOk()->assertJsonPath('data.status', 'answered');
});

it('is admin-only', function () {
    $question = Question::factory()->create();

    asUser()->getJson('/api/admin/questions')->assertForbidden();
    asUser()->postJson("/api/admin/questions/{$question->id}/answer", ['answer' => 'x'])->assertForbidden();
});
