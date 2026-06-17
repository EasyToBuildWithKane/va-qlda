<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Support\ContractLifecycle\ContractMetricsEngine;
use Inertia\Inertia;
use Inertia\Response;

class ContractReportController extends Controller
{
    public function __invoke(ContractMetricsEngine $engine): Response
    {
        $this->authorize('viewAny', Contract::class);

        $report = $engine->buildReport();
        $byVendor = collect($report['byVendor'] ?? []);

        return Inertia::render('Contract/Reports', [
            'report' => $report,
            'summary' => [
                'contracts' => (int) $byVendor->sum('count'),
                'vendors' => $byVendor->count(),
                'annual_cost' => (float) $byVendor->sum(fn (array $row) => (float) ($row['annual_cost'] ?? 0)),
                'lifecycle_cost' => (float) $byVendor->sum(fn (array $row) => (float) ($row['lifecycle_cost'] ?? 0)),
            ],
        ]);
    }
}
