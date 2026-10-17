<?php

use App\Models\AdminLog;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Subscription;
use App\Models\User;

function commenterWithSubscription(): User
{
    $user = User::factory()->create();
    Subscription::factory()->for($user)->create();

    return $user;
}

it('lets any signed-in user comment on a free post', function () {
    $post = Post::factory()->free()->create();
    $user = User::factory()->create();

    asUser($user)->postJson("/api/posts/{$post->id}/comments", ['body' => 'Great episode'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Great episode')
        ->assertJsonPath('data.author.is_admin', false)
        ->assertJsonPath('data.mine', true);

    expect(Comment::sole()->user_id)->toBe($user->id);
});

it('keeps premium comments to subscribers, both reading and writing', function () {
    $post = Post::factory()->create();
    Comment::factory()->create(['post_id' => $post->id, 'body' => 'Spoiler about premium content']);

    $outsider = asUser(User::factory()->create());
    $outsider->getJson("/api/posts/{$post->id}/comments")->assertForbidden();
    $outsider->postJson("/api/posts/{$post->id}/comments", ['body' => 'Let me in'])->assertForbidden();

    asUser(commenterWithSubscription())->getJson("/api/posts/{$post->id}/comments")
        ->assertOk()->assertJsonPath('data.0.body', 'Spoiler about premium content');
});

it('needs a login', function () {
    $post = Post::factory()->free()->create();

    $this->getJson("/api/posts/{$post->id}/comments")->assertUnauthorized();
    $this->postJson("/api/posts/{$post->id}/comments", ['body' => 'Hello'])->assertUnauthorized();
});

it('does not accept comments on drafts or scheduled posts', function () {
    $draft = Post::factory()->free()->draft()->create();
    $scheduled = Post::factory()->free()->scheduled()->create();

    asUser()->postJson("/api/posts/{$draft->id}/comments", ['body' => 'Early'])->assertNotFound();
    asUser()->postJson("/api/posts/{$scheduled->id}/comments", ['body' => 'Early'])->assertNotFound();
});

it('validates the comment text', function () {
    $post = Post::factory()->free()->create();

    asUser()->postJson("/api/posts/{$post->id}/comments", ['body' => ''])->assertUnprocessable()->assertJsonValidationErrors('body');
    asUser()->postJson("/api/posts/{$post->id}/comments", ['body' => str_repeat('a', 1001)])->assertUnprocessable();
});

it('keeps replies one level deep', function () {
    $post = Post::factory()->free()->create();
    $root = Comment::factory()->create(['post_id' => $post->id]);
    $reply = Comment::factory()->replyTo($root)->create();

    asUser()->postJson("/api/posts/{$post->id}/comments", ['body' => 'Reply to a reply', 'parent_id' => $reply->id])
        ->assertCreated()->assertJsonPath('data.parent_id', $root->id);
});

it('rejects a parent from another post or a hidden one', function () {
    $post = Post::factory()->free()->create();
    $elsewhere = Comment::factory()->create(['post_id' => Post::factory()->free()]);
    $hidden = Comment::factory()->hidden()->create(['post_id' => $post->id]);

    asUser()->postJson("/api/posts/{$post->id}/comments", ['body' => 'x y', 'parent_id' => $elsewhere->id])->assertNotFound();
    asUser()->postJson("/api/posts/{$post->id}/comments", ['body' => 'x y', 'parent_id' => $hidden->id])->assertNotFound();
});

it('lists threads newest first with replies oldest first, and marks admin replies', function () {
    $post = Post::factory()->free()->create();
    $old = Comment::factory()->create(['post_id' => $post->id, 'created_at' => now()->subDay()]);
    $new = Comment::factory()->create(['post_id' => $post->id]);
    Comment::factory()->replyTo($old)->create(['body' => 'first reply', 'created_at' => now()->subHours(2)]);
    Comment::factory()->replyTo($old)->create([
        'body' => 'admin reply',
        'user_id' => User::factory()->admin(),
        'created_at' => now()->subHour(),
    ]);

    $response = asUser()->getJson("/api/posts/{$post->id}/comments")->assertOk()->assertJsonCount(2, 'data');

    expect($response->json('data.0.id'))->toBe($new->id)
        ->and($response->json('data.1.replies.0.body'))->toBe('first reply')
        ->and($response->json('data.1.replies.1.author.is_admin'))->toBeTrue();
});

it('never exposes commenters\' email or phone', function () {
    $post = Post::factory()->free()->create();
    $author = User::factory()->create(['email' => 'secret@example.com']);
    Comment::factory()->create(['post_id' => $post->id, 'user_id' => $author->id]);

    $content = asUser()->getJson("/api/posts/{$post->id}/comments")->assertOk()->getContent();

    expect($content)->not->toContain('secret@example.com');
});

it('lets authors delete only their own comments', function () {
    $mine = Comment::factory()->create();
    $theirs = Comment::factory()->create();

    asUser($mine->user)->deleteJson("/api/comments/{$theirs->id}")->assertForbidden();
    asUser($mine->user)->deleteJson("/api/comments/{$mine->id}")->assertNoContent();

    expect($mine->fresh()->trashed())->toBeTrue()->and($theirs->fresh()->trashed())->toBeFalse();
});

it('keeps a deleted comment as an empty placeholder only while it has replies', function () {
    $post = Post::factory()->free()->create();
    $withReply = Comment::factory()->create(['post_id' => $post->id, 'body' => 'gone with reply']);
    Comment::factory()->replyTo($withReply)->create();
    $alone = Comment::factory()->create(['post_id' => $post->id, 'body' => 'gone alone']);
    $withReply->delete();
    $alone->delete();

    $response = asUser()->getJson("/api/posts/{$post->id}/comments")->assertOk()->assertJsonCount(1, 'data');

    expect($response->json('data.0.deleted'))->toBeTrue()
        ->and($response->json('data.0.body'))->toBeNull()
        ->and($response->getContent())->not->toContain('gone with reply')->not->toContain('gone alone');
});

it('does not show hidden comments or hidden threads to readers', function () {
    $post = Post::factory()->free()->create();
    $hiddenRoot = Comment::factory()->hidden()->create(['post_id' => $post->id, 'body' => 'hidden root']);
    Comment::factory()->replyTo($hiddenRoot)->create(['body' => 'reply in hidden thread']);
    $root = Comment::factory()->create(['post_id' => $post->id]);
    Comment::factory()->replyTo($root)->hidden()->create(['body' => 'hidden reply']);

    $response = asUser()->getJson("/api/posts/{$post->id}/comments")->assertOk()->assertJsonCount(1, 'data');

    expect($response->json('data.0.replies'))->toBeEmpty()
        ->and($response->getContent())->not->toContain('hidden root')->not->toContain('reply in hidden thread');
});

it('lets an admin list, hide and unhide comments, with a log entry', function () {
    $comment = Comment::factory()->create();
    Comment::factory()->hidden()->create();

    asAdmin()->getJson('/api/admin/comments?status=hidden')->assertOk()->assertJsonCount(1, 'data');
    asAdmin()->getJson('/api/admin/comments')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.user.email', fn ($email) => $email !== null);

    asAdmin()->postJson("/api/admin/comments/{$comment->id}/hide")->assertOk()->assertJsonPath('data.hidden', true);
    expect($comment->fresh()->isHidden())->toBeTrue();

    asAdmin()->postJson("/api/admin/comments/{$comment->id}/unhide")->assertOk()->assertJsonPath('data.hidden', false);
    expect($comment->fresh()->isHidden())->toBeFalse()
        ->and(AdminLog::whereIn('action', ['comment.hide', 'comment.unhide'])->count())->toBe(2);
});

it('lets an admin reply in the same thread', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();
    $root = Comment::factory()->create();
    $reply = Comment::factory()->replyTo($root)->create();

    asAdmin($admin)->postJson("/api/admin/comments/{$reply->id}/reply", ['body' => 'Thanks for listening'])
        ->assertCreated()->assertJsonPath('data.parent_id', $root->id);

    $adminReply = Comment::where('user_id', $admin->id)->sole();
    expect($adminReply->post_id)->toBe($root->post_id)
        ->and(AdminLog::where('action', 'comment.reply')->count())->toBe(1);
});

it('keeps the comment admin to admins', function () {
    $comment = Comment::factory()->create();

    asUser()->getJson('/api/admin/comments')->assertForbidden();
    asUser()->postJson("/api/admin/comments/{$comment->id}/hide")->assertForbidden();
    asUser()->postJson("/api/admin/comments/{$comment->id}/reply", ['body' => 'Not an admin'])->assertForbidden();
});
