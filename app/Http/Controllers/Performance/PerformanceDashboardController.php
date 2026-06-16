<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Support\DashboardPersonnelScope;
use App\Support\Performance\PerformanceFilter;
use App\Support\Performance\PerformanceInsights;
use App\Support\Performance\PerformanceMetrics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Executive Dashboard — tổng quan hiệu suất toàn hệ thống cho ban quản lý.
 */
class PerformanceDashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardPersonnelScope $personnelScope,
        PerformanceMetrics $metrics,
        PerformanceInsights $insights,
    ): Response {
        $this->authorize('performance.view');

        $filter = PerformanceFilter::fromRequest($request, $personnelScope);
        $payload = $metrics->build($filter);
        $payload['insights'] = $insights->generate($payload);

        return Inertia::render('Performance/Dashboard', $payload);
    }
}
