<?php

use App\Models\Post;
use App\Models\User;
use App\Services\Watermark\AudioWatermarker;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

it('fills a local database with usable demo data', function () {
    Storage::fake('local');
    Storage::fake('public');
    Process::fake();

    $this->seed(DemoSeeder::class);

    expect(User::where('role', 'admin')->count())->toBe(1)
        ->and(User::firstWhere('email', 'admin@periscope.test')->two_factor_confirmed_at)->not->toBeNull()
        ->and(User::firstWhere('email', 'subscriber@periscope.test')->hasActiveSubscription())->toBeTrue()
        ->and(User::firstWhere('email', 'expired@periscope.test')->hasActiveSubscription())->toBeFalse()
        ->and(User::firstWhere('email', 'blocked@periscope.test')->isBlocked())->toBeTrue()
        ->and(Post::visible()->count())->toBeGreaterThanOrEqual(10)
        ->and(Post::visible()->where('access', 'free')->count())->toBeGreaterThan(0)
        ->and(Post::visible()->where('access', 'premium')->count())->toBeGreaterThan(0)
        ->and(Post::visible()->whereNotNull('audio_path')->count())->toBeGreaterThan(0)
        ->and(Post::visible()->whereNull('audio_path')->count())->toBeGreaterThan(0);
});

it('lets the seeded subscriber log in and see premium content', function () {
    Storage::fake('local');
    Storage::fake('public');
    Process::fake();
    $this->seed(DemoSeeder::class);
    $premium = Post::visible()->where('access', 'premium')->first();

    $this->postJson('/api/login', ['email' => 'subscriber@periscope.test', 'password' => DemoSeeder::PASSWORD])->assertOk();
    asUser(User::firstWhere('email', 'subscriber@periscope.test'))->getJson("/api/posts/{$premium->id}")->assertJsonPath('data.locked', false);
});

it('refuses to seed in production', function () {
    $this->app['env'] = 'production';

    expect(fn () => (new DemoSeeder)->run())->toThrow(RuntimeException::class);
});

it('can skip audiowmark in development but never in production', function () {
    Process::fake();
    config(['periscope.watermark.enabled' => false]);

    app(AudioWatermarker::class)->apply('/in.mp3', str_repeat('a', 32), '/tmp/out.mp3');
    Process::assertRanTimes(fn ($p) => $p->command[0] === 'ffmpeg', 1);
    Process::assertNotRan(fn ($p) => $p->command[0] === 'audiowmark');

    $this->app['env'] = 'production';
    expect(fn () => app(AudioWatermarker::class)->apply('/in.mp3', str_repeat('a', 32), '/tmp/out.mp3'))
        ->toThrow(RuntimeException::class, 'cannot be disabled in production');
});
