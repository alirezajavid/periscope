<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Post> */
class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'excerpt' => fake()->sentence(12),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'access' => 'premium',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ];
    }

    public function free(): static
    {
        return $this->state(['access' => 'free']);
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft', 'published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(['published_at' => now()->addDay()]);
    }

    public function withAudio(string $path = 'audio/original/example.mp3'): static
    {
        return $this->state(['audio_path' => $path, 'audio_size' => 1024]);
    }
}
