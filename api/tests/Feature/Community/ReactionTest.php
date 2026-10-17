<?php

use App\Enums\ReactionType;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Subscription;
use App\Models\User;

it('likes, switches to dislike and clears a reaction on a post', function () {
    $post = Post::factory()->free()->create();
    $user = User::factory()->create();

    asUser($user)->putJson("/api/posts/{$post->id}/reaction", ['type' => 'like'])
        ->assertOk()->assertJson(['data' => ['likes' => 1, 'dislikes' => 0, 'mine' => 'like']]);

    asUser($user)->putJson("/api/posts/{$post->id}/reaction", ['type' => 'dislike'])
        ->assertOk()->assertJson(['data' => ['likes' => 0, 'dislikes' => 1, 'mine' => 'dislike']]);

    asUser($user)->putJson("/api/posts/{$post->id}/reaction", ['type' => null])
        ->assertOk()->assertJson(['data' => ['likes' => 0, 'dislikes' => 0, 'mine' => null]]);

    expect(Reaction::count())->toBe(0);
});

it('counts one reaction per user', function () {
    $post = Post::factory()->free()->create();
    $user = User::factory()->create();

    asUser($user)->putJson("/api/posts/{$post->id}/reaction", ['type' => 'like'])->assertOk();
    asUser($user)->putJson("/api/posts/{$post->id}/reaction", ['type' => 'like'])->assertOk();
    asUser()->putJson("/api/posts/{$post->id}/reaction", ['type' => 'like'])->assertOk()->assertJsonPath('data.likes', 2);
});

it('shows the counts and the reader\'s own reaction on the post', function () {
    $post = Post::factory()->free()->create();
    $user = User::factory()->create();
    $post->react($user, ReactionType::Dislike);
    $post->react(User::factory()->create(), ReactionType::Like);

    asUser($user)->getJson("/api/posts/{$post->id}")
        ->assertOk()->assertJson(['data' => ['reactions' => ['likes' => 1, 'dislikes' => 1, 'mine' => 'dislike']]]);
});

it('lets only subscribers react to premium posts and their comments', function () {
    $post = Post::factory()->create();
    $comment = Comment::factory()->create(['post_id' => $post->id]);
    $subscriber = User::factory()->create();
    Subscription::factory()->for($subscriber)->create();

    asUser()->putJson("/api/posts/{$post->id}/reaction", ['type' => 'like'])->assertForbidden();
    asUser()->putJson("/api/comments/{$comment->id}/reaction", ['type' => 'like'])->assertForbidden();
    asUser($subscriber)->putJson("/api/posts/{$post->id}/reaction", ['type' => 'like'])->assertOk();
    asUser($subscriber)->putJson("/api/comments/{$comment->id}/reaction", ['type' => 'like'])->assertOk();
});

it('reacts to comments, and comment counts appear in the list', function () {
    $comment = Comment::factory()->create(['post_id' => Post::factory()->free()]);
    $user = User::factory()->create();

    asUser($user)->putJson("/api/comments/{$comment->id}/reaction", ['type' => 'like'])
        ->assertOk()->assertJson(['data' => ['likes' => 1, 'mine' => 'like']]);

    asUser($user)->getJson("/api/posts/{$comment->post_id}/comments")
        ->assertOk()->assertJson(['data' => [['reactions' => ['likes' => 1, 'dislikes' => 0, 'mine' => 'like']]]]);
});

it('refuses reactions on hidden or deleted comments', function () {
    $post = Post::factory()->free()->create();
    $hidden = Comment::factory()->hidden()->create(['post_id' => $post->id]);
    $deleted = Comment::factory()->create(['post_id' => $post->id]);
    $deleted->delete();

    asUser()->putJson("/api/comments/{$hidden->id}/reaction", ['type' => 'like'])->assertNotFound();
    asUser()->putJson("/api/comments/{$deleted->id}/reaction", ['type' => 'like'])->assertNotFound();
});

it('validates the reaction type and needs a login', function () {
    $post = Post::factory()->free()->create();

    asUser()->putJson("/api/posts/{$post->id}/reaction", ['type' => 'love'])->assertUnprocessable();
    asUser()->putJson("/api/posts/{$post->id}/reaction", [])->assertUnprocessable();
    $this->putJson("/api/posts/{$post->id}/reaction", ['type' => 'like'])->assertUnauthorized();
});
