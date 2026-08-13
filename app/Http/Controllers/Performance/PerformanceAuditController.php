<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\DashboardPersonnelScope;
use App\Support\Performance\EmployeeAuditBuilder;
use App\Support\Performance\EmployeeAuditListBuilder;
use App\Support\Performance\PerformanceFilter;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Audit nhân sự — danh sách theo kỳ/team và chi tiết timeline từng thành viên.
 */
class PerformanceAuditController extends Controller
{
    public function index(
        Request $request,
        DashboardPersonnelScope $personnelScope,
        EmployeeAuditListBuilder $listBuilder,
    ): Response {
        $this->authorize('performance.view');

        $filter = PerformanceFilter::fromRequest($request, $personnelScope);
        $built = $listBuilder->build($filter);

        $search = mb_strtolower(trim((string) $request->query('q', '')));
        $kpi = (string) $request->query('kpi', '');

        $rows = collect($built['employees']);

        if ($search !== '') {
            $rows = $rows->filter(function (array $row) use ($search) {
                $hay = mb_strtolower($row['name'].' '.($row['role'] ?? '').' '.($row['unitName'] ?? ''));

                return str_contains($hay, $search);
            });
        }

        $rows = $this->applyKpiFilter($rows, $kpi)->values();

        $perPage = min(50, max(10, (int) $request->query('per_page', 20)));
        $page = max(1, (int) $request->query('page', 1));
        $total = $rows->count();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        $employees = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Performance/Audit', [
            'filter' => $filter->toClientArray(),
            'options' => $filter->options(),
            'summary' => $built['summary'],
            'employees' => $employees,
            'filters' => [
                'q' => $request->query('q', ''),
                'kpi' => $kpi,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(
        Request $request,
        Employee $employee,
        DashboardPersonnelScope $personnelScope,
        EmployeeAuditBuilder $builder,
    ): Response {
        $this->authorize('performance.view');

        $filter = PerformanceFilter::fromRequest($request, $personnelScope);

        if (! $filter->employeeIds()->contains($employee->id)) {
            abort(403, 'Bạn không có quyền xem audit của nhân sự này.');
        }

        $audit = $builder->build($employee, $filter);

        return Inertia::render('Performance/AuditShow', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'role' => $employee->role_title,
                'avatar' => $audit['member']['avatar'] ?? null,
                'unitName' => $audit['member']['unitName'] ?? null,
            ],
            'filter' => $filter->toClientArray(),
            'options' => $filter->options(),
            'audit' => $audit,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function applyKpiFilter($rows, string $kpi)
    {
        return match ($kpi) {
            'excellent' => $rows->filter(fn (array $r) => in_array($r['grade'] ?? '', ['S', 'A'], true)),
            'needs_improvement' => $rows->filter(fn (array $r) => in_array($r['grade'] ?? '', ['C', 'D'], true)),
            'has_commitment' => $rows->filter(fn (array $r) => ($r['committed'] ?? 0) > 0),
            default => $rows,
        };
    }
}
