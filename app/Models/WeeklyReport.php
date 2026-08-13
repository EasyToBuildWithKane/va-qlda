<?php

namespace App\Models;

use App\Support\Enums\WeeklyReportSection as SectionEnum;
use App\Support\Enums\WeeklyReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $sprint_id
 * @property int $week_number
 * @property \Illuminate\Support\Carbon $week_start
 * @property \Illuminate\Support\Carbon $week_end
 * @property string|null $title
 * @property WeeklyReportStatus $status
 * @property string|null $executive_summary
 * @property string|null $ai_summary
 * @property array<string, mixed>|null $kpi_snapshot
 * @property array<string, mixed>|null $meta
 * @property string|null $data_hash
 * @property \Illuminate\Support\Carbon|null $generated_at
 * @property int|null $generated_by
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property int|null $submitted_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $rejected_at
 * @property int|null $rejected_by
 * @property string|null $reject_reason
 */
class WeeklyReport extends Model
{
    protected $fillable = [
        'project_id',
        'sprint_id',
        'week_number',
        'week_start',
        'week_end',
        'title',
        'status',
        'executive_summary',
        'ai_summary',
        'kpi_snapshot',
        'meta',
        'data_hash',
        'generated_at',
        'generated_by',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'reject_reason',
    ];

    protected $casts = [
        'week_number' => 'integer',
        'week_start' => 'date',
        'week_end' => 'date',
        'status' => WeeklyReportStatus::class,
        'kpi_snapshot' => 'array',
        'meta' => 'array',
        'generated_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    // ---- Relations ------------------------------------------------------

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(WeeklyReportSection::class)->orderBy('sort_order');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WeeklyReportVersion::class)->orderByDesc('version_number');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'generated_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'approved_by');
    }

    // ---- Scopes ---------------------------------------------------------

    /**
     * @param  Builder<WeeklyReport>  $query
     * @return Builder<WeeklyReport>
     */
    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * @param  Builder<WeeklyReport>  $query
     * @return Builder<WeeklyReport>
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('week_start')->orderByDesc('week_number');
    }

    // ---- Helpers --------------------------------------------------------

    /** Mã hiển thị, vd "WR-3-W2" (sprint 3, kỳ 2). */
    public function code(): string
    {
        $sprint = $this->sprint_id ?? '0';

        return "WR-{$sprint}-W{$this->week_number}";
    }

    /** Nhãn khoảng ngày, vd "04/08/2026 – 10/08/2026". */
    public function periodLabel(): string
    {
        if (! $this->week_start || ! $this->week_end) {
            return '';
        }

        return $this->week_start->format('d/m/Y').' – '.$this->week_end->format('d/m/Y');
    }

    public function isLocked(): bool
    {
        return $this->status->isLocked();
    }

    /** Section nội dung đã được người dùng chỉnh sửa (không cho regenerate ghi đè). */
    public function editedSectionKeys(): array
    {
        return $this->sections
            ->where('is_edited', true)
            ->pluck('section')
            ->all();
    }

    /** Có thể tạo lại vì dữ liệu Sprint đã đổi so với lần generate gần nhất. */
    public function regenerationAvailable(?string $currentHash): bool
    {
        return $this->generated_at !== null
            && $currentHash !== null
            && $this->data_hash !== null
            && $this->data_hash !== $currentHash;
    }

    /** @return array<int, SectionEnum> */
    public static function sectionOrder(): array
    {
        return SectionEnum::cases();
    }
}
