<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Models\Department;
use App\Support\Options\DepartmentOptions;
use App\Support\SecurityAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Mutate-only department API (used by DepartmentFormModal).
 * Admin directory UI removed — org data will come from HRM.
 */
class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentOptions $departmentOptions,
    ) {}

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $memberIds = $this->resolveMemberIds($validated);

        $department = null;
        DB::transaction(function () use ($validated, $memberIds, &$department) {
            $department = Department::create(collect($validated)->except('member_ids')->all());
            $this->syncMembers($department, $memberIds);
        });

        $this->departmentOptions->flush();

        if ($department !== null) {
            SecurityAuditLogger::department($request->user(), 'created', $department->id, ['name' => $department->name]);
        }

        return back()->with('success', 'Đã thêm phòng ban.');
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $validated = $request->validated();
        $memberIds = $this->resolveMemberIds($validated);

        DB::transaction(function () use ($department, $validated, $memberIds) {
            $department->update(collect($validated)->except('member_ids')->all());
            $this->syncMembers($department, $memberIds);
        });

        $this->departmentOptions->flush();

        SecurityAuditLogger::department($request->user(), 'updated', $department->id, ['name' => $department->name]);

        return back()->with('success', 'Đã cập nhật phòng ban.');
    }

    public function toggleStatus(Department $department): RedirectResponse
    {
        $this->authorize('update', $department);

        $department->update(['is_active' => ! $department->is_active]);
        $this->departmentOptions->flush();

        $msg = $department->is_active ? 'Đã kích hoạt phòng ban.' : 'Đã ngừng hoạt động phòng ban.';

        return back()->with('success', $msg);
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->authorize('delete', $department);

        // Projects keep existing; their department_id is nulled by the FK.
        SecurityAuditLogger::department(request()->user(), 'deleted', $department->id, ['name' => $department->name]);
        $department->delete();
        $this->departmentOptions->flush();

        return back()->with('success', 'Đã xoá phòng ban.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<int>
     */
    private function resolveMemberIds(array $validated): array
    {
        $ids = array_map('intval', $validated['member_ids'] ?? []);
        if (! empty($validated['manager_id'])) {
            $ids[] = (int) $validated['manager_id'];
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param  list<int>  $memberIds
     */
    private function syncMembers(Department $department, array $memberIds): void
    {
        $existingJoined = $department->members()
            ->pluck('department_member.joined_at', 'employees.id');

        $today = now()->toDateString();
        $sync = [];
        foreach ($memberIds as $id) {
            $sync[$id] = [
                'joined_at' => $existingJoined[$id] ?? $today,
                'is_active' => true,
            ];
        }
        $department->members()->sync($sync);
    }
}
