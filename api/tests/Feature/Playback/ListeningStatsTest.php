<?php

use App\Models\PlaybackProgress;
use App\Models\Post;
use App\Models\User;

it('summarises listening per episode', function () {
    $post = Post::factory()->withAudio()->create(['audio_duration' => 1000, 'title' => 'Episode 145']);

    foreach ([[1000, true], [500, false], [200, false], [900, true]] as [$max, $completed]) {
        PlaybackProgress::create([
            'user_id' => User::factory()->create()->id, 'post_id' => $post->id, 'last_position' => $max, 'max_position' => $max,
            'completed' => $completed, 'started_at' => now(), 'last_played_at' => now(),
        ]);
    }
    Post::factory()->create(); // never listened to: not listed

    $response = asAdmin()->getJson('/api/admin/stats/listening')->assertOk()->assertJsonCount(1, 'data');

    expect($response->json('data.0'))->toMatchArray([
        'post_id' => $post->id, 'started' => 4, 'completed' => 2, 'completion_percent' => 50.0, 'average_listened_seconds' => 650,
    ]);
});

it('is admin-only', function () {
    asUser()->getJson('/api/admin/stats/listening')->assertForbidden();
});
