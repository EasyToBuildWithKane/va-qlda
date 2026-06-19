<?php

namespace App\Http\Controllers\DailyReport;

use App\Application\DailyReport\RejectReportUseCase;
use App\Application\DailyReport\ScoreReportUseCase;
use App\Domain\DailyReport\Exceptions\DailyReportException;
use App\Domain\DailyReport\Models\DailyReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\DailyReport\RejectDailyReportRequest;
use App\Http\Requests\DailyReport\ScoreDailyReportRequest;
use App\Http\Resources\DailyReportResource;
use App\Support\DailyReportPendingMemberQueue;
use App\Support\NotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DailyReportReviewController extends Controller
{
    /**
     * Review queue: reports awaiting scoring (reviewers only).
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DailyReport::class);

        if (! $request->user()->allows('daily_report.review')) {
            abort(403);
        }

        $queue = DailyReportPendingMemberQueue::build();

        $employeeId = $request->integer('employee_id') ?: null;
        if ($employeeId !== null) {
            $allowedIds = collect($queue['members'])->pluck('employee_id');
            if (! $allowedIds->contains($employeeId)) {
                $employeeId = null;
            }
        }

        $reportsQuery = DailyReport::query()
            ->with(['employee', 'score'])
            ->pendingReview()
            ->latest('submitted_at');

        if ($employeeId !== null) {
            $reportsQuery->forEmployee($employeeId);
        }

        $reports = $reportsQuery->paginate(15)->withQueryString();

        return Inertia::render('DailyReport/Review', [
            'reports' => DailyReportResource::collection($reports),
            'pendingMembers' => $queue['members'],
            'queueTotals' => $queue['totals'],
            'filters' => [
                'employee_id' => $employeeId,
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
}
