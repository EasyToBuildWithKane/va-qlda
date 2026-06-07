<?php

namespace App\Http\Controllers\AiAccount;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiAccount\AiAnalyticsReportRequest;
use App\Models\AiAccount;
use App\Models\AiPurchaseProposal;
use App\Services\AiAccount\AiExecutiveAnalyticsBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAnalyticsController extends Controller
{
    public function __construct(
        private readonly AiExecutiveAnalyticsBuilder $analytics,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AiAccount::class);

        $data = $this->analytics->buildDashboard([
            'granularity' => $request->query('granularity', 'month'),
            'compare_previous_year' => $request->boolean('compare_previous_year', true),
            'inactive_days' => $request->integer('inactive_days', 30),
        ]);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function report(AiAnalyticsReportRequest $request): JsonResponse
    {
        $payload = $this->analytics->buildReport($request->filters());

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    public function filterOptions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AiAccount::class);

        $tools = AiAccount::query()
            ->distinct()
            ->orderBy('tool_name')
            ->pluck('tool_name')
            ->merge(
                AiPurchaseProposal::query()->distinct()->orderBy('tool_name')->pluck('tool_name'),
            )
            ->unique()
            ->filter()
            ->values()
            ->all();

        $vendors = AiPurchaseProposal::query()
            ->whereNotNull('vendor_name')
            ->distinct()
            ->orderBy('vendor_name')
            ->pluck('vendor_name')
            ->values()
            ->all();

        $departments = AiPurchaseProposal::query()
            ->select(['proposer_department', 'department_using'])
            ->get()
            ->flatMap(fn (AiPurchaseProposal $p) => [$p->proposer_department, $p->department_using])
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $proposers = AiPurchaseProposal::query()
            ->whereNotNull('proposer_name')
            ->distinct()
            ->orderBy('proposer_name')
            ->pluck('proposer_name')
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'tools' => $tools,
                'vendors' => $vendors,
                'departments' => $departments,
                'proposers' => $proposers,
            ],
        ]);
    }
}
