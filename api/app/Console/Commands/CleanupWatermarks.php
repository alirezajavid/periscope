<?php

namespace App\Console\Commands;

use App\Models\PersonalizedFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupWatermarks extends Command
{
    protected $signature = 'periscope:cleanup-watermarks';

    protected $description = 'Delete personalized files older than their keep period (they are rebuilt on demand)';

    public function handle(): int
    {
        $count = 0;

        PersonalizedFile::where('expires_at', '<', now())->each(function (PersonalizedFile $file) use (&$count) {
            if ($file->path !== null) {
                Storage::disk('local')->delete($file->path);
                Storage::disk(config('periscope.foreign_disk'))->delete($file->path);
            }
            $file->delete();
            $count++;
        });

        $this->info("Removed {$count} expired file(s).");

        return self::SUCCESS;
    }
}
