<?php

namespace App\Support;

/**
 * Link ngoài cho tài liệu dự án: Google Docs/Sheets và PDF (URL trực tiếp hoặc Google Drive).
 */
class ProjectAttachmentExternalUrl
{
    /**
     * @return array{
     *     type: 'document'|'spreadsheet'|'pdf',
     *     view_url: string,
     *     embed_url: string,
     *     default_title: string,
     *     mime_type: string
     * }|null
     */
    public static function parse(string $url): ?array
    {
        $google = GoogleWorkspaceUrl::parse($url);
        if ($google !== null) {
            return [
                'type' => $google['type'],
                'view_url' => $google['view_url'],
                'embed_url' => $google['embed_url'],
                'default_title' => $google['default_title'],
                'mime_type' => $google['type'] === 'document'
                    ? 'application/vnd.google-apps.document'
                    : 'application/vnd.google-apps.spreadsheet',
            ];
        }

        $pdf = self::parsePdfLink($url);

        return $pdf;
    }

    public static function isSupported(string $url): bool
    {
        return self::parse($url) !== null;
    }

    /**
     * @return array{
     *     type: 'pdf',
     *     view_url: string,
     *     embed_url: string,
     *     default_title: string,
     *     mime_type: string
     * }|null
     */
    private static function parsePdfLink(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        if (! preg_match('#^https://#i', $url)) {
            return null;
        }

        $parsed = parse_url($url);
        if ($parsed === false) {
            return null;
        }

        $host = strtolower($parsed['host'] ?? '');
        $path = $parsed['path'] ?? '';

        if (in_array($host, ['drive.google.com', 'www.drive.google.com'], true)) {
            if (preg_match('#/file/d/([a-zA-Z0-9_-]+)#', $path, $m)) {
                $id = $m[1];

                return self::pdfResult(
                    "https://drive.google.com/file/d/{$id}/view",
                    "https://drive.google.com/file/d/{$id}/preview",
                    'PDF (Google Drive)',
                );
            }

            $query = $parsed['query'] ?? '';
            if ($query !== '' && preg_match('/(?:^|&)id=([a-zA-Z0-9_-]+)/', $query, $m)) {
                $id = $m[1];

                return self::pdfResult(
                    "https://drive.google.com/file/d/{$id}/view",
                    "https://drive.google.com/file/d/{$id}/preview",
                    'PDF (Google Drive)',
                );
            }
        }

        if (preg_match('/\.pdf$/i', $path)) {
            $view = self::stripFragment($url);

            return self::pdfResult($view, $view, 'PDF');
        }

        return null;
    }

    /**
     * @return array{
     *     type: 'pdf',
     *     view_url: string,
     *     embed_url: string,
     *     default_title: string,
     *     mime_type: string
     * }
     */
    private static function pdfResult(string $viewUrl, string $embedUrl, string $defaultTitle): array
    {
        return [
            'type' => 'pdf',
            'view_url' => $viewUrl,
            'embed_url' => $embedUrl,
            'default_title' => $defaultTitle,
            'mime_type' => 'application/pdf',
        ];
    }

    private static function stripFragment(string $url): string
    {
        $pos = strpos($url, '#');

        return $pos === false ? $url : substr($url, 0, $pos);
    }
}
