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

        $query = Department::query()
            ->with('manager')
            ->withCount('projects')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhereHas('manager', fn ($m) => $m->where('name', 'like', "%{$search}%"));
            });
        }

        $status = $request->query('status');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($managerId = $request->query('manager_id')) {
            $query->where('manager_id', (int) $managerId);
        }

        if ($color = $request->query('color')) {
            $query->where('color', $color);
        }

        $hasProjects = $request->query('has_projects');
        if ($hasProjects === 'yes') {
            $query->has('projects');
        } elseif ($hasProjects === 'no') {
            $query->doesntHave('projects');
        }

        $perPage = $request->integer('per_page', 10);
        if (! in_array($perPage, [5, 10, 15, 20], true)) {
            $perPage = 10;
        }

        return Inertia::render('Department/Index', [
            'departments' => DepartmentResource::collection(
                $query->paginate($perPage)->withQueryString(),
            ),
            'filters' => (object) $request->only([
                'q', 'status', 'manager_id', 'color', 'has_projects', 'per_page',
            ]),
            'summary' => [
                'total' => Department::count(),
                'active' => Department::where('is_active', true)->count(),
                'inactive' => Department::where('is_active', false)->count(),
            ],
            'colorOptions' => self::colorFilterOptions(),
            'existingDepartments' => Department::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'employees' => Options::employees(),
            'can' => ['create' => $request->user()->can('create', Department::class)],
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function colorFilterOptions(): array
    {
        return [
            ['value' => 'brand', 'label' => 'Xanh thương hiệu'],
            ['value' => 'sky', 'label' => 'Xanh trời'],
            ['value' => 'emerald', 'label' => 'Xanh lá'],
            ['value' => 'violet', 'label' => 'Tím'],
            ['value' => 'amber', 'label' => 'Vàng'],
            ['value' => 'rose', 'label' => 'Đỏ hồng'],
            ['value' => 'cyan', 'label' => 'Xanh cyan'],
            ['value' => 'slate', 'label' => 'Xám'],
        ];
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

        $department->update(['is_active' => ! $department->is_active]);

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
