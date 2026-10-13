<?php

namespace App\Support;

class EmbedUrl
{
    /**
     * Pull the player URL out of embed code an admin pasted. Only https URLs on allowed
     * video hosts are returned; the frontend builds its own iframe from this, so the raw
     * embed code never reaches a browser.
     */
    public static function extract(?string $embedCode): ?string
    {
        if ($embedCode === null || ! preg_match('~https?://[^\s"\'<>]+~i', $embedCode, $match)) {
            return null;
        }

        $parts = parse_url(html_entity_decode($match[0]));
        $host = strtolower($parts['host'] ?? '');

        if (! in_array($host, config('periscope.embed_hosts'), true)) {
            return null;
        }

        return 'https://'.$host.($parts['path'] ?? '').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
