<?php

namespace App\Http\Controllers;

use App\Application\RoutineTask\CreateRoutineTaskUseCase;
use App\Application\RoutineTask\DeleteRoutineTaskUseCase;
use App\Application\RoutineTask\ReorderRoutineTasksUseCase;
use App\Application\RoutineTask\ToggleRoutineTaskStatusUseCase;
use App\Application\RoutineTask\UpdateRoutineTaskUseCase;
use App\Domain\RoutineTask\Models\RoutineTask;
use App\Http\Requests\RoutineTask\ReorderRoutineTasksRequest;
use App\Http\Requests\RoutineTask\StoreRoutineTaskRequest;
use App\Http\Requests\RoutineTask\ToggleRoutineTaskStatusRequest;
use App\Http\Requests\RoutineTask\UpdateRoutineTaskRequest;
use App\Http\Resources\RoutineTaskResource;
use App\Models\Employee;
use App\Support\Enums\TaskStatus;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoutineTaskController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RoutineTask::class);

        $account = $request->user();
        $selfId = (int) ($account->employee_id ?? 0);
        $canViewOthers = $account->allows('routine_task.view') || $account->isAdminTier();

        $requestedEmployee = (int) $request->integer('employee');
        $targetEmployeeId = $selfId;

        if ($requestedEmployee > 0 && $requestedEmployee !== $selfId) {
            abort_unless($canViewOthers, 403, 'Bạn không có quyền xem việc thường xuyên của nhân sự này.');
            $targetEmployeeId = $requestedEmployee;
        }

        abort_unless($targetEmployeeId > 0, 403, 'Tài khoản chưa gắn hồ sơ nhân sự.');

        $statusFilter = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));

        $query = RoutineTask::query()
            ->forEmployee($targetEmployeeId)
            ->byPosition();

        if ($statusFilter !== '' && in_array($statusFilter, RoutineTask::allowedStatusValues(), true)) {
            $query->where('status', $statusFilter);
        }

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('title', 'like', '%'.$q.'%')
                    ->orWhere('description', 'like', '%'.$q.'%');
            });
        }

        $tasks = $query->get();

        $base = RoutineTask::query()->forEmployee($targetEmployeeId);
        $total = (clone $base)->count();
        $todo = (clone $base)->where('status', TaskStatus::Todo->value)->count();
        $inProgress = (clone $base)->where('status', TaskStatus::InProgress->value)->count();
        $done = (clone $base)->where('status', TaskStatus::Done->value)->count();

        $employee = Employee::query()->find($targetEmployeeId);

        return Inertia::render('RoutineTask/Index', [
            'tasks' => RoutineTaskResource::collection($tasks)->resolve(),
            'summary' => [
                'total' => $total,
                'todo' => $todo,
                'in_progress' => $inProgress,
                'done' => $done,
                'progress_pct' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
            ],
            'filters' => [
                'q' => $q,
                'status' => $statusFilter,
                'employee' => $targetEmployeeId !== $selfId ? $targetEmployeeId : null,
            ],
            'options' => [
                'statuses' => collect(RoutineTask::allowedStatusValues())
                    ->map(function (string $value) {
                        $status = TaskStatus::from($value);

                        return [
                            'value' => $status->value,
                            'label' => $status->label(),
                            'color' => $status->color(),
                        ];
                    })
                    ->values(),
                'employees' => $canViewOthers ? Options::employees() : [],
            ],
            'viewer' => [
                'employee_id' => $selfId,
                'is_self' => $targetEmployeeId === $selfId,
                'target_name' => $employee?->full_name,
                'can_view_others' => $canViewOthers,
                'can_create' => $targetEmployeeId === $selfId && $account->can('create', RoutineTask::class),
            ],
        ]);
    }

    public function store(
        StoreRoutineTaskRequest $request,
        CreateRoutineTaskUseCase $create,
    ): RedirectResponse {
        $employeeId = (int) $request->user()->employee_id;
        abort_unless($employeeId > 0, 403);

        $create->execute($employeeId, $request->validated());

        return back()->with('success', 'Đã thêm việc thường xuyên.');
    }

    public function update(
        UpdateRoutineTaskRequest $request,
        RoutineTask $routineTask,
        UpdateRoutineTaskUseCase $update,
    ): RedirectResponse {
        $update->execute($routineTask, $request->validated());

        return back()->with('success', 'Đã cập nhật việc thường xuyên.');
    }

    public function toggleStatus(
        ToggleRoutineTaskStatusRequest $request,
        RoutineTask $routineTask,
        ToggleRoutineTaskStatusUseCase $toggle,
    ): RedirectResponse {
        $status = $request->validated('status') ?? null;
        $explicit = $status !== null ? TaskStatus::tryFrom($status) : null;

        $toggle->execute($routineTask, $explicit);

        return back()->with('success', 'Đã cập nhật trạng thái.');
    }

    public function reorder(
        ReorderRoutineTasksRequest $request,
        ReorderRoutineTasksUseCase $reorder,
    ): RedirectResponse {
        $employeeId = (int) $request->user()->employee_id;
        abort_unless($employeeId > 0, 403);

        $reorder->execute($employeeId, $request->validated('ids'));

        return back()->with('success', 'Đã sắp xếp lại danh sách.');
    }

    public function destroy(
        RoutineTask $routineTask,
        DeleteRoutineTaskUseCase $delete,
    ): RedirectResponse {
        $this->authorize('delete', $routineTask);
        $delete->execute($routineTask);

        return back()->with('success', 'Đã xoá việc thường xuyên.');
    }
}
