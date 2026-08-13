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
 * tổng quan (sprint + danh sách tuần + trạng thái) và chi tiết một tuần.
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
     * Tổng quan: sprint + các tuần (kèm trạng thái report nếu đã có).
     *
     * @return array<string, mixed>
     */
    public function overview(Project $project): array
    {
        $sprint = $this->activeSprint($project);
        $weeks = $this->weekResolver->weeks($sprint);
        $current = $this->weekResolver->currentWeek($sprint);

        $reports = WeeklyReport::query()
            ->forProject($project->id)
            ->where('sprint_id', $sprint?->id)
            ->get()
            ->keyBy('week_number');

        $weekRows = array_map(function (array $w) use ($reports) {
            $report = $reports->get($w['week_number']);

            return [
                'week_number' => $w['week_number'],
                'week_start' => $w['start']->toDateString(),
                'week_end' => $w['end']->toDateString(),
                'report_id' => $report?->id,
                'status' => $report?->status->value,
                'status_label' => $report?->status->label(),
            ];
        }, $weeks);

        return [
            'sprint' => $sprint ? ['id' => $sprint->id, 'name' => $sprint->name] : null,
            'current_week' => $current['week_number'],
            'weeks' => $weekRows,
            'engine' => $this->enginePayload(),
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

    /**
     * Trạng thái engine (không lộ API key) — UI badge «Tổng hợp bằng AI».
     *
     * @return array{mode: string, provider: string|null, model: string|null}
     */
    private function enginePayload(): array
    {
        $configured = (bool) config('weekly_report.llm.enabled')
            && filled(config('weekly_report.llm.api_key'));

        return [
            'mode' => $configured ? 'llm' : 'heuristic',
            'provider' => $configured ? (string) config('weekly_report.llm.provider', 'openai') : null,
            'model' => $configured ? (string) config('weekly_report.llm.model') : null,
        ];
    }
}
