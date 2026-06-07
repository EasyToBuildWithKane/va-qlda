<?php

namespace App\Support;

/**
 * Khớp tìm kiếm tiếng Việt — mirror {@see resources/js/shared/utils/normalizeSearchKey.js}.
 */
class VietnameseSearch
{
    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace('đ', 'd', $value);
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $value = $normalized;
            }
        }
        $value = preg_replace('/\p{M}/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @param  array<int, string|null>  $fields
     */
    public static function matches(array $fields, string $query): bool
    {
        $q = self::normalize($query);
        if ($q === '') {
            return true;
        }

        $parts = [];
        foreach ($fields as $field) {
            $n = self::normalize((string) ($field ?? ''));
            if ($n !== '') {
                $parts[] = $n;
            }
        }
        $haystack = implode(' ', $parts);
        if ($haystack === '') {
            return false;
        }
        if (str_contains($haystack, $q)) {
            return true;
        }

        $tokens = array_values(array_filter(explode(' ', $q)));
        if ($tokens === []) {
            return true;
        }

        foreach ($tokens as $token) {
            if (! str_contains($haystack, $token)) {
                return false;
            }
        }

        return true;
    }
}
