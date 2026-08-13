<?php

namespace App\Support\WeeklyReport;

/**
 * Gỡ markdown / thinking token / kí hiệu AI khỏi văn bản báo cáo tuần.
 */
final class WeeklyReportPlainText
{
    public static function unwrapRaw(string $raw): string
    {
        $text = self::stripThinking(trim($raw));

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $text, $m)) {
            return trim($m[1]);
        }

        return trim($text);
    }

    public static function sanitize(string $text): string
    {
        $text = self::stripThinking($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $text = preg_replace('/```[\w]*\s*/', '', $text) ?? $text;
        $text = preg_replace('/\[([^\]\n]+)\]\([^)]+\)/', '$1', $text) ?? $text;

        for ($i = 0; $i < 3; $i++) {
            $text = preg_replace('/\*\*\*(.+?)\*\*\*/s', '$1', $text) ?? $text;
            $text = preg_replace('/\*\*(.+?)\*\*/s', '$1', $text) ?? $text;
            $text = preg_replace('/__(.+?)__/s', '$1', $text) ?? $text;
        }

        $text = preg_replace('/`([^`\n]+)`/', '$1', $text) ?? $text;
        $text = preg_replace('/(?<!\w)\*(?!\s|\*)(.+?)(?<!\s|\*)\*(?!\w)/s', '$1', $text) ?? $text;
        $text = preg_replace('/\[([^\]\n]+)\](?!\()/', '$1', $text) ?? $text;
        $text = str_replace([' → ', ' -> ', '⇒'], ': ', $text);
        $text = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '', $text) ?? $text;
        $text = str_replace(['**', '__', '```', '`'], '', $text);

        $lines = [];
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            $line = preg_replace('/^#{1,6}\s+/', '', $line) ?? $line;
            $line = preg_replace('/^(?:[-*+•]|\d+[.)])\s+/u', '', $line) ?? $line;
            $line = trim($line);
            if ($line === '' || preg_match('/^[-*_~]{3,}$/', $line)) {
                continue;
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    private static function stripThinking(string $text): string
    {
        $text = preg_replace('/<(?:think|thinking)\b[^>]*>.*?<\/(?:think|thinking)>/is', '', $text) ?? $text;
        $text = preg_replace('/<\|begin_of_(?:thought|reasoning|solution)\|>.*?<\|end_of_(?:thought|reasoning|solution)\|>/is', '', $text) ?? $text;
        $text = preg_replace('/<\/?(?:think|thinking)>/i', '', $text) ?? $text;
        $text = preg_replace('/<\|(?:begin|end)_of_(?:thought|reasoning|solution)\|>/i', '', $text) ?? $text;

        return $text;
    }
}
