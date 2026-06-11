<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin-editable key→value override for runtime configuration.
 *
 * Values are JSON-encoded text; typing/decoding is handled by
 * {@see \App\Support\Settings\SettingsRepository} against the schema. The
 * table stores ONLY overrides — defaults live in
 * {@see \App\Support\Settings\SettingsSchema}, so an empty table is valid.
 *
 * @property string $key
 * @property string|null $value
 * @property int|null $updated_by
 */
class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = [
        'key',
        'value',
        'updated_by',
    ];
}
