<?php

namespace App\Support;

/**
 * Rich-text emptiness checks aligned with the daily report form (Tiptap HTML).
 */
final class DailyReportFieldContent
{
    public static function hasMeaningfulText(?string $html): bool
    {
        if ($html === null || $html === '') {
            return false;
        }

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = trim($text);

        return $text !== '';
    }
}
