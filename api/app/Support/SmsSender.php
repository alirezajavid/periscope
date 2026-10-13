<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class SmsSender
{
    public function send(string $phone, string $message): void
    {
        $driver = config('periscope.sms.driver');

        match ($driver) {
            'log' => Log::info("SMS to {$phone}: {$message}"),
            default => throw new RuntimeException("SMS driver [{$driver}] is not implemented."),
        };
    }
}
