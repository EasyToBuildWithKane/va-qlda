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
use App\Support\Enums\SystemRole;
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

        if (! in_array($request->user()->role, [SystemRole::Admin, SystemRole::Lead], true)) {
            abort(403);
        }

        $reports = DailyReport::query()
            ->with(['employee', 'score'])
            ->pendingReview()
            ->latest('submitted_at')
            ->paginate(15);

        return Inertia::render('DailyReport/Review', [
            'reports' => DailyReportResource::collection($reports),
        ]);
    }

    public function score(ScoreDailyReportRequest $request, DailyReport $report, ScoreReportUseCase $useCase): RedirectResponse
    {
        try {
            $useCase->execute(
                $report,
                $request->user()->employee_id,
                $request->validated(),
                $request->input('notes'),
            );
        } catch (DailyReportException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Report scored.');
    }

    public function reject(RejectDailyReportRequest $request, DailyReport $report, RejectReportUseCase $useCase): RedirectResponse
    {
        try {
            $useCase->execute($report, $request->validated()['notes']);
        } catch (DailyReportException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Report returned to the author.');
    }
}
