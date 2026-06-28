<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $weekly_report_id
 * @property int $version_number
 * @property string $status
 * @property array<string, mixed> $snapshot
 * @property string|null $note
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class WeeklyReportVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'weekly_report_id',
        'version_number',
        'status',
        'snapshot',
        'note',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(WeeklyReport::class, 'weekly_report_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'created_by');
    }
}
