<?php

namespace App\Support\KnowledgeBase;

use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

class KbMarkdownHtml
{
    public static function toHtml(string $markdown): string
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);

        $converter = new MarkdownConverter($environment);

        return $converter->convert($markdown)->getContent();
    }

    public static function titleFromMarkdown(string $markdown, string $fallbackBasename): string
    {
        if (preg_match('/^#\s+(.+)$/m', $markdown, $m)) {
            return trim($m[1]);
        }

        $name = preg_replace('/\.md$/i', '', $fallbackBasename) ?? $fallbackBasename;

        return Str::title(str_replace(['-', '_'], ' ', $name));
    }

    public static function excerptHtml(string $markdown): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === ''
                || str_starts_with($line, '#')
                || str_starts_with($line, '```')
                || str_starts_with($line, '|')
                || str_starts_with($line, '---')
                || str_starts_with($line, '>')
            ) {
                continue;
            }
            $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $line) ?? $line;
            $text = trim($text);
            if ($text === '') {
                continue;
            }
            $text = mb_substr($text, 0, 280);

            return '<p>'.e($text).'</p>';
        }

        return '<p>Tài liệu nội bộ VA-QLDA (seed từ repository).</p>';
    }

    public static function slugForRepoPath(string $relativePath): string
    {
        $normalized = str_replace('\\', '/', $relativePath);
        $normalized = preg_replace('/\.md$/i', '', $normalized) ?? $normalized;
        $base = Str::slug(str_replace('/', '-', $normalized));
        if ($base === '') {
            $base = 'tai-lieu';
        }

        return 'kb-'.$base;
    }
}
