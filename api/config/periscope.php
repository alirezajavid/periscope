<?php

return [
    // Frontend origins; used in emails (verification, password reset) and CORS.
    'app_url' => env('PERISCOPE_APP_URL', 'http://localhost:9000'),
    'admin_url' => env('PERISCOPE_ADMIN_URL', 'http://localhost:9001'),

    // SMS provider for OTP codes. Only `log` (writes the code to the log) exists for now.
    'sms' => ['driver' => env('SMS_DRIVER', 'log')],

    // Video hosts whose embed URLs are accepted in posts.
    'embed_hosts' => ['www.aparat.com', 'aparat.com'],

    // Disk that receives the one-way copy for the foreign mirror (config/filesystems.php).
    'foreign_disk' => env('FOREIGN_DISK', 'foreign'),

    // Public base URL of the foreign mirror (a reverse proxy to this app); empty = no mirror URLs.
    'foreign_url' => env('PERISCOPE_FOREIGN_URL'),

    // Editable settings and how each one is validated (see Admin\SettingController).
    'settings' => [
        'social_links' => ['array', 'max:20'],
        'support_items' => ['array', 'max:20'],
        'page_about' => ['nullable', 'string', 'max:100000'],
        'page_terms' => ['nullable', 'string', 'max:100000'],
        'page_install_guide' => ['nullable', 'string', 'max:100000'],
        'maintenance' => ['boolean'],
        'maintenance_message' => ['nullable', 'string', 'max:1000'],
    ],

    // Audio watermark. Turning `enabled` off skips audiowmark (plain 64 kbps copy) so playback can be
    // tried on a machine without it; it is refused in production, where a copy without a mark would be a leak.
    'watermark' => ['enabled' => (bool) env('WATERMARK_ENABLED', true)],

    // Signed URL lifetime for personalized audio, in minutes.
    'audio_url_ttl' => (int) env('PERISCOPE_AUDIO_URL_TTL', 120),

    // Admin panel idle timeout, in minutes.
    'admin_idle_timeout' => (int) env('PERISCOPE_ADMIN_IDLE_TIMEOUT', 30),

    // How long a personalized audio file is kept on the server, in days.
    'audio_retention_days' => (int) env('PERISCOPE_AUDIO_RETENTION_DAYS', 7),
];
