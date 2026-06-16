<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\DashboardPersonnelScope;
use App\Support\Performance\EmployeeAuditBuilder;
use App\Support\Performance\PerformanceFilter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Audit theo nhân viên — Timeline kế hoạch vs kết quả từng tuần cho 1 thành viên.
 */
class PerformanceAuditController extends Controller
{
    public function index(
        Request $request,
        DashboardPersonnelScope $personnelScope,
        EmployeeAuditBuilder $builder,
    ): Response {
        $this->authorize('performance.view');

        $filter = PerformanceFilter::fromRequest($request, $personnelScope);

        // Chọn thành viên: ưu tiên filter, nếu trống thì lấy người đầu trong phạm vi.
        $memberId = $filter->memberId ?? $personnelScope->employeeIds()->first();
        $member = $memberId ? Employee::query()->find($memberId) : null;

        return Inertia::render('Performance/Audit', [
            'filter' => $filter->toClientArray(),
            'options' => $filter->options(),
            'audit' => $member ? $builder->build($member, $filter) : null,
        ]);
    }
}
