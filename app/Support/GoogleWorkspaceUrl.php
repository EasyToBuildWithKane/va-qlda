<?php

namespace App\Support;

/**
 * Parse và chuẩn hoá link Google Docs / Sheets để nhúng preview.
 */
class GoogleWorkspaceUrl
{
    /**
     * @return array{type: 'document'|'spreadsheet', id: string, view_url: string, embed_url: string, default_title: string}|null
     */
    public static function parse(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parsed = parse_url($url);
        $host = strtolower($parsed['host'] ?? '');
        if (! in_array($host, ['docs.google.com', 'www.docs.google.com'], true)) {
            return null;
        }

        $path = $parsed['path'] ?? '';

        if (preg_match('#/document/d/([a-zA-Z0-9_-]+)#', $path, $m)) {
            $id = $m[1];

            return [
                'type' => 'document',
                'id' => $id,
                'view_url' => "https://docs.google.com/document/d/{$id}/edit",
                'embed_url' => "https://docs.google.com/document/d/{$id}/preview",
                'default_title' => 'Google Docs',
            ];
        }

        if (preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $path, $m)) {
            $id = $m[1];

            return [
                'type' => 'spreadsheet',
                'id' => $id,
                'view_url' => "https://docs.google.com/spreadsheets/d/{$id}/edit",
                'embed_url' => "https://docs.google.com/spreadsheets/d/{$id}/preview?rm=minimal",
                'default_title' => 'Google Sheets',
            ];
        }

        return null;
    }

    public static function isSupported(string $url): bool
    {
        return self::parse($url) !== null;
    }
}
