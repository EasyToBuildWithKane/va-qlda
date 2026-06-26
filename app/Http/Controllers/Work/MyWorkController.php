<?php

namespace App\Http\Controllers\Work;

use App\Application\Work\MyWorkQuery;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Task;
use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskStatus;
use App\Support\PublicMediaUrl;
use App\Support\Team\LedTeamScope;
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

        // ── Dữ liệu theo chế độ ──────────────────────────────────────────────
        if ($mode === 'team') {
            $summary = null;
            $buckets = null;
            $viewing = null;
        } elseif ($target > 0) {
            $data = $this->query->execute($viewer, $target, $this->filters($request), $canActTeam);
            $summary = $data['summary'];
            $buckets = $data['buckets'];
            $viewing = $this->employeeCard($isSelf ? $viewer->employee : Employee::find($target), $isSelf);
        } else {
            // Tài khoản chưa gắn nhân viên — không có việc để hiển thị.
            $summary = $this->query->summaryFor(0);
            $buckets = ['overdue' => [], 'today' => [], 'upcoming' => [], 'no_due' => []];
            $viewing = $this->employeeCard($viewer->employee, true);
        }

        return Inertia::render('MyWork/Index', [
            'mode' => $mode,
            'viewing' => $viewing,
            'summary' => $summary,
            'buckets' => $buckets,
            'filters' => (object) $this->filters($request, dropNull: true),
            'options' => [
                'priorities' => TaskPriority::options(),
                'statuses' => TaskStatus::options(),
            ],
            'team' => [
                'canTeamView' => $canTeamView,
                'canActTeam' => $viewer->allows('my_work.act_team'),
                'members' => $canTeamView ? $this->memberRoster($ledMemberIds) : [],
            ],
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
     * @return array<int, array<string, mixed>>
     */
    private function memberRoster(Collection $memberIds): array
    {
        if ($memberIds->isEmpty()) {
            return [];
        }

        $today = Carbon::today();

        $employees = Employee::query()
            ->whereIn('id', $memberIds)
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'avatar_path', 'role_title']);

        $tasksByEmployee = Task::query()
            ->whereIn('assignee_id', $memberIds)
            ->where('status', '!=', TaskStatus::Done->value)
            ->get(['id', 'assignee_id', 'due_date'])
            ->groupBy('assignee_id');

        return $employees->map(function (Employee $employee) use ($tasksByEmployee, $today) {
            $tasks = $tasksByEmployee->get($employee->id, collect());

            return [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'avatar_path' => PublicMediaUrl::fromPublicDisk($employee->avatar_path),
                'role_title' => $employee->role_title,
                'open' => $tasks->count(),
                'overdue' => $tasks->filter(fn ($t) => $t->due_date !== null && $t->due_date->lt($today))->count(),
                'dueToday' => $tasks->filter(fn ($t) => $t->due_date !== null && $t->due_date->isSameDay($today))->count(),
            ];
        })->values()->all();
    }
}
