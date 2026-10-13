<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('periscope:build-watermarks')->hourly()->withoutOverlapping();
Schedule::command('periscope:cleanup-watermarks')->dailyAt('04:00');
Schedule::command('periscope:notify-new-episodes')->everyFiveMinutes()->withoutOverlapping();
