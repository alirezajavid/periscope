<?php

use App\Enums\PersonalizedFileStatus;
use App\Jobs\GeneratePersonalizedAudio;
use App\Jobs\MirrorFileToForeignHost;
use App\Models\PersonalizedFile;
use App\Models\Post;
use App\Models\User;
use App\Services\Watermark\AudioWatermarker;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/** Pretend ffmpeg/audiowmark/ffprobe: every command succeeds and the final MP3 appears on disk. */
function fakeAudioTools(?Closure $override = null): void
{
    test()->ran = new ArrayObject;

    Process::fake(function (PendingProcess $process) use ($override): ProcessResult {
        $command = $process->command;
        test()->ran->append($command);

        if ($override && ($result = $override($command))) {
            return $result;
        }
        if ($command[0] === 'ffmpeg' && str_ends_with((string) end($command), '.mp3')) {
            file_put_contents(end($command), 'watermarked-mp3');
        }

        return Process::result($command[0] === 'ffprobe' ? "2400.4\n" : '');
    });
}

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    Storage::disk('local')->put('audio/original/ep.mp3', 'original-premium-audio');
    $this->post = Post::factory()->withAudio('audio/original/ep.mp3')->create();
    $this->user = User::factory()->create();
});

it('builds the copy with ffmpeg, audiowmark and the user\'s own payload', function () {
    fakeAudioTools();

    (new GeneratePersonalizedAudio($this->post->id, $this->user->id))->handle(app(AudioWatermarker::class));

    $file = PersonalizedFile::firstWhere(['user_id' => $this->user->id, 'post_id' => $this->post->id]);
    expect($file->status)->toBe(PersonalizedFileStatus::Ready)
        ->and($file->path)->toBe("audio/personalized/{$this->post->id}/{$this->user->id}.mp3")
        ->and($file->expires_at->isBetween(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()))->toBeTrue()
        ->and($this->post->fresh()->audio_duration)->toBe(2400);
    Storage::disk('local')->assertExists($file->path);

    $payload = $this->user->watermark_payload;
    expect($payload)->toMatch('/^[0-9a-f]{32}$/');
    Process::assertRan(fn (PendingProcess $p) => $p->command[0] === 'audiowmark' && $p->command[1] === 'add' && end($p->command) === $payload);
    Process::assertRan(fn (PendingProcess $p) => $p->command[0] === 'ffmpeg' && in_array('64k', $p->command, true));
    Queue::assertPushed(MirrorFileToForeignHost::class, fn ($job) => $job->personalizedFileId === $file->id);
});

it('runs the tools as argument arrays in the right order', function () {
    fakeAudioTools();

    (new GeneratePersonalizedAudio($this->post->id, $this->user->id))->handle(app(AudioWatermarker::class));

    expect(collect($this->ran->getArrayCopy())->each(fn ($command) => expect($command)->toBeArray())->map(fn ($command) => $command[0])->all())
        ->toBe(['ffmpeg', 'audiowmark', 'ffmpeg', 'ffprobe']);
});

it('embeds a different payload for each user', function () {
    fakeAudioTools();
    $other = User::factory()->create();

    foreach ([$this->user, $other] as $user) {
        (new GeneratePersonalizedAudio($this->post->id, $user->id))->handle(app(AudioWatermarker::class));
    }

    expect($this->user->watermark_payload)->not->toBe($other->watermark_payload);
    Process::assertRan(fn (PendingProcess $p) => $p->command[0] === 'audiowmark' && end($p->command) === $other->watermark_payload);
});

it('leaves no working files behind', function () {
    fakeAudioTools();

    (new GeneratePersonalizedAudio($this->post->id, $this->user->id))->handle(app(AudioWatermarker::class));

    expect(Storage::disk('local')->allDirectories("audio/personalized/{$this->post->id}"))->toBe([]);
});

it('does nothing when a usable copy already exists', function () {
    fakeAudioTools();
    PersonalizedFile::factory()->ready('audio/personalized/x.mp3')->create(['user_id' => $this->user->id, 'post_id' => $this->post->id]);

    (new GeneratePersonalizedAudio($this->post->id, $this->user->id))->handle(app(AudioWatermarker::class));

    Process::assertNothingRan();
});

it('rebuilds an expired copy', function () {
    fakeAudioTools();
    PersonalizedFile::factory()->ready('audio/personalized/x.mp3')->create([
        'user_id' => $this->user->id, 'post_id' => $this->post->id, 'expires_at' => now()->subDay(),
    ]);

    (new GeneratePersonalizedAudio($this->post->id, $this->user->id))->handle(app(AudioWatermarker::class));

    expect(PersonalizedFile::count())->toBe(1)
        ->and(PersonalizedFile::first()->isUsable())->toBeTrue();
});

it('records a failure with the tool\'s error message', function () {
    fakeAudioTools(fn (array $command) => $command[0] === 'audiowmark' ? Process::result(errorOutput: 'No such payload', exitCode: 1) : null);
    $job = new GeneratePersonalizedAudio($this->post->id, $this->user->id);

    try {
        $job->handle(app(AudioWatermarker::class));
        $this->fail('The job should have thrown.');
    } catch (RuntimeException $e) {
        $job->failed($e);
    }

    $file = PersonalizedFile::first();
    expect($file->status)->toBe(PersonalizedFileStatus::Failed)->and($file->error)->toContain('audiowmark failed')->toContain('No such payload');
});

it('uses the normal queue by default', function () {
    expect((new GeneratePersonalizedAudio(1, 1))->queue)->toBe('watermark');
});
