<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Resolve paths on the public disk to browser-ready URLs (or null if missing).
 */
class PublicMediaUrl
{
    public static function fromPublicDisk(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        if (Str::startsWith($path, '/storage/')) {
            return url($path);
        }

        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            return $disk->url($path);
        }

        // Legacy rows: filename only (no directory).
        if (! str_contains($path, '/')) {
            foreach (['avatars/'.$path, 'employees/'.$path, $path] as $candidate) {
                if ($disk->exists($candidate)) {
                    return $disk->url($candidate);
                }
            }
        }

        return null;
    }
}
