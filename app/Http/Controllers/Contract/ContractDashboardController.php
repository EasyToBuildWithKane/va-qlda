<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Support\ContractLifecycle\ContractMetricsEngine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContractDashboardController extends Controller
{
    public function __invoke(Request $request, ContractMetricsEngine $engine): Response
    {
        $this->authorize('viewAny', Contract::class);

        $period = $request->query('period', 'year');
        if (! in_array($period, ['month', 'quarter', 'year'], true)) {
            $period = 'year';
        }

        return Inertia::render('Contract/Dashboard', [
            'metrics' => $engine->build($period),
            'filters' => ['period' => $period],
            'can' => [
                'manage' => $request->user()->can('create', Contract::class),
            ],
        ]);
    }
}
