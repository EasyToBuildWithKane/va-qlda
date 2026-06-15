<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Nhân sự hiển thị trên /dashboard — mặc định chỉ Phòng Công nghệ.
 */
class DashboardPersonnelScope
{
    private ?int $departmentId = null;

    private bool $departmentResolved = false;

    public function department(): ?Department
    {
        $id = $this->departmentId();

        return $id ? Department::query()->find($id) : null;
    }

    public function departmentId(): ?int
    {
        if ($this->departmentResolved) {
            return $this->departmentId;
        }

        $this->departmentResolved = true;
        $pattern = (string) config('va.dashboard_personnel_department_pattern', 'Công nghệ');
        $pattern = trim($pattern);

        if ($pattern === '') {
            return null;
        }

        $this->departmentId = Department::query()
            ->active()
            ->where('name', 'like', '%'.$pattern.'%')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->value('id');

        return $this->departmentId;
    }

    /**
     * @return Collection<int, int>
     */
    public function employeeIds(): Collection
    {
        return $this->employeeQuery()->pluck('id');
    }

    /**
     * @return Builder<Employee>
     */
    public function employeeQuery(): Builder
    {
        $deptId = $this->departmentId();
        if ($deptId === null) {
            return Employee::query()->whereRaw('0 = 1');
        }

        $pivotMemberIds = Department::query()
            ->whereKey($deptId)
            ->first()
            ?->members()
            ->pluck('employees.id') ?? collect();

        if ($pivotMemberIds->isNotEmpty()) {
            return Employee::query()
                ->where('is_active', true)
                ->whereIn('id', $pivotMemberIds)
                ->orderBy('full_name');
        }

        return Employee::query()
            ->where('is_active', true)
            ->where(function (Builder $q) use ($deptId) {
                $q->whereHas('projects', fn (Builder $p) => $p->where('department_id', $deptId))
                    ->orWhereHas('managedProjects', fn (Builder $p) => $p->where('department_id', $deptId));
            })
            ->orderBy('full_name');
    }
}
