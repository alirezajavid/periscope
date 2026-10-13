<?php

namespace App\Services\Watermark;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Wraps the audio tools. Pipeline per user (docs/reference.md §5):
 * ffmpeg → mono 44.1 kHz WAV, audiowmark embeds the user's 128-bit payload, ffmpeg → 64 kbps MP3.
 * Commands are always argument arrays, never concatenated strings.
 */
class AudioWatermarker
{
    private const TIMEOUT_SECONDS = 1800;

    /** Write a watermarked MP3 of $original to $output. */
    public function apply(string $original, string $payload, string $output): void
    {
        $workDir = dirname($output).'/work-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($workDir);

        try {
            $plain = "{$workDir}/in.wav";
            $marked = "{$workDir}/out.wav";

            $this->run(['ffmpeg', '-y', '-i', $original, '-ac', '1', '-ar', '44100', $plain]);
            $this->run(['audiowmark', 'add', $plain, $marked, $payload]);
            $this->run(['ffmpeg', '-y', '-i', $marked, '-ac', '1', '-b:a', '64k', $output]);
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /** Duration in whole seconds. */
    public function duration(string $file): int
    {
        $result = $this->run([
            'ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', $file,
        ]);

        return (int) round((float) trim($result));
    }

    /** @param array<int, string> $command */
    private function run(array $command): string
    {
        $result = Process::timeout(self::TIMEOUT_SECONDS)->run($command);

        if ($result->failed()) {
            throw new RuntimeException("{$command[0]} failed: ".mb_substr(trim($result->errorOutput()), -500));
        }

        return $result->output();
    }
}
