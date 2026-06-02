<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Placeholder dashboard for Stage 0. Real KPI cards/charts land in the
     * dashboard stage; for now it confirms the authenticated app shell works.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Dashboard/Index', [
            'stats' => [
                ['label' => 'Projects', 'value' => 0],
                ['label' => 'Members', 'value' => 0],
                ['label' => 'Avg Team Score', 'value' => '—'],
                ['label' => 'Open Blockers', 'value' => 0],
            ],
        ]);
    }
}
