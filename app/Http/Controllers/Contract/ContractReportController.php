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

        return Inertia::render('Contract/Reports', [
            'report' => $engine->buildReport(),
        ]);
    }
}
