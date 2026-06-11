<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Resolve a page URL (e.g. prnt.sc) to a direct HTTPS image URL for hover preview.
 */
class EvidenceLinkPreview
{
    private const DIRECT_IMAGE_PATTERN = '/\.(jpe?g|png|gif|webp|bmp)(\?|$)/i';

    /** Hosts allowed for server-side HTML fetch (screenshot / OG meta). */
    private const FETCHABLE_HOST_SUFFIXES = [
        'prnt.sc',
        'prntscr.com',
        'gyazo.com',
        'ibb.co',
        'imgur.com',
    ];

    public static function resolveImageUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https') {
            return null;
        }

        if (preg_match(self::DIRECT_IMAGE_PATTERN, $url)) {
            return self::sanitizeHttpsUrl($url);
        }

        $host = strtolower($parts['host'] ?? '');
        if (! self::isFetchableHost($host)) {
            return null;
        }

        try {
            $response = Http::timeout(8)
                ->withUserAgent('VA-QLDA/1.0 (evidence-link-preview)')
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $html = $response->body();

            if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)
                || preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $html, $m)) {
                return self::sanitizeHttpsUrl(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));
            }

            if (preg_match('/class=["\'][^"\']*screenshot-image[^"\']*["\'][^>]+src=["\']([^"\']+)["\']/i', $html, $m)
                || preg_match('/<img[^>]+class=["\'][^"\']*screenshot-image[^"\']*["\'][^>]+src=["\']([^"\']+)["\']/i', $html, $m)
                || preg_match('/id=["\']screenshot-image["\'][^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
                return self::sanitizeHttpsUrl(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private static function isFetchableHost(string $host): bool
    {
        foreach (self::FETCHABLE_HOST_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }

    private static function sanitizeHttpsUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https') {
            return null;
        }

        $host = $parts['host'] ?? '';
        if ($host === '') {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
        }

        return $url;
    }
}
