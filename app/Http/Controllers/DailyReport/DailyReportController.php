<?php

namespace App\Http\Controllers\DailyReport;

use App\Application\DailyReport\CreateDailyReportUseCase;
use App\Application\DailyReport\DeleteDailyReportUseCase;
use App\Application\DailyReport\SubmitDailyReportUseCase;
use App\Application\DailyReport\UpdateDailyReportUseCase;
use App\Domain\DailyReport\Exceptions\DailyReportException;
use App\Domain\DailyReport\Models\DailyReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\DailyReport\StoreDailyReportRequest;
use App\Http\Requests\DailyReport\UpdateDailyReportRequest;
use App\Http\Resources\DailyReportResource;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\DailyReportCalendar;
use App\Support\DailyReportFieldContent;
use App\Support\Enums\Grade;
use App\Support\Enums\ReportStatus;
use App\Support\Enums\SystemRole;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DailyReportController extends Controller
{
    /**
     * Report history with filters. Members see only their own reports.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DailyReport::class);

        $account = $request->user();
        $isMember = $account->role === SystemRole::Member;

        $query = $this->historyReportsQuery($request, $account);

        $perPage = (int) $request->query('per_page', 10);
        if (! in_array($perPage, [5, 10, 15, 20], true)) {
            $perPage = 10;
        }

        $summary = $this->summaryWithTrend($request, $account);

        $reports = $query->paginate($perPage)->withQueryString();

        return Inertia::render('DailyReport/History', [
            'reports' => DailyReportResource::collection($reports),
            'summary' => $summary,
            'filters' => (object) $request->only([
                'status', 'project_id', 'employee_id', 'employee_ids', 'grade', 'q', 'from', 'to', 'per_page', 'late', 'group',
            ]),
            'statuses' => collect(ReportStatus::cases())
                ->map(fn (ReportStatus $s) => ['value' => $s->value, 'label' => $s->label()]),
            'grades' => collect(Grade::cases())
                ->map(fn (Grade $g) => ['value' => $g->value, 'label' => $g->label()]),
            'projects' => Options::projects(),
            'employees' => $isMember ? [] : Options::employees(),
            'canFilterEmployee' => ! $isMember,
            'canReview' => $account->allows('daily_report.review'),
        ]);
    }

    /**
     * Full filtered dataset (no pagination) for the styled Excel export. Uses
     * the exact same filters as the list so the workbook matches the screen.
     * Member self-scoping is enforced inside historyReportsQuery().
     */
    public function exportData(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', DailyReport::class);

        $rows = $this->historyReportsQuery($request, $request->user())
            ->limit(5000)
            ->get();

        return DailyReportResource::collection($rows)->response();
    }

    /**
     * Build the filtered history query. Pass $withDates = false to apply every
     * filter except the from/to window (used by the trend comparison so a custom
     * date window can be substituted for the same filter set).
     */
    private function historyReportsQuery(Request $request, SystemAccount $account, bool $withDates = true): Builder
    {
        $isMember = $account->role === SystemRole::Member;

        $query = DailyReport::query()
            ->with(['employee', 'score'])
            ->latest('date');

        if ($isMember) {
            $query->where('employee_id', $account->employee_id);
        } elseif ($employeeIds = $this->employeeIdFilter($request)) {
            $query->whereIn('employee_id', $employeeIds);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', $projectId);
        }
        if ($grade = $request->query('grade')) {
            $query->whereHas('score', fn ($q) => $q->where('grade', $grade));
        }
        if ($request->boolean('late')) {
            $query->where('is_late', true);
        }
        if ($keyword = trim((string) $request->query('q'))) {
            $like = '%'.$keyword.'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhereHas('employee', fn (Builder $e) => $e->where('full_name', 'like', $like));
            });
        }
        if ($withDates) {
            if ($from = $request->query('from')) {
                $query->whereDate('date', '>=', $from);
            }
            if ($to = $request->query('to')) {
                $query->whereDate('date', '<=', $to);
            }
        }

        return $query;
    }

    /**
     * Normalised list of employee ids to filter by (supports multi-select
     * `employee_ids[]` plus the legacy single `employee_id`). Capped to 100.
     *
     * @return int[]
     */
    private function employeeIdFilter(Request $request): array
    {
        $ids = $request->query('employee_ids', []);
        if (! is_array($ids)) {
            $ids = [$ids];
        }
        if ($single = $request->query('employee_id')) {
            $ids[] = $single;
        }

        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->take(100)
            ->values()
            ->all();
    }

    /**
     * Filtered-set counts plus a period-over-period trend. The big tiles show
     * the filtered summary; the trend pills compare the current window against
     * the immediately preceding window of equal length.
     *
     * @return array<string, mixed>
     */
    private function summaryWithTrend(Request $request, SystemAccount $account): array
    {
        $summary = $this->countSummary($this->historyReportsQuery($request, $account));

        [$curFrom, $curTo, $prevFrom, $prevTo] = $this->trendWindows($request);

        $current = $this->countSummary(
            $this->historyReportsQuery($request, $account, withDates: false)
                ->whereBetween('date', [$curFrom, $curTo]),
        );
        $previous = $this->countSummary(
            $this->historyReportsQuery($request, $account, withDates: false)
                ->whereBetween('date', [$prevFrom, $prevTo]),
        );

        $metrics = ['total', 'reviewed', 'submitted', 'draft', 'late', 'completion_rate'];
        $trend = [];
        foreach ($metrics as $key) {
            $trend[$key] = $this->percentChange($previous[$key], $current[$key]);
        }

        return [
            ...$summary,
            'trend' => $trend,
            'period' => [
                'current' => ['from' => $curFrom, 'to' => $curTo],
                'previous' => ['from' => $prevFrom, 'to' => $prevTo],
            ],
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function countSummary(Builder $query): array
    {
        $total = (clone $query)->count();
        $reviewed = (clone $query)->where('status', ReportStatus::Reviewed)->count();

        return [
            'total' => $total,
            'draft' => (clone $query)->where('status', ReportStatus::Draft)->count(),
            'submitted' => (clone $query)->where('status', ReportStatus::Submitted)->count(),
            'reviewed' => $reviewed,
            'late' => (clone $query)->where('is_late', true)->count(),
            'completion_rate' => $total > 0 ? round($reviewed / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Current/previous comparison windows. If from/to is set, the previous
     * window is the equal-length span ending the day before `from`; otherwise
     * the default is the last 30 days vs the prior 30 days.
     *
     * @return array{0:string,1:string,2:string,3:string} [curFrom, curTo, prevFrom, prevTo]
     */
    private function trendWindows(Request $request): array
    {
        $from = $request->query('from');
        $to = $request->query('to');

        if ($from && $to) {
            $curFrom = Carbon::parse($from)->startOfDay();
            $curTo = Carbon::parse($to)->startOfDay();
        } else {
            $curTo = Carbon::parse(DailyReportCalendar::today());
            $curFrom = $curTo->copy()->subDays(29);
        }

        $lengthDays = $curFrom->diffInDays($curTo) + 1;
        $prevTo = $curFrom->copy()->subDay();
        $prevFrom = $prevTo->copy()->subDays($lengthDays - 1);

        return [
            $curFrom->toDateString(),
            $curTo->toDateString(),
            $prevFrom->toDateString(),
            $prevTo->toDateString(),
        ];
    }

    /** Signed percent change from previous → current, rounded to 1 dp. */
    private function percentChange(int|float $previous, int|float $current): ?float
    {
        if ($previous == 0) {
            return $current == 0 ? 0.0 : null; // null = "new" (no prior baseline)
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    /**
     * "My Report (Today)" — today's draft, or an empty form.
     */
    public function today(Request $request): Response
    {
        $this->authorize('create', DailyReport::class);

        $today = DailyReportCalendar::today();

        $report = DailyReport::with(['employee', 'score'])
            ->forEmployee($request->user()->employee_id)
            ->onDate($today)
            ->first();

        return Inertia::render('DailyReport/Today', [
            'report' => $report ? (new DailyReportResource($report))->resolve() : null,
            'today' => $today,
            'projectOptions' => Project::active()
                ->orderBy('sort_order')
                ->with(['tasks' => fn ($q) => $q->orderBy('order_column')])
                ->get(['id', 'name', 'code', 'color'])
                ->map(fn (Project $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'code' => $p->code,
                    'color' => $p->color,
                    'tasks' => $p->tasks
                        ->map(fn ($t) => [
                            'id' => $t->id,
                            'title' => $t->title,
                            'status' => $t->status->value,
                        ])
                        ->values(),
                ]),
        ]);
    }

    public function store(StoreDailyReportRequest $request, CreateDailyReportUseCase $useCase): RedirectResponse
    {
        try {
            $report = $useCase->execute(
                $request->user()->employee_id,
                $request->validated(),
                $request->user(),
            );
        } catch (DailyReportException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('daily-reports.show', $report)
            ->with('success', 'Đã lưu bản nháp.');
    }

    public function show(DailyReport $report): Response
    {
        $this->authorize('view', $report);

        return Inertia::render('DailyReport/Show', [
            'report' => (new DailyReportResource($report->load(['employee', 'score.reviewer'])))->resolve(),
        ]);
    }

    public function update(UpdateDailyReportRequest $request, DailyReport $report, UpdateDailyReportUseCase $useCase): RedirectResponse
    {
        try {
            $useCase->execute($report, $request->validated(), $request->user());
        } catch (DailyReportException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã lưu báo cáo.');
    }

    public function destroy(DailyReport $report, DeleteDailyReportUseCase $useCase): RedirectResponse
    {
        $this->authorize('delete', $report);

        try {
            $useCase->execute($report);
        } catch (DailyReportException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('daily-reports.index')
            ->with('success', 'Đã xoá báo cáo.');
    }

    public function submit(DailyReport $report, SubmitDailyReportUseCase $useCase): RedirectResponse
    {
        $this->authorize('submit', $report);

        $required = [
            'goals_today' => 'Mục tiêu hôm nay',
            'progress_update' => 'Tiến độ thực hiện',
            'plan_tomorrow' => 'Kế hoạch ngày mai',
        ];
        $missingLabels = collect($required)
            ->filter(fn (string $label, string $field) => ! DailyReportFieldContent::hasMeaningfulText($report->{$field}))
            ->values()
            ->all();

        if ($missingLabels !== []) {
            throw ValidationException::withMessages([
                'submit' => 'Hãy điền đủ: '.implode(', ', $missingLabels).'. Lưu bản nháp tại «Báo cáo hôm nay» rồi nộp lại.',
            ]);
        }

        try {
            $useCase->execute($report);
        } catch (DailyReportException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('daily-reports.show', $report)
            ->with('success', 'Đã nộp báo cáo chờ duyệt.');
    }
}
