<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Comment> */
class CommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(8),
        ];
    }

    public function replyTo(Comment $parent): static
    {
        return $this->state(['post_id' => $parent->post_id, 'parent_id' => $parent->id]);
    }

    public function hidden(): static
    {
        return $this->state(['hidden_at' => now()]);
    }
}
