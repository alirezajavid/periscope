<?php

namespace App\Support;

use DateTimeInterface;
use IntlDateFormatter;

/** Persian-calendar dates for emails (the frontend formats its own dates). */
class JalaliDate
{
    public static function format(DateTimeInterface $date): string
    {
        $formatter = new IntlDateFormatter('fa_IR@calendar=persian', IntlDateFormatter::LONG, IntlDateFormatter::NONE, 'Asia/Tehran');

        return (string) $formatter->format($date);
    }
}
