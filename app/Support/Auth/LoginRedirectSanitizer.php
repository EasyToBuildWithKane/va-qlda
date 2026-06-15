<?php

namespace App\Support\Auth;

/**
 * Prevent open redirects after login (Google callback or intended URL).
 */
class LoginRedirectSanitizer
{
    public static function sanitize(?string $redirect, string $default = '/dashboard'): string
    {
        if ($redirect === null || $redirect === '') {
            return $default;
        }

        $redirect = trim($redirect);

        if (! str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            return $default;
        }

        if (str_contains($redirect, '://')) {
            return $default;
        }

        $lower = strtolower($redirect);
        if (str_starts_with($lower, '/login')
            || str_starts_with($lower, '/tech/login')
            || str_starts_with($lower, '/auth/google')
            || str_starts_with($lower, '/logout')) {
            return $default;
        }

        return $redirect;
    }
}
