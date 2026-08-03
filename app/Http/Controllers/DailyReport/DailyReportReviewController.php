<?php

namespace App\Http\Controllers\DailyReport;

use App\Application\DailyReport\RejectReportUseCase;
use App\Application\DailyReport\ScoreReportUseCase;
use App\Domain\DailyReport\Exceptions\DailyReportException;
use App\Domain\DailyReport\Models\DailyReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\DailyReport\BulkRejectDailyReportRequest;
use App\Http\Requests\DailyReport\BulkScoreDailyReportRequest;
use App\Http\Requests\DailyReport\RejectDailyReportRequest;
use App\Http\Requests\DailyReport\ScoreDailyReportRequest;
use App\Http\Resources\DailyReportResource;
use App\Support\DailyReport\DailyReportScoringResolver;
use App\Support\DailyReportCalendar;
use App\Support\DailyReportPendingMemberQueue;
use App\Support\NotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DailyReportReviewController extends Controller
{
    public function __construct(
        private readonly DailyReportScoringResolver $rubricResolver,
    ) {}

    /**
     * Review queue: reports awaiting scoring (reviewers only).
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DailyReport::class);

        if (! $request->user()->allows('daily_report.review')) {
            abort(403);
        }

        $today = DailyReportCalendar::today();
        $queue = DailyReportPendingMemberQueue::build($today);

        $employeeId = $request->integer('employee_id') ?: null;
        if ($employeeId !== null) {
            $allowedIds = collect($queue['members'])->pluck('employee_id');
            if (! $allowedIds->contains($employeeId)) {
                $employeeId = null;
            }
        }

        $queueFilter = trim((string) $request->query('queue', 'all'));
        if (! in_array($queueFilter, ['all', 'today', 'late'], true)) {
            $queueFilter = 'all';
        }

        $search = trim((string) $request->query('q', ''));

        $reportsQuery = DailyReport::query()
            ->with(['employee', 'score'])
            ->pendingReview()
            ->latest('submitted_at');

        if ($employeeId !== null) {
            $reportsQuery->forEmployee($employeeId);
        }

        if ($queueFilter === 'today') {
            $reportsQuery->whereDate('date', $today);
        } elseif ($queueFilter === 'late') {
            $reportsQuery->where('is_late', true);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $reportsQuery->where(function ($builder) use ($like) {
                $builder->where('title', 'like', $like)
                    ->orWhereHas('employee', function ($emp) use ($like) {
                        $emp->where('full_name', 'like', $like)
                            ->orWhere('role_title', 'like', $like);
                    });
            });
        }

        $perPage = (int) $request->query('per_page', 15);
        if (! in_array($perPage, [15, 30, 50], true)) {
            $perPage = 15;
        }

        $reports = $reportsQuery->paginate($perPage)->withQueryString();

        $scoringRubricsByEmployee = [];
        foreach ($reports->getCollection() as $report) {
            $empId = (int) $report->employee_id;
            if (isset($scoringRubricsByEmployee[$empId])) {
                continue;
            }
            $scoringRubricsByEmployee[$empId] = $this->rubricResolver->forEmployee($report->employee);
        }

        return Inertia::render('DailyReport/Review', [
            'reports' => DailyReportResource::collection($reports),
            'pendingMembers' => $queue['members'],
            'queueTotals' => $queue['totals'],
            'scoringRubricsByEmployee' => $scoringRubricsByEmployee,
            'today' => $today,
            'filters' => [
                'employee_id' => $employeeId,
                'queue' => $queueFilter,
                'q' => $search,
            ],
        ]);
    }

    public function score(ScoreDailyReportRequest $request, DailyReport $report, ScoreReportUseCase $useCase): RedirectResponse
    {
        try {
            $score = $useCase->execute(
                $report,
                $request->user()->employee_id,
                $request->validated(),
                $request->input('notes'),
            );
            NotificationDispatcher::dailyReportScored($report->fresh(['employee']), $score, $request->user());
        } catch (DailyReportException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã chấm điểm báo cáo.');
    }

    public function reject(RejectDailyReportRequest $request, DailyReport $report, RejectReportUseCase $useCase): RedirectResponse
    {
        try {
            $useCase->execute(
                $report,
                $request->user()->employee_id,
                $request->validated()['notes'],
            );
            NotificationDispatcher::dailyReportRejected($report->fresh(['employee']), $request->user());
        } catch (DailyReportException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã trả báo cáo cho người viết chỉnh sửa.');
    }

    public function bulkScore(BulkScoreDailyReportRequest $request, ScoreReportUseCase $useCase): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $scores = [
            'task_completion' => $data['task_completion'],
            'skill_score' => $data['skill_score'],
            'attitude_score' => $data['attitude_score'],
            'kaizen_score' => $data['kaizen_score'],
            'expertise_score' => $data['expertise_score'],
        ];
        $notes = $data['notes'] ?? null;

        $ok = 0;
        $failed = 0;

        DB::transaction(function () use ($data, $user, $useCase, $scores, $notes, &$ok, &$failed) {
            $reports = DailyReport::query()
                ->with('employee')
                ->whereIn('id', $data['ids'])
                ->pendingReview()
                ->get();

            foreach ($reports as $report) {
                if (! $user->can('score', $report)) {
                    $failed++;

                    continue;
                }

                try {
                    $score = $useCase->execute(
                        $report,
                        (int) $user->employee_id,
                        $scores,
                        $notes,
                    );
                    NotificationDispatcher::dailyReportScored($report->fresh(['employee']), $score, $user);
                    $ok++;
                } catch (DailyReportException) {
                    $failed++;
                }
            }
        });

        if ($ok === 0) {
            return back()->with('error', 'Không duyệt được báo cáo nào. Kiểm tra quyền hoặc trạng thái.');
        }

        $message = "Đã duyệt {$ok} báo cáo.";
        if ($failed > 0) {
            $message .= " {$failed} báo cáo bỏ qua.";
        }

        return back()->with('success', $message);
    }

    public function bulkReject(BulkRejectDailyReportRequest $request, RejectReportUseCase $useCase): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $notes = $data['notes'];

        $ok = 0;
        $failed = 0;

        DB::transaction(function () use ($data, $user, $useCase, $notes, &$ok, &$failed) {
            $reports = DailyReport::query()
                ->whereIn('id', $data['ids'])
                ->pendingReview()
                ->get();

            foreach ($reports as $report) {
                if (! $user->can('reject', $report)) {
                    $failed++;

                    continue;
                }

                try {
                    $useCase->execute($report, (int) $user->employee_id, $notes);
                    NotificationDispatcher::dailyReportRejected($report->fresh(['employee']), $user);
                    $ok++;
                } catch (DailyReportException) {
                    $failed++;
                }
            }
        });

        if ($ok === 0) {
            return back()->with('error', 'Không trả lại được báo cáo nào. Kiểm tra quyền hoặc trạng thái.');
        }

        $message = "Đã trả lại {$ok} báo cáo.";
        if ($failed > 0) {
            $message .= " {$failed} báo cáo bỏ qua.";
        }

        return back()->with('success', $message);
    }
}
