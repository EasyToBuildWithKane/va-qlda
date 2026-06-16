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

        return Inertia::render('Contract/Dashboard', [
            'metrics' => $engine->build(),
            'can' => [
                'manage' => $request->user()->can('create', Contract::class),
            ],
        ]);
    }
}
