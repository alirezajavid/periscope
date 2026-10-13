<?php

return [
    // Frontend origins; used in emails (verification, password reset) and CORS.
    'app_url' => env('PERISCOPE_APP_URL', 'http://localhost:9000'),
    'admin_url' => env('PERISCOPE_ADMIN_URL', 'http://localhost:9001'),

    // Signed URL lifetime for personalized audio, in minutes.
    'audio_url_ttl' => (int) env('PERISCOPE_AUDIO_URL_TTL', 120),

    // Admin panel idle timeout, in minutes.
    'admin_idle_timeout' => (int) env('PERISCOPE_ADMIN_IDLE_TIMEOUT', 30),

    // How long a personalized audio file is kept on the server, in days.
    'audio_retention_days' => (int) env('PERISCOPE_AUDIO_RETENTION_DAYS', 7),
];
