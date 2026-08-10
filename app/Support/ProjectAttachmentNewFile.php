<?php

namespace App\Support;

/**
 * Blank files created from the project documents panel (not uploaded).
 */
final class ProjectAttachmentNewFile
{
    /** @var list<string> */
    public const TYPES = ['txt', 'md', 'csv', 'json'];

    /**
     * @return array{ext: string, mime: string, content: string, label: string}|null
     */
    public static function definition(string $type): ?array
    {
        return match (strtolower(trim($type))) {
            'txt' => [
                'ext' => 'txt',
                'mime' => 'text/plain',
                'content' => '',
                'label' => 'Văn bản',
            ],
            'md' => [
                'ext' => 'md',
                'mime' => 'text/markdown',
                'content' => '',
                'label' => 'Markdown',
            ],
            'csv' => [
                'ext' => 'csv',
                'mime' => 'text/csv',
                'content' => '',
                'label' => 'CSV',
            ],
            'json' => [
                'ext' => 'json',
                'mime' => 'application/json',
                'content' => "{}\n",
                'label' => 'JSON',
            ],
            default => null,
        };
    }

    /**
     * Strip path segments and trailing extension; return a safe base name.
     */
    public static function sanitizeBaseName(string $name, string $ext): string
    {
        $base = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, trim($name)));
        $base = preg_replace('/\.'.$ext.'$/i', '', $base) ?? $base;
        $base = preg_replace('/[<>:"|?*\x00-\x1F]+/u', '', $base) ?? $base;
        $base = trim($base, " \t.·");

        return $base !== '' ? $base : 'Tai lieu moi';
    }

    public static function originalName(string $name, string $type): ?string
    {
        $def = self::definition($type);
        if ($def === null) {
            return null;
        }

        return self::sanitizeBaseName($name, $def['ext']).'.'.$def['ext'];
    }

    /** Extensions that can be previewed and edited as plain text in the panel. */
    public const TEXT_EDITABLE_EXTENSIONS = ['txt', 'md', 'csv', 'json', 'log', 'xml', 'yml', 'yaml', 'html', 'htm', 'css', 'js', 'ts', 'vue', 'php', 'ini', 'env'];

    public static function isTextEditableName(string $name, ?string $mime = null): bool
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== '' && in_array($ext, self::TEXT_EDITABLE_EXTENSIONS, true)) {
            return true;
        }

        $mime = strtolower((string) $mime);

        return str_starts_with($mime, 'text/')
            || in_array($mime, ['application/json', 'application/xml', 'application/javascript'], true);
    }

    /**
     * Rename a file while keeping its extension (unless the new title already includes it).
     */
    public static function renameKeepingExtension(string $currentName, string $newTitle): string
    {
        $ext = strtolower((string) pathinfo($currentName, PATHINFO_EXTENSION));
        $base = self::sanitizeBaseName($newTitle, $ext);

        return $ext !== '' ? $base.'.'.$ext : $base;
    }
}
