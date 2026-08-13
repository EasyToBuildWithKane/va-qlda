<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\RejectWeeklyReportRequest;
use App\Http\Requests\Project\StoreWeeklyReportRequest;
use App\Http\Requests\Project\UpdateWeeklyReportRequest;
use App\Models\Project;
use App\Models\WeeklyReport;
use App\Services\WeeklyReport\Export\WeeklyReportDocxExporter;
use App\Services\WeeklyReport\Export\WeeklyReportPdfExporter;
use App\Services\WeeklyReport\WeeklyReportPresenter;
use App\Services\WeeklyReport\WeeklyReportService;
use App\Support\Enums\WeeklyReportStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class WeeklyReportController extends Controller
{
    public function __construct(
        private readonly WeeklyReportService $service,
        private readonly WeeklyReportPresenter $presenter,
    ) {}

    /** Tạo draft cho khoảng ngày đã chọn rồi tổng hợp nội dung ngay. */
    public function store(StoreWeeklyReportRequest $request, Project $project): RedirectResponse
    {
        $sprint = $this->presenter->activeSprint($project);
        $data = $request->validated();

        if (! empty($data['week_start']) && ! empty($data['week_end'])) {
            $report = $this->service->createForPeriod(
                $project,
                $sprint,
                Carbon::parse($data['week_start']),
                Carbon::parse($data['week_end']),
                $request->user(),
            );
        } else {
            $report = $this->service->createForWeek(
                $project,
                $sprint,
                (int) $data['week_number'],
                $request->user(),
            );
        }

        return $this->backToReport($project, $report, 'Đã tạo báo cáo tuần.');
    }

    /** Lưu chỉnh sửa của người dùng (3 thẻ chính + tóm tắt điều hành). */
    public function update(UpdateWeeklyReportRequest $request, Project $project, WeeklyReport $weeklyReport): RedirectResponse
    {
        $this->ensureOwnership($project, $weeklyReport);

        if ($weeklyReport->isLocked()) {
            return back()->with('error', 'Báo cáo đã được duyệt — không thể chỉnh sửa.');
        }

        $this->service->saveDraft($weeklyReport, $request->validated(), $request->user());

        return $this->backToReport($project, $weeklyReport, 'Đã lưu báo cáo tuần.');
    }

    /** Tổng hợp lại toàn bộ (ghi đè mọi thẻ). */
    public function generate(Project $project, WeeklyReport $weeklyReport): RedirectResponse
    {
        $this->ensureOwnership($project, $weeklyReport);
        $this->authorize('generate', [WeeklyReport::class, $project]);

        if ($weeklyReport->isLocked()) {
            return back()->with('error', 'Báo cáo đã được duyệt — không thể tạo lại.');
        }

        $this->service->generate($weeklyReport, request()->user());

        return $this->backToReport($project, $weeklyReport, 'Đã tổng hợp lại báo cáo.');
    }

    /** Tạo lại nhưng giữ nguyên nội dung các thẻ người dùng đã sửa. */
    public function regenerate(Project $project, WeeklyReport $weeklyReport): RedirectResponse
    {
        $this->ensureOwnership($project, $weeklyReport);
        $this->authorize('generate', [WeeklyReport::class, $project]);

        if ($weeklyReport->isLocked()) {
            return back()->with('error', 'Báo cáo đã được duyệt — không thể tạo lại.');
        }

        $this->service->generate($weeklyReport, request()->user(), preserveEdited: true);

        return $this->backToReport($project, $weeklyReport, 'Đã cập nhật phần dữ liệu thay đổi.');
    }

    /** Gửi duyệt báo cáo. */
    public function submit(Project $project, WeeklyReport $weeklyReport): RedirectResponse
    {
        $this->ensureOwnership($project, $weeklyReport);
        $this->authorize('submit', $weeklyReport);

        if (! in_array($weeklyReport->status, [
            WeeklyReportStatus::Draft, WeeklyReportStatus::Generated,
            WeeklyReportStatus::Edited, WeeklyReportStatus::Rejected,
        ], true)) {
            return back()->with('error', 'Báo cáo không ở trạng thái có thể gửi duyệt.');
        }

        $this->service->submit($weeklyReport, request()->user());

        return $this->backToReport($project, $weeklyReport, 'Đã gửi báo cáo đi duyệt.');
    }

    /** Duyệt báo cáo. */
    public function approve(Project $project, WeeklyReport $weeklyReport): RedirectResponse
    {
        $this->ensureOwnership($project, $weeklyReport);
        $this->authorize('approve', $weeklyReport);

        if ($weeklyReport->status !== WeeklyReportStatus::Submitted) {
            return back()->with('error', 'Chỉ duyệt được báo cáo đang chờ duyệt.');
        }

        $this->service->approve($weeklyReport, request()->user());

        return $this->backToReport($project, $weeklyReport, 'Đã duyệt báo cáo tuần.');
    }

    /** Trả lại báo cáo kèm lý do. */
    public function reject(RejectWeeklyReportRequest $request, Project $project, WeeklyReport $weeklyReport): RedirectResponse
    {
        $this->ensureOwnership($project, $weeklyReport);

        if ($weeklyReport->status !== WeeklyReportStatus::Submitted) {
            return back()->with('error', 'Chỉ trả lại được báo cáo đang chờ duyệt.');
        }

        $this->service->reject($weeklyReport, $request->user(), $request->validated('reason'));

        return $this->backToReport($project, $weeklyReport, 'Đã trả lại báo cáo.');
    }

    /** Xuất PDF. */
    public function exportPdf(Project $project, WeeklyReport $weeklyReport, WeeklyReportPdfExporter $exporter): Response
    {
        $this->ensureOwnership($project, $weeklyReport);
        $this->authorize('export', $weeklyReport);

        return $exporter->download($weeklyReport);
    }

    /** Xuất DOCX. */
    public function exportDocx(Project $project, WeeklyReport $weeklyReport, WeeklyReportDocxExporter $exporter): Response
    {
        $this->ensureOwnership($project, $weeklyReport);
        $this->authorize('export', $weeklyReport);

        return $exporter->download($weeklyReport);
    }

    private function ensureOwnership(Project $project, WeeklyReport $report): void
    {
        abort_unless($report->project_id === $project->id, 404);
    }

    private function backToReport(Project $project, WeeklyReport $report, string $message): RedirectResponse
    {
        $tab = $this->resolveReturnTab();

        return redirect()
            ->route('projects.show', ['project' => $project->id, 'tab' => $tab, 'wr' => $report->id])
            ->with('success', $message);
    }

    /** Tab Tổng quan hoặc Báo cáo tuần — giữ ngữ cảnh UI sau thao tác. */
    private function resolveReturnTab(): string
    {
        $tab = request()->input('tab', request()->query('tab', 'weekly'));

        return in_array($tab, ['overview', 'weekly'], true) ? $tab : 'weekly';
    }
}
