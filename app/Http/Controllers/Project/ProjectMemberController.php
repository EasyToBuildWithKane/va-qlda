<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreMemberRequest;
use App\Http\Requests\Project\UpdateMemberRequest;
use App\Models\Employee;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;

class ProjectMemberController extends Controller
{
    public function store(StoreMemberRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();

        $project->members()->attach($data['employee_id'], [
            'role' => $data['role'],
            'rate_type' => $data['rate_type'],
            'rate' => $data['rate'] ?? null,
            'allocation' => $data['allocation'] ?? null,
            'joined_at' => $data['joined_at'] ?? now()->toDateString(),
            'is_active' => $data['is_active'] ?? true,
        ]);

        return back()->with('success', 'Đã thêm thành viên.');
    }

    public function update(UpdateMemberRequest $request, Project $project, Employee $employee): RedirectResponse
    {
        $data = $request->validated();

        $project->members()->updateExistingPivot($employee->id, [
            'role' => $data['role'],
            'rate_type' => $data['rate_type'],
            'rate' => $data['rate'] ?? null,
            'allocation' => $data['allocation'] ?? null,
            'joined_at' => $data['joined_at'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return back()->with('success', 'Đã cập nhật thành viên.');
    }

    public function destroy(Project $project, Employee $employee): RedirectResponse
    {
        $this->authorize('manage', $project);

        $project->members()->detach($employee->id);

        return back()->with('success', 'Đã gỡ thành viên khỏi dự án.');
    }
}
