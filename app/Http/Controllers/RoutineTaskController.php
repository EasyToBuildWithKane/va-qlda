<?php

namespace App\Http\Controllers;

use App\Application\RoutineTask\CreateRoutineTaskUseCase;
use App\Application\RoutineTask\DeleteRoutineTaskUseCase;
use App\Application\RoutineTask\ReorderRoutineTasksUseCase;
use App\Application\RoutineTask\StoreRoutineTaskAttachmentsUseCase;
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
use App\Support\PublicMediaUrl;
use App\Support\Team\LedTeamScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
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
        $directReports = $selfId > 0 ? LedTeamScope::members($selfId) : collect();
        $directReportIds = $directReports->pluck('id')->map(fn ($id) => (int) $id);

        $requestedEmployee = (int) $request->integer('employee');
        $targetEmployeeId = $selfId;

        if ($requestedEmployee > 0 && $requestedEmployee !== $selfId) {
            $allowed = $canViewOthers || $directReportIds->contains($requestedEmployee);
            abort_unless($allowed, 403, 'Bạn không có quyền xem việc thường xuyên của nhân sự này.');
            $targetEmployeeId = $requestedEmployee;
        }

        abort_unless($targetEmployeeId > 0, 403, 'Tài khoản chưa gắn hồ sơ nhân sự.');

        $statusFilter = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));

        $query = RoutineTask::query()
            ->with('attachments')
            ->withCount('attachments')
            ->forEmployee($targetEmployeeId)
            ->byPosition();

        if ($statusFilter !== '' && in_array($statusFilter, RoutineTask::allowedStatusValues(), true)) {
            $query->where('status', $statusFilter);
        }

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('title', 'like', '%'.$q.'%')
                    ->orWhere('description', 'like', '%'.$q.'%')
                    ->orWhere('blockers', 'like', '%'.$q.'%')
                    ->orWhere('risks', 'like', '%'.$q.'%');
            });
        }

        if ($from !== '' && $this->validDate($from)) {
            $query->whereDate('work_date', '>=', $from);
        }
        if ($to !== '' && $this->validDate($to)) {
            $query->whereDate('work_date', '<=', $to);
        }

        $tasks = $query->get();

        $base = RoutineTask::query()->forEmployee($targetEmployeeId);
        $total = (clone $base)->count();
        $todo = (clone $base)->where('status', TaskStatus::Todo->value)->count();
        $inProgress = (clone $base)->where('status', TaskStatus::InProgress->value)->count();
        $done = (clone $base)->where('status', TaskStatus::Done->value)->count();
        $estimateHours = (float) (clone $base)->sum('estimate_hours');
        $actualHours = (float) (clone $base)->sum('actual_hours');
        $hoursToday = (float) (clone $base)->whereDate('work_date', Carbon::today()->toDateString())->sum('actual_hours');

        $employee = Employee::query()->find($targetEmployeeId);
        $canPickPeople = $canViewOthers || $directReports->isNotEmpty();

        return Inertia::render('RoutineTask/Index', [
            'tasks' => RoutineTaskResource::collection($tasks)->resolve(),
            'summary' => [
                'total' => $total,
                'todo' => $todo,
                'in_progress' => $inProgress,
                'done' => $done,
                'progress_pct' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
                'estimate_hours' => round($estimateHours, 1),
                'actual_hours' => round($actualHours, 1),
                'hours_today' => round($hoursToday, 1),
            ],
            'filters' => [
                'q' => $q,
                'status' => $statusFilter,
                'employee' => $targetEmployeeId !== $selfId ? $targetEmployeeId : null,
                'from' => $from !== '' ? $from : null,
                'to' => $to !== '' ? $to : null,
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
                'employees' => $canViewOthers
                    ? Options::employees()
                    : $directReports->map(fn (Employee $e) => $this->personOption($e))->values(),
                'direct_reports' => $directReports->map(fn (Employee $e) => $this->personOption($e))->values(),
            ],
            'viewer' => [
                'employee_id' => $selfId,
                'is_self' => $targetEmployeeId === $selfId,
                'target_name' => $employee?->full_name,
                'can_view_others' => $canPickPeople,
                'can_create' => $targetEmployeeId === $selfId && $account->can('create', RoutineTask::class),
                'self' => $account->employee ? $this->personOption($account->employee) : null,
            ],
        ]);
    }

    public function store(
        StoreRoutineTaskRequest $request,
        CreateRoutineTaskUseCase $create,
        StoreRoutineTaskAttachmentsUseCase $attach,
    ): RedirectResponse {
        $employeeId = (int) $request->user()->employee_id;
        abort_unless($employeeId > 0, 403);

        $data = $request->safe()->except('files');
        $task = $create->execute($employeeId, $data);

        $files = Arr::wrap($request->file('files') ?? []);
        if (is_array($files) && $files !== []) {
            $attach->execute($task, $files, $employeeId);
        }

        return back()->with('success', 'Đã ghi nhận công việc.');
    }

    public function update(
        UpdateRoutineTaskRequest $request,
        RoutineTask $routineTask,
        UpdateRoutineTaskUseCase $update,
        StoreRoutineTaskAttachmentsUseCase $attach,
    ): RedirectResponse {
        $data = $request->safe()->except('files');
        $update->execute($routineTask, $data);

        $files = Arr::wrap($request->file('files') ?? []);
        if (is_array($files) && $files !== []) {
            $attach->execute(
                $routineTask,
                $files,
                $request->user()->employee_id ? (int) $request->user()->employee_id : null,
            );
        }

        return back()->with('success', 'Đã cập nhật công việc.');
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

        return back()->with('success', 'Đã xoá công việc.');
    }

    /**
     * @return array{id:int, name:string, avatar_path:string|null, role_title:?string}
     */
    private function personOption(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->full_name,
            'avatar_path' => PublicMediaUrl::fromPublicDisk($employee->avatar_path),
            'role_title' => $employee->role_title,
        ];
    }

    private function validDate(string $value): bool
    {
        try {
            Carbon::parse($value);

            return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
        } catch (\Throwable) {
            return false;
        }
    }
}
