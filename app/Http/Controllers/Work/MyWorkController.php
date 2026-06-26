<?php

namespace App\Http\Controllers\Work;

use App\Application\Work\MyWorkQuery;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OrgTeamMember;
use App\Models\Task;
use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskStatus;
use App\Support\Options;
use App\Support\Performance\EmployeeOrgUnitResolver;
use App\Support\PublicMediaUrl;
use App\Support\Team\LedTeamScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Màn hình "Việc của tôi" (/my-work) — tập trung toàn bộ công việc cá nhân đa
 * dự án, ưu tiên hôm nay. Lead (có my_work.view_team) còn xem được việc của
 * thành viên nhóm mình phụ trách.
 *
 * Authorization 2 lớp:
 *   - Cổng quyền: ma trận RBAC (my_work.view_team / my_work.act_team).
 *   - Phạm vi  : LedTeamScope (chỉ nhân sự dưới quyền) — mirror PerformanceAuditController.
 */
class MyWorkController extends Controller
{
    public function __construct(private readonly MyWorkQuery $query) {}

    public function __invoke(Request $request): Response
    {
        $viewer = $request->user();
        $selfId = (int) ($viewer->employee_id ?? 0);

        $canTeamView = $viewer->allows('my_work.view_team');
        $ledMemberIds = $canTeamView && $selfId > 0
            ? LedTeamScope::memberIds($selfId)
            : collect();

        // ── Resolve chế độ + người được xem ─────────────────────────────────
        $requested = (int) $request->integer('member');
        $mode = 'self';
        $target = $selfId;

        if ($requested > 0 && $requested !== $selfId) {
            // Xem việc của một nhân sự khác — bắt buộc kiểm tra phạm vi.
            $allowed = $viewer->isAdminTier()
                || ($canTeamView && $ledMemberIds->contains($requested));
            abort_unless($allowed, 403, 'Bạn không có quyền xem việc của nhân sự này.');

            $mode = 'member';
            $target = $requested;
        } elseif ($request->query('scope') === 'team' && $canTeamView) {
            $mode = 'team';
        }

        $isSelf = $mode === 'self';
        $canActTeam = ! $isSelf
            && ($viewer->isAdminTier()
                || ($viewer->allows('my_work.act_team') && $ledMemberIds->contains($target)));

        $scopeTeamIds = ($canTeamView && $selfId > 0)
            ? LedTeamScope::scopedTeamIds($selfId)
            : collect();

        // ── Dữ liệu theo chế độ ──────────────────────────────────────────────
        $teamSummary = null;
        $teamScope = null;
        $teamDepartmentLanes = [];
        $members = $canTeamView
            ? $this->memberRoster($ledMemberIds, $scopeTeamIds)
            : [];

        if ($mode === 'team') {
            $summary = null;
            $buckets = null;
            $viewing = null;
            $dailyReportToday = null;
            $teamSummary = $this->teamSummary($members);
            $teamScope = $selfId > 0 ? $this->teamScopePayload($selfId, $members) : null;
            $teamDepartmentLanes = $this->teamDepartmentLanes($ledMemberIds);
        } elseif ($target > 0) {
            $data = $this->query->execute($viewer, $target, $this->filters($request), $canActTeam);
            $summary = $data['summary'];
            $buckets = $data['buckets'];
            $dailyReportToday = $data['dailyReportToday'];
            $viewing = $this->employeeCard($isSelf ? $viewer->employee : Employee::find($target), $isSelf);
        } else {
            // Tài khoản chưa gắn nhân viên — không có việc để hiển thị.
            $summary = $this->query->summaryFor(0);
            $buckets = ['overdue' => [], 'today' => [], 'upcoming' => [], 'no_due' => []];
            $dailyReportToday = null;
            $viewing = $this->employeeCard($viewer->employee, true);
        }

        return Inertia::render('MyWork/Index', [
            'mode' => $mode,
            'viewing' => $viewing,
            'summary' => $summary,
            'buckets' => $buckets,
            'dailyReportToday' => $dailyReportToday,
            'filters' => (object) $this->filters($request, dropNull: true),
            'options' => [
                'priorities' => TaskPriority::options(),
                'statuses' => TaskStatus::options(),
            ],
            'teamSummary' => $teamSummary,
            'teamScope' => $teamScope,
            'teamDepartmentLanes' => $teamDepartmentLanes,
            'team' => [
                'canTeamView' => $canTeamView,
                'canActTeam' => $viewer->allows('my_work.act_team'),
                'members' => $members,
            ],
        ]);
    }

    /**
     * Chi tiết việc của một thành viên — JSON cho modal "Xem nhanh" ở chế độ nhóm.
     *
     * Cùng cổng quyền + phạm vi như chế độ ?member= (LedTeamScope) nhưng trả gọn
     * buckets + summary để hiển thị trong modal, không rời trang.
     */
    public function memberTasks(Request $request, Employee $employee): JsonResponse
    {
        $viewer = $request->user();
        $selfId = (int) ($viewer->employee_id ?? 0);
        $targetId = (int) $employee->id;
        $isSelf = $targetId === $selfId;

        $canTeamView = $viewer->allows('my_work.view_team');
        $ledMemberIds = $canTeamView && $selfId > 0
            ? LedTeamScope::memberIds($selfId)
            : collect();

        $allowed = $isSelf
            || $viewer->isAdminTier()
            || ($canTeamView && $ledMemberIds->contains($targetId));
        abort_unless($allowed, 403, 'Bạn không có quyền xem việc của nhân sự này.');

        $canActTeam = ! $isSelf
            && ($viewer->isAdminTier()
                || ($viewer->allows('my_work.act_team') && $ledMemberIds->contains($targetId)));

        $data = $this->query->execute($viewer, $targetId, [], $canActTeam);

        return response()->json([
            'member' => $this->employeeCard($employee, $isSelf),
            'summary' => $data['summary'],
            'buckets' => $data['buckets'],
            'canActTeam' => $canActTeam,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request, bool $dropNull = false): array
    {
        $filters = [
            'q' => $request->query('q'),
            'project_id' => $request->query('project_id'),
            'priority' => $request->query('priority'),
            'status' => $request->query('status'),
        ];

        return $dropNull
            ? array_filter($filters, fn ($v) => $v !== null && $v !== '')
            : $filters;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function employeeCard(?Employee $employee, bool $isSelf): ?array
    {
        if ($employee === null) {
            return null;
        }

        return [
            'id' => $employee->id,
            'name' => $employee->full_name,
            'avatar_path' => PublicMediaUrl::fromPublicDisk($employee->avatar_path),
            'role_title' => $employee->role_title,
            'isSelf' => $isSelf,
        ];
    }

    /**
     * Roster nhóm cho chế độ "Nhóm của tôi": mỗi thành viên kèm số việc mở /
     * quá hạn / đến hạn hôm nay.
     *
     * Lưu ý: count dùng assignee_id (việc có người phụ trách chính) để gói trong
     * 1 truy vấn; chi tiết từng người (?member=) dùng đầy đủ predicate gồm pivot.
     *
     * @param  Collection<int, int>  $memberIds
     * @param  Collection<int, int>  $scopeTeamIds
     * @return array<int, array<string, mixed>>
     */
    private function memberRoster(Collection $memberIds, Collection $scopeTeamIds): array
    {
        if ($memberIds->isEmpty()) {
            return [];
        }

        $today = Carbon::today();

        $employees = Employee::query()
            ->whereIn('id', $memberIds)
            ->where('is_active', true)
            ->with(['departments' => fn ($q) => $q
                ->where('departments.is_active', true)
                ->orderBy('departments.sort_order')
                ->orderBy('departments.name')])
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'avatar_path', 'role_title']);

        $tasksByEmployee = Task::query()
            ->whereIn('assignee_id', $memberIds)
            ->where('status', '!=', TaskStatus::Done->value)
            ->get(['id', 'assignee_id', 'due_date', 'status'])
            ->groupBy('assignee_id');

        $orgPlacements = $this->orgPlacementsFor($memberIds, $scopeTeamIds);
        $orgUnitNames = EmployeeOrgUnitResolver::labelsFor($memberIds);

        return $employees->map(function (Employee $employee) use ($tasksByEmployee, $today, $orgPlacements, $orgUnitNames) {
            $tasks = $tasksByEmployee->get($employee->id, collect());
            $overdue = $tasks->filter(fn ($t) => $t->due_date !== null && $t->due_date->lt($today));
            $dueToday = $tasks->filter(fn ($t) => $t->due_date !== null && $t->due_date->isSameDay($today));
            $upcoming = $tasks->filter(fn ($t) => $t->due_date !== null && $t->due_date->gt($today));
            $noDue = $tasks->filter(fn ($t) => $t->due_date === null);
            $inProgress = $tasks->filter(fn ($t) => $t->status === TaskStatus::InProgress->value);

            $placement = $orgPlacements[$employee->id] ?? null;
            $departments = $employee->departments->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
            ])->values()->all();
            $primaryDepartment = $departments[0]['name'] ?? null;

            $groupDepartment = $primaryDepartment
                ?? ($placement['section_title'] ?? null)
                ?? ($orgUnitNames[$employee->id] ?? null);

            return [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'avatar_path' => PublicMediaUrl::fromPublicDisk($employee->avatar_path),
                'role_title' => $employee->role_title,
                'org_team_name' => $placement['team_name'] ?? null,
                'org_section_title' => $placement['section_title'] ?? null,
                'org_unit_name' => $orgUnitNames[$employee->id] ?? null,
                'departments' => $departments,
                'group_department' => $groupDepartment,
                'open' => $tasks->count(),
                'overdue' => $overdue->count(),
                'dueToday' => $dueToday->count(),
                'upcoming' => $upcoming->count(),
                'noDue' => $noDue->count(),
                'inProgress' => $inProgress->count(),
            ];
        })->values()->all();
    }

    /**
     * Nhóm tổ chức + phòng ban xuất hiện trong roster (cho banner phạm vi).
     *
     * @param  array<int, array<string, mixed>>  $members
     * @return array<string, mixed>
     */
    private function teamScopePayload(int $leaderEmployeeId, array $members): array
    {
        $meta = LedTeamScope::scopeMeta($leaderEmployeeId);

        $departmentNames = collect($members)
            ->flatMap(fn (array $m) => collect($m['departments'] ?? [])->pluck('name'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $orgTeamsInRoster = collect($members)
            ->pluck('org_team_name')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'ledTeams' => $meta['ledTeams'],
            'scopeLabel' => $meta['scopeLabel'],
            'rosterOrgTeams' => $orgTeamsInRoster,
            'rosterDepartments' => $departmentNames,
            'groupingHint' => 'Mỗi hàng ngang = phòng ban phụ trách dự án; thẻ dự án cuộn ngang bên trong',
        ];
    }

    /**
     * Swimlane theo phòng ban của dự án (giống Kanban /projects nhóm theo phòng ban).
     *
     * @param  Collection<int, int>  $memberIds
     * @return array{lanes: list<array<string, mixed>>}
     */
    private function teamDepartmentLanes(Collection $memberIds): array
    {
        $projectGroups = $this->teamProjectGroups($memberIds);
        if ($projectGroups === []) {
            return ['lanes' => []];
        }

        $byDepartment = collect($projectGroups)->groupBy(
            fn (array $g) => $g['project']['department_id'] ?? null,
        );

        $lanes = [];

        foreach (Options::departments() as $dept) {
            $projects = $byDepartment->get($dept['id'], collect())->values()->all();
            if ($projects === []) {
                continue;
            }

            $lanes[] = [
                'key' => 'd'.$dept['id'],
                'department_id' => $dept['id'],
                'label' => $dept['name'],
                'color' => $dept['color'] ?? 'slate',
                'open' => (int) collect($projects)->sum('open'),
                'overdue' => (int) collect($projects)->sum('overdue'),
                'dueToday' => (int) collect($projects)->sum('dueToday'),
                'projects' => $projects,
            ];
        }

        $unassigned = $byDepartment->get(null, collect())->values()->all();
        if ($unassigned !== []) {
            $lanes[] = [
                'key' => 'none',
                'department_id' => null,
                'label' => 'Chưa phân phòng',
                'color' => 'slate',
                'open' => (int) collect($unassigned)->sum('open'),
                'overdue' => (int) collect($unassigned)->sum('overdue'),
                'dueToday' => (int) collect($unassigned)->sum('dueToday'),
                'projects' => $unassigned,
            ];
        }

        return ['lanes' => $lanes];
    }

    /**
     * @param  Collection<int, int>  $memberIds
     * @return list<array<string, mixed>>
     */
    private function teamProjectGroups(Collection $memberIds): array
    {
        if ($memberIds->isEmpty()) {
            return [];
        }

        $today = Carbon::today();

        $tasks = Task::query()
            ->whereIn('assignee_id', $memberIds)
            ->where('status', '!=', TaskStatus::Done->value)
            ->with(['project:id,name,code,color,department_id', 'project.department:id,name,color'])
            ->get(['id', 'project_id', 'assignee_id', 'due_date', 'status']);

        if ($tasks->isEmpty()) {
            return [];
        }

        $employees = Employee::query()
            ->whereIn('id', $memberIds)
            ->get(['id', 'full_name', 'avatar_path'])
            ->keyBy('id');

        return $tasks->groupBy('project_id')->map(function (Collection $projectTasks, $projectId) use ($today, $employees) {
            $project = $projectTasks->first()->project;
            $overdue = $projectTasks->filter(fn ($t) => $t->due_date !== null && $t->due_date->lt($today));
            $dueToday = $projectTasks->filter(fn ($t) => $t->due_date !== null && $t->due_date->isSameDay($today));

            $members = $projectTasks->groupBy('assignee_id')->map(function (Collection $empTasks, $assigneeId) use ($employees, $today) {
                $emp = $employees->get((int) $assigneeId);
                $overdue = $empTasks->filter(fn ($t) => $t->due_date !== null && $t->due_date->lt($today));
                $dueToday = $empTasks->filter(fn ($t) => $t->due_date !== null && $t->due_date->isSameDay($today));

                return [
                    'id' => (int) $assigneeId,
                    'name' => $emp?->full_name,
                    'avatar_path' => PublicMediaUrl::fromPublicDisk($emp?->avatar_path),
                    'open' => $empTasks->count(),
                    'overdue' => $overdue->count(),
                    'dueToday' => $dueToday->count(),
                ];
            })->sortByDesc('open')->values()->all();

            return [
                'project' => [
                    'id' => (int) $projectId,
                    'name' => $project?->name,
                    'code' => $project?->code,
                    'color' => $project?->color,
                    'department_id' => $project?->department_id,
                    'department_name' => $project?->department?->name,
                ],
                'open' => $projectTasks->count(),
                'overdue' => $overdue->count(),
                'dueToday' => $dueToday->count(),
                'members' => $members,
            ];
        })->sortBy(fn (array $g) => $g['project']['name'] ?? '')->values()->all();
    }

    /**
     * @param  Collection<int, int>  $memberIds
     * @param  Collection<int, int>  $scopeTeamIds
     * @return array<int, array{team_name: ?string, section_title: ?string}>
     */
    private function orgPlacementsFor(Collection $memberIds, Collection $scopeTeamIds): array
    {
        if ($memberIds->isEmpty() || $scopeTeamIds->isEmpty()) {
            return [];
        }

        $out = [];

        OrgTeamMember::query()
            ->whereIn('employee_id', $memberIds)
            ->whereIn('org_team_id', $scopeTeamIds)
            ->with(['team:id,name', 'section:id,title'])
            ->orderBy('sort_order')
            ->get()
            ->groupBy('employee_id')
            ->each(function (Collection $rows, $employeeId) use (&$out): void {
                $row = $rows->first();
                if ($row === null) {
                    return;
                }
                $out[(int) $employeeId] = [
                    'team_name' => $row->team?->name,
                    'section_title' => $row->section?->title,
                ];
            });

        return $out;
    }

    /**
     * KPI tổng hợp nhóm (không phụ thuộc lọc client) cho strip thống kê chế độ team.
     *
     * @param  array<int, array<string, mixed>>  $members
     * @return array<string, int>
     */
    private function teamSummary(array $members): array
    {
        $sum = fn (string $key) => (int) array_sum(array_column($members, $key));

        $atRisk = collect($members)->filter(
            fn (array $m) => ($m['overdue'] ?? 0) > 0 || ($m['dueToday'] ?? 0) > 0,
        )->count();

        $clear = collect($members)->filter(fn (array $m) => ($m['open'] ?? 0) === 0)->count();

        return [
            'members' => count($members),
            'open' => $sum('open'),
            'overdue' => $sum('overdue'),
            'dueToday' => $sum('dueToday'),
            'inProgress' => $sum('inProgress'),
            'atRisk' => $atRisk,
            'clear' => $clear,
        ];
    }
}
