<?php

namespace App\Support\Auth;

/**
 * Emails allowed to sign in via /tech/login (VA QLDA — Phòng Công nghệ).
 */
final class TechLoginAccess
{
    /**
     * @return list<string>
     */
    public static function allowedEmails(): array
    {
        $raw = config('va.tech_login_allowed_emails', []);

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $email): string => strtolower(trim((string) $email)),
            $raw,
        ))));
    }

    public static function isAllowedEmail(?string $email): bool
    {
        if ($email === null || trim($email) === '') {
            return false;
        }

        return in_array(strtolower(trim($email)), self::allowedEmails(), true);
    }
}
