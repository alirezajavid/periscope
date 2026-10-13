<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * One device plays at a time per account. The device that holds the lock keeps it
 * alive with heartbeats; if they stop, the lock expires on its own.
 */
class PlaybackLock
{
    public const TTL_SECONDS = 90;

    public function acquire(int $userId, string $deviceId): bool
    {
        $holder = Cache::get($this->key($userId));

        if ($holder !== null && $holder !== $deviceId) {
            return false;
        }

        Cache::put($this->key($userId), $deviceId, self::TTL_SECONDS);

        return true;
    }

    public function release(int $userId, string $deviceId): void
    {
        if (Cache::get($this->key($userId)) === $deviceId) {
            Cache::forget($this->key($userId));
        }
    }

    private function key(int $userId): string
    {
        return "playback-lock:{$userId}";
    }
}
