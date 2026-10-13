<?php

use App\Enums\LeakCheckResult;
use App\Models\AdminLog;
use App\Models\LeakCheck;
use App\Models\User;
use App\Services\TextFingerprint\TextFingerprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

it('identifies the leaker from pasted text and records the check', function () {
    $users = User::factory()->count(5)->create();
    $leaker = $users[3];
    $leaked = strip_tags(app(TextFingerprint::class)->mark('<p>متن محرمانه و بلند که نشت کرده است.</p>', $leaker));

    asAdmin()->postJson('/api/admin/leak-checks/text', ['text' => $leaked])->assertOk()
        ->assertJsonPath('result', 'found')->assertJsonPath('users.0.id', $leaker->id);

    $check = LeakCheck::first();
    expect($check->found_user_id)->toBe($leaker->id)->and($check->result)->toBe(LeakCheckResult::Found)
        ->and(AdminLog::where('action', 'leak_check.text')->exists())->toBeTrue();
});

it('reports not found for text without a mark', function () {
    asAdmin()->postJson('/api/admin/leak-checks/text', ['text' => 'plain text from somewhere else'])->assertOk()
        ->assertJsonPath('result', 'not_found')->assertJsonPath('users', []);

    expect(LeakCheck::first()->found_user_id)->toBeNull();
});

it('reports every candidate when a text mixes several readers', function () {
    [$a, $b] = User::factory()->count(2)->create();
    $text = strip_tags(app(TextFingerprint::class)->mark('<p>first paragraph here</p>', $a)
        .app(TextFingerprint::class)->mark('<p>second paragraph here</p>', $b));

    asAdmin()->postJson('/api/admin/leak-checks/text', ['text' => $text])->assertOk()
        ->assertJsonPath('result', 'ambiguous')->assertJsonCount(2, 'users');
});

it('identifies the leaker from a recording via audiowmark', function () {
    $user = User::factory()->create();
    Process::fake(function (PendingProcess $process) use ($user) {
        return Process::result($process->command[0] === 'audiowmark'
            ? "pattern  leak.wav {$user->watermark_payload} 1.2 0.9 A\n"
            : '');
    });

    asAdmin()->postJson('/api/admin/leak-checks/audio', ['audio' => UploadedFile::fake()->create('leak.mp3', 100, 'audio/mpeg')])
        ->assertOk()->assertJsonPath('result', 'found')->assertJsonPath('users.0.id', $user->id);

    Process::assertRan(fn (PendingProcess $p) => $p->command[0] === 'audiowmark' && $p->command[1] === 'get');
    expect(LeakCheck::first()->subject)->toBe('leak.mp3');
});

it('reports not found when the recording has no watermark', function () {
    Process::fake(fn () => Process::result('no pattern found'));

    asAdmin()->postJson('/api/admin/leak-checks/audio', ['audio' => UploadedFile::fake()->create('x.mp3', 10, 'audio/mpeg')])
        ->assertOk()->assertJsonPath('result', 'not_found');
});

it('lists past checks', function () {
    LeakCheck::create(['admin_id' => null, 'type' => 'text', 'subject' => 'x', 'result' => 'not_found']);

    asAdmin()->getJson('/api/admin/leak-checks')->assertOk()->assertJsonCount(1, 'data');
});

it('is admin-only', function () {
    asUser()->postJson('/api/admin/leak-checks/text', ['text' => 'x'])->assertForbidden();
    asUser()->postJson('/api/admin/leak-checks/audio')->assertForbidden();
    asUser()->getJson('/api/admin/leak-checks')->assertForbidden();
});

it('needs a login', function () {
    $this->postJson('/api/admin/leak-checks/text', ['text' => 'x'])->assertUnauthorized();
});
