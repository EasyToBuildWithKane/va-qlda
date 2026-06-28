<?php

namespace App\Models;

use App\Support\Enums\WeeklyReportSection as SectionEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $weekly_report_id
 * @property SectionEnum $section
 * @property string|null $content
 * @property string|null $ai_content
 * @property bool $is_edited
 * @property int $sort_order
 */
class WeeklyReportSection extends Model
{
    protected $fillable = [
        'weekly_report_id',
        'section',
        'content',
        'ai_content',
        'is_edited',
        'sort_order',
    ];

    protected $casts = [
        'section' => SectionEnum::class,
        'is_edited' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(WeeklyReport::class, 'weekly_report_id');
    }
}
