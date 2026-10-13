<?php

namespace Database\Factories;

use App\Models\PersonalizedFile;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PersonalizedFile> */
class PersonalizedFileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'post_id' => Post::factory(),
            'path' => null,
            'status' => 'building',
        ];
    }

    public function ready(string $path = 'audio/personalized/1/1.mp3'): static
    {
        return $this->state(['status' => 'ready', 'path' => $path, 'expires_at' => now()->addDays(7)]);
    }

    public function failed(string $error = 'audiowmark failed'): static
    {
        return $this->state(['status' => 'failed', 'error' => $error]);
    }
}
