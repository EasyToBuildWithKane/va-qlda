<?php

namespace App\Services\WeeklyReport;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\SystemAccount;
use App\Models\WeeklyReport;
use App\Models\WeeklyReportSection;
use App\Support\Enums\WeeklyReportSection as SectionEnum;
use App\Support\Enums\WeeklyReportStatus;
use App\Support\NotificationDispatcher;
use App\Support\SecurityAuditLogger;
use App\Support\WeeklyReport\Contracts\WeeklyReportGenerator;
use App\Support\WeeklyReport\SprintWeekResolver;
use App\Support\WeeklyReport\WeeklyReportDataCollector;
use App\Support\WeeklyReport\WeeklyReportDataHasher;
use Illuminate\Support\Facades\DB;

/**
 * Điều phối vòng đời báo cáo tuần: tạo draft, sinh nội dung (generate),
 * tạo lại có bảo toàn nội dung đã sửa (regenerate), và lưu chỉnh sửa.
 *
 * Pha 2 sẽ bổ sung các transition submit/approve/reject + version + notify.
 */
class WeeklyReportService
{
    public function __construct(
        private readonly WeeklyReportGenerator $generator,
        private readonly WeeklyReportDataCollector $collector,
        private readonly WeeklyReportDataHasher $hasher,
        private readonly SprintWeekResolver $weekResolver,
    ) {}

    /**
     * Lấy báo cáo của tuần (nếu có) hoặc tạo draft mới rồi generate ngay.
     */
    public function createForWeek(Project $project, ?Sprint $sprint, int $weekNumber, SystemAccount $actor): WeeklyReport
    {
        $window = $this->weekResolver->weekByNumber($sprint, $weekNumber);

        $report = WeeklyReport::query()
            ->where('project_id', $project->id)
            ->where('sprint_id', $sprint?->id)
            ->where('week_number', $window['week_number'])
            ->first();

        if (! $report) {
            $report = new WeeklyReport([
                'project_id' => $project->id,
                'sprint_id' => $sprint?->id,
                'week_number' => $window['week_number'],
                'week_start' => $window['start'],
                'week_end' => $window['end'],
                'title' => null,
                'status' => WeeklyReportStatus::Draft,
            ]);
            $report->save();
        }

        return $this->generate($report, $actor);
    }

    /**
     * Sinh (hoặc tạo lại) nội dung báo cáo.
     *
     * @param  bool  $preserveEdited  true = giữ nguyên nội dung các thẻ người dùng đã sửa
     */
    public function generate(WeeklyReport $report, SystemAccount $actor, bool $preserveEdited = false): WeeklyReport
    {
        $report->loadMissing(['project', 'sprint', 'sections']);

        $context = $this->collector->collect(
            $report->project,
            $report->sprint,
            $report->week_number,
            $report->week_start,
            $report->week_end,
        );

        $generated = $this->generator->generate($context);
        $hash = $this->hasher->hash($context);

        return DB::transaction(function () use ($report, $actor, $generated, $hash, $preserveEdited) {
            $hadEdits = $preserveEdited && $report->sections->where('is_edited', true)->isNotEmpty();

            $report->fill([
                'kpi_snapshot' => $generated->kpi,
                'meta' => $generated->meta,
                'ai_summary' => $generated->aiSummary,
                'data_hash' => $hash,
                'generated_at' => now(),
                'generated_by' => $actor->id,
                'status' => $hadEdits ? WeeklyReportStatus::Edited : WeeklyReportStatus::Generated,
            ]);

            if (! $preserveEdited) {
                $report->executive_summary = $generated->executiveSummary;
            } elseif ($report->executive_summary === null) {
                $report->executive_summary = $generated->executiveSummary;
            }

            $report->save();

            $this->syncSections($report, $generated->sections, $preserveEdited);

            return $report->load('sections');
        });
    }

    /**
     * Lưu chỉnh sửa của người dùng (3 thẻ chính + executive summary).
     *
     * @param  array{executive_summary?: string|null, sections?: array<int, array{section:string, content:string}>}  $data
     */
    public function saveDraft(WeeklyReport $report, array $data, SystemAccount $actor): WeeklyReport
    {
        return DB::transaction(function () use ($report, $data) {
            if (array_key_exists('executive_summary', $data)) {
                $report->executive_summary = $data['executive_summary'];
            }

            foreach ($data['sections'] ?? [] as $row) {
                $section = SectionEnum::tryFrom($row['section'] ?? '');
                if (! $section || ! $section->isEditable()) {
                    continue;
                }

                $report->sections()->updateOrCreate(
                    ['section' => $section->value],
                    [
                        'content' => $row['content'] ?? '',
                        'is_edited' => true,
                        'sort_order' => $section->sortOrder(),
                    ],
                );
            }

            if (! $report->status->isLocked()) {
                $report->status = WeeklyReportStatus::Edited;
            }
            $report->save();

            return $report->load('sections');
        });
    }

    /** Gửi duyệt (draft/generated/edited/rejected → submitted). */
    public function submit(WeeklyReport $report, SystemAccount $actor): WeeklyReport
    {
        return DB::transaction(function () use ($report, $actor) {
            $report->forceFill([
                'status' => WeeklyReportStatus::Submitted,
                'submitted_at' => now(),
                'submitted_by' => $actor->id,
            ])->save();

            $this->snapshot($report, $actor, 'Gửi duyệt');
            NotificationDispatcher::weeklyReportSubmitted($report, $actor);
            SecurityAuditLogger::weeklyReport($actor, 'submitted', $report->id, ['week' => $report->week_number]);

            return $report;
        });
    }

    /** Duyệt (submitted → approved, khoá nội dung). */
    public function approve(WeeklyReport $report, SystemAccount $actor): WeeklyReport
    {
        return DB::transaction(function () use ($report, $actor) {
            $report->forceFill([
                'status' => WeeklyReportStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $actor->id,
            ])->save();

            $this->snapshot($report, $actor, 'Duyệt');
            NotificationDispatcher::weeklyReportApproved($report, $actor);
            SecurityAuditLogger::weeklyReport($actor, 'approved', $report->id, ['week' => $report->week_number]);

            return $report;
        });
    }

    /** Trả lại (submitted → rejected, kèm lý do). */
    public function reject(WeeklyReport $report, SystemAccount $actor, string $reason): WeeklyReport
    {
        return DB::transaction(function () use ($report, $actor, $reason) {
            $report->forceFill([
                'status' => WeeklyReportStatus::Rejected,
                'rejected_at' => now(),
                'rejected_by' => $actor->id,
                'reject_reason' => $reason,
            ])->save();

            $this->snapshot($report, $actor, 'Trả lại: '.$reason);
            NotificationDispatcher::weeklyReportRejected($report, $actor);
            SecurityAuditLogger::weeklyReport($actor, 'rejected', $report->id, ['week' => $report->week_number]);

            return $report;
        });
    }

    /** Lưu một snapshot phiên bản tại thời điểm chuyển trạng thái. */
    private function snapshot(WeeklyReport $report, SystemAccount $actor, ?string $note = null): void
    {
        $report->loadMissing('sections');
        $next = (int) $report->versions()->max('version_number') + 1;

        $report->versions()->create([
            'version_number' => $next,
            'status' => $report->status->value,
            'snapshot' => [
                'executive_summary' => $report->executive_summary,
                'ai_summary' => $report->ai_summary,
                'kpi' => $report->kpi_snapshot,
                'meta' => $report->meta,
                'sections' => $report->sections
                    ->mapWithKeys(fn (WeeklyReportSection $s) => [$s->section->value => $s->content])
                    ->all(),
            ],
            'note' => $note,
            'created_by' => $actor->id,
            'created_at' => now(),
        ]);
    }

    /**
     * Upsert 6 section. Nếu preserveEdited: thẻ đã sửa giữ content, chỉ cập nhật ai_content.
     *
     * @param  array<string, string>  $sections
     */
    private function syncSections(WeeklyReport $report, array $sections, bool $preserveEdited): void
    {
        $existing = $report->sections->keyBy(fn (WeeklyReportSection $s) => $s->section->value);

        foreach (SectionEnum::cases() as $section) {
            $aiText = $sections[$section->value] ?? '';
            $current = $existing->get($section->value);
            $keepUserContent = $preserveEdited
                && $section->isEditable()
                && $current
                && $current->is_edited;

            $report->sections()->updateOrCreate(
                ['section' => $section->value],
                [
                    'ai_content' => $aiText,
                    'content' => $keepUserContent ? $current->content : $aiText,
                    'is_edited' => $keepUserContent,
                    'sort_order' => $section->sortOrder(),
                ],
            );
        }
    }
}
