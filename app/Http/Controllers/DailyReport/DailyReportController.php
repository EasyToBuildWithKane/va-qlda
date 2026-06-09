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
use App\Support\DailyReportCalendar;
use App\Support\Enums\Grade;
use App\Support\Enums\ReportStatus;
use App\Support\Enums\SystemRole;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $query = DailyReport::query()
            ->with(['employee', 'score'])
            ->latest('date');

        // Members only ever see their own reports; managers can filter by person.
        if ($isMember) {
            $query->where('employee_id', $account->employee_id);
        } elseif ($employeeId = $request->query('employee_id')) {
            $query->where('employee_id', $employeeId);
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
        if ($keyword = trim((string) $request->query('q'))) {
            $query->where('title', 'like', '%'.$keyword.'%');
        }
        if ($from = $request->query('from')) {
            $query->whereDate('date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('date', '<=', $to);
        }

        $perPage = (int) $request->query('per_page', 10);
        if (! in_array($perPage, [5, 10, 15, 20], true)) {
            $perPage = 10;
        }

        $reports = $query->paginate($perPage)->withQueryString();

        return Inertia::render('DailyReport/History', [
            'reports' => DailyReportResource::collection($reports),
            'filters' => (object) $request->only([
                'status', 'project_id', 'employee_id', 'grade', 'q', 'from', 'to', 'per_page',
            ]),
            'statuses' => collect(ReportStatus::cases())
                ->map(fn (ReportStatus $s) => ['value' => $s->value, 'label' => $s->label()]),
            'grades' => collect(Grade::cases())
                ->map(fn (Grade $g) => ['value' => $g->value, 'label' => $g->label()]),
            'projects' => Options::projects(),
            'employees' => $isMember ? [] : Options::employees(),
            'canFilterEmployee' => ! $isMember,
        ]);
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
        $report = $useCase->execute($request->user()->employee_id, $request->validated());

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
        $useCase->execute($report, $request->validated());

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

        $required = ['goals_today', 'progress_update', 'plan_tomorrow'];
        $missing = collect($required)->filter(fn (string $field) => blank($report->{$field}));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'submit' => 'Hãy điền đủ mục tiêu, tiến độ và kế hoạch ngày mai trước khi nộp.',
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
