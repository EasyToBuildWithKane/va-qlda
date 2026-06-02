<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Department::class);

        $departments = Department::query()
            ->with('manager')
            ->withCount('projects')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Department/Index', [
            'departments' => DepartmentResource::collection($departments),
            'employees' => Options::employees(),
            'can' => ['create' => $request->user()->can('create', Department::class)],
        ]);
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        Department::create($request->validated());

        return back()->with('success', 'Đã thêm phòng ban.');
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        return back()->with('success', 'Đã cập nhật phòng ban.');
    }

    public function toggleStatus(Department $department): RedirectResponse
    {
        $this->authorize('update', $department);

        $department->update(['is_active' => !$department->is_active]);

        $msg = $department->is_active ? 'Đã kích hoạt phòng ban.' : 'Đã ngừng hoạt động phòng ban.';

        return back()->with('success', $msg);
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->authorize('delete', $department);

        // Projects keep existing; their department_id is nulled by the FK.
        $department->delete();

        return back()->with('success', 'Đã xoá phòng ban.');
    }
}
