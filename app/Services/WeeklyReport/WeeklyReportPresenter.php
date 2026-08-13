<?php

namespace App\Services\WeeklyReport;

use App\Http\Resources\WeeklyReportResource;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\WeeklyReport;
use App\Support\Enums\SprintStatus;
use App\Support\WeeklyReport\SprintWeekResolver;
use App\Support\WeeklyReport\WeeklyReportDataCollector;
use App\Support\WeeklyReport\WeeklyReportDataHasher;
use Illuminate\Http\Request;

/**
 * Dựng dữ liệu props cho tab "Báo cáo tuần" trên trang Project/Show:
 * tổng quan (sprint + kỳ mặc định + danh sách báo cáo) và chi tiết một kỳ.
 */
class WeeklyReportPresenter
{
    public function __construct(
        private readonly SprintWeekResolver $weekResolver,
        private readonly WeeklyReportDataCollector $collector,
        private readonly WeeklyReportDataHasher $hasher,
    ) {}

    /**
     * Sprint dùng cho báo cáo tuần: ưu tiên Sprint đang chạy, sau đó mới nhất.
     */
    public function activeSprint(Project $project): ?Sprint
    {
        $sprints = $project->relationLoaded('sprints') ? $project->sprints : $project->sprints()->get();

        return $sprints->firstWhere('status', SprintStatus::Active)
            ?? $sprints->sortByDesc(fn (Sprint $s) => $s->start_date ?? $s->created_at)->first();
    }

    /**
     * Tổng quan: sprint + kỳ mặc định (T2–CN hiện tại) + danh sách báo cáo đã tạo.
     *
     * @return array<string, mixed>
     */
    public function overview(Project $project): array
    {
        $sprint = $this->activeSprint($project);
        $current = $this->weekResolver->currentWeek($sprint);

        $reports = WeeklyReport::query()
            ->forProject($project->id)
            ->when(
                $sprint,
                fn ($q) => $q->where('sprint_id', $sprint->id),
                fn ($q) => $q->whereNull('sprint_id'),
            )
            ->latestFirst()
            ->get();

        return [
            'sprint' => $sprint ? ['id' => $sprint->id, 'name' => $sprint->name] : null,
            'default_start' => $current['start']->toDateString(),
            'default_end' => $current['end']->toDateString(),
            'reports' => $reports->map(fn (WeeklyReport $report) => [
                'id' => $report->id,
                'week_start' => $report->week_start->toDateString(),
                'week_end' => $report->week_end->toDateString(),
                'status' => $report->status->value,
                'status_label' => $report->status->label(),
            ])->all(),
        ];
    }

    /**
     * Chi tiết một tuần (theo ?wr report id) cho partial reload.
     * Trả null nếu request không yêu cầu chi tiết.
     *
     * @return array<string, mixed>|null
     */
    public function detail(Project $project, Request $request): ?array
    {
        $wr = $request->integer('wr');
        if (! $wr) {
            return null;
        }

        $report = WeeklyReport::query()
            ->forProject($project->id)
            ->with(['sprint', 'sections', 'generatedBy', 'submittedBy', 'approvedBy', 'versions.createdBy'])
            ->find($wr);

        if (! $report) {
            return null;
        }

        $report->setRelation('project', $project);

        $data = (new WeeklyReportResource($report))->toArray($request);
        $data['regeneration_available'] = $this->regenerationAvailable($project, $report);
        $data['versions'] = $report->versions->map(fn ($v) => [
            'version_number' => $v->version_number,
            'status' => $v->status,
            'note' => $v->note,
            'created_by' => $v->createdBy?->display_name,
            'created_at' => $v->created_at?->toIso8601String(),
        ])->all();

        return $data;
    }

    private function regenerationAvailable(Project $project, WeeklyReport $report): bool
    {
        if ($report->generated_at === null || $report->data_hash === null) {
            return false;
        }

        $context = $this->collector->collect(
            $project,
            $report->sprint,
            $report->week_number,
            $report->week_start,
            $report->week_end,
        );

        return $report->data_hash !== $this->hasher->hash($context);
    }
}
