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
}
