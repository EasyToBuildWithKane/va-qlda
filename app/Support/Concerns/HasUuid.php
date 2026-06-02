<?php

namespace App\Support\Concerns;

use Illuminate\Support\Str;

/**
 * Adds an opaque public `uuid` to a model with a bigint primary key (D1).
 * The PK stays auto-increment (fast FK joins); the uuid is for external/API
 * references. Auto-filled on create when not provided.
 */
trait HasUuid
{
    public static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
