<?php

namespace App\Support\Coaching;

use Illuminate\Support\Str;

class SafeEmbedUrl
{
    /** @var array<int, string> */
    private const ALLOWED_HOST_SUFFIXES = [
        'youtube.com',
        'youtu.be',
        'loom.com',
        'canva.com',
        'docs.google.com',
        'drive.google.com',
    ];

    public static function isAllowed(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);
        foreach (self::ALLOWED_HOST_SUFFIXES as $suffix) {
            if ($host === $suffix || Str::endsWith($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }

    public static function embedSrc(?string $url): ?string
    {
        if (! self::isAllowed($url)) {
            return null;
        }

        if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/)([a-zA-Z0-9_-]+)~', $url, $m)) {
            return 'https://www.youtube.com/embed/'.$m[1];
        }

        if (Str::contains($url, 'loom.com/share/')) {
            return str_replace('/share/', '/embed/', $url);
        }

        if (Str::contains($url, 'docs.google.com')) {
            return preg_replace('~/edit.*$~', '/preview', $url) ?? $url;
        }

        if (Str::contains($url, 'drive.google.com/file/d/')) {
            if (preg_match('~/file/d/([^/]+)~', $url, $m)) {
                return 'https://drive.google.com/file/d/'.$m[1].'/preview';
            }
        }

        if (Str::contains($url, 'canva.com/design/')) {
            return $url.(Str::contains($url, '?') ? '&' : '?').'embed';
        }

        return $url;
    }
}
