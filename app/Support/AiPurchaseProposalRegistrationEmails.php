<?php

namespace App\Support;

final class AiPurchaseProposalRegistrationEmails
{
    /**
     * @param  list<string>|null  $emails
     * @return list<string>
     */
    public static function normalize(?array $emails, int $staffCount, ?string $legacySingle = null): array
    {
        $slots = max(1, $staffCount);
        $out = array_fill(0, $slots, '');
        $source = $emails ?? [];

        for ($i = 0; $i < $slots; $i++) {
            $out[$i] = trim((string) ($source[$i] ?? ''));
        }

        $legacy = trim((string) ($legacySingle ?? ''));
        if ($legacy !== '' && $out[0] === '') {
            $out[0] = $legacy;
        }

        return $out;
    }

    /**
     * @param  list<string>  $emails
     */
    public static function firstFilled(array $emails): ?string
    {
        foreach ($emails as $email) {
            $email = trim($email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $emails
     */
    public static function formatForDocument(array $emails): string
    {
        $filled = array_values(array_filter(array_map('trim', $emails)));

        return $filled !== [] ? implode(', ', $filled) : '—';
    }
}
