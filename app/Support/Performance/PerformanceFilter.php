<?php

namespace App\Support\Performance;

use App\Models\Employee;
use App\Models\OrgTeam;
use App\Models\OrgTeamMember;
use App\Models\Project;
use App\Models\Sprint;
use App\Support\DashboardPersonnelScope;
use App\Support\Enums\TaskStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Value object cho bộ lọc toàn module Hiệu suất & Audit.
 *
 * Giải mã query (periodType + anchor + scope) thành khoảng thời gian {start,end}
 * và tập employee ids đã phạm vi hóa. Mọi widget đọc cùng một filter để đồng bộ.
 *
 * Mặc định phạm vi nhân sự = phòng ban HRM của user đang đăng nhập
 * (fallback Phòng Công nghệ khi chưa gắn đơn vị).
 */
class PerformanceFilter
{
    /** @var list<string> */
    public const PERIODS = ['week', 'month', 'quarter', 'year', 'sprint'];

    private ?Collection $resolvedEmployeeIds = null;

    /**
     * @param  list<string>  $statuses
     */
    public function __construct(
        public readonly string $periodType,
        public readonly Carbon $start,
        public readonly Carbon $end,
        public readonly string $label,
        public readonly ?int $sprintId,
        public readonly ?int $departmentId,
        public readonly ?string $departmentCode,
        public readonly ?string $departmentName,
        public readonly bool $allDepartments,
        public readonly ?int $teamId,
        public readonly ?string $unitKey,
        public readonly ?int $memberId,
        public readonly ?int $projectId,
        public readonly array $statuses,
        private readonly DashboardPersonnelScope $personnelScope,
        private readonly PerformancePersonnelResolver $hrmScope,
        public readonly string $anchorDate,
    ) {}

    public static function fromRequest(Request $request, DashboardPersonnelScope $personnelScope): self
    {
        $tz = config('performance.timezone', 'Asia/Ho_Chi_Minh');

        $period = (string) $request->query('period', config('performance.default_period', 'month'));
        if (! in_array($period, self::PERIODS, true)) {
            $period = 'month';
        }

        $anchorRaw = (string) $request->query('date', Carbon::now($tz)->toDateString());
        try {
            $anchor = Carbon::parse($anchorRaw, $tz);
        } catch (\Throwable) {
            $anchor = Carbon::now($tz);
        }

        $sprintId = self::intOrNull($request->query('sprint'));
        $sprint = $period === 'sprint' && $sprintId ? Sprint::query()->find($sprintId) : null;

        [$start, $end, $label] = self::resolveRange($period, $anchor, $sprint, $tz, Carbon::now($tz));

        $statuses = (array) $request->query('status', []);
        $statuses = array_values(array_filter(
            array_map('strval', $statuses),
            fn (string $s) => in_array($s, TaskStatus::values(), true),
        ));

        $hrmScope = app(PerformancePersonnelResolver::class);
        $hrmScope->syncEmployees();

        $dept = $hrmScope->resolveDepartment(
            $request->query('department') !== null ? (string) $request->query('department') : null,
            $request->user('system'),
        );

        $teamRaw = $request->query('team');
        $teamId = self::intOrNull($teamRaw);
        $unitKey = $teamId === null && filled($teamRaw) ? trim((string) $teamRaw) : null;

        return new self(
            periodType: $period,
            start: $start,
            end: $end,
            label: $label,
            sprintId: $sprint?->id,
            departmentId: $dept['local_id'],
            departmentCode: $dept['code'],
            departmentName: $dept['name'],
            allDepartments: $dept['all'],
            teamId: $teamId,
            unitKey: $unitKey,
            memberId: self::intOrNull($request->query('member')),
            projectId: self::intOrNull($request->query('project')),
            statuses: $statuses,
            personnelScope: $personnelScope,
            hrmScope: $hrmScope,
            anchorDate: $anchor->toDateString(),
        );
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private static function resolveRange(string $period, Carbon $anchor, ?Sprint $sprint, string $tz, Carbon $now): array
    {
        $capEnd = static function (Carbon $end) use ($now): Carbon {
            return $end->gt($now) ? $now->copy()->endOfDay() : $end;
        };

        return match ($period) {
            'week' => (function () use ($anchor, $capEnd) {
                $s = $anchor->copy()->startOfWeek();
                $e = $capEnd($anchor->copy()->endOfWeek());

                return [$s, $e, PerformanceDisplay::rangeLabel($s, $e)];
            })(),
            'quarter' => (function () use ($anchor, $capEnd) {
                $s = $anchor->copy()->startOfQuarter();
                $e = $capEnd($anchor->copy()->endOfQuarter());

                return [$s, $e, PerformanceDisplay::rangeLabel($s, $e)];
            })(),
            'year' => (function () use ($anchor, $capEnd) {
                $s = $anchor->copy()->startOfYear();
                $e = $capEnd($anchor->copy()->endOfYear());

                return [$s, $e, PerformanceDisplay::rangeLabel($s, $e)];
            })(),
            'sprint' => $sprint && $sprint->start_date && $sprint->end_date
                ? (function () use ($sprint, $tz, $capEnd) {
                    $s = Carbon::parse($sprint->start_date, $tz)->startOfDay();
                    $e = $capEnd(Carbon::parse($sprint->end_date, $tz)->endOfDay());

                    return [$s, $e, 'Sprint: '.$sprint->name.' · '.PerformanceDisplay::rangeLabel($s, $e)];
                })()
                : (function () use ($anchor, $capEnd) {
                    $s = $anchor->copy()->startOfMonth();
                    $e = $capEnd($anchor->copy()->endOfMonth());

                    return [$s, $e, PerformanceDisplay::rangeLabel($s, $e)];
                })(),
            default => (function () use ($anchor, $capEnd) {
                $s = $anchor->copy()->startOfMonth();
                $e = $capEnd($anchor->copy()->endOfMonth());

                return [$s, $e, PerformanceDisplay::rangeLabel($s, $e)];
            })(),
        };
    }

    /**
     * Kỳ liền trước cùng độ dài — dùng cho delta tăng/giảm trên KPI card.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function previousRange(): array
    {
        return match ($this->periodType) {
            'week' => [$this->start->copy()->subWeek(), $this->end->copy()->subWeek()],
            'quarter' => [$this->start->copy()->subQuarter(), $this->end->copy()->subQuarter()],
            'year' => [$this->start->copy()->subYear(), $this->end->copy()->subYear()],
            'sprint' => [
                $this->start->copy()->subDays($this->start->diffInDays($this->end) + 1),
                $this->start->copy()->subDay(),
            ],
            default => [$this->start->copy()->subMonthNoOverflow(), $this->end->copy()->subMonthNoOverflow()],
        };
    }

    /**
     * Tập employee ids đã phạm vi hóa theo member → team Org → đơn vị HRM → phòng ban HRM.
     *
     * @return Collection<int, int>
     */
    public function employeeIds(): Collection
    {
        if ($this->resolvedEmployeeIds !== null) {
            return $this->resolvedEmployeeIds;
        }

        if ($this->memberId) {
            return $this->resolvedEmployeeIds = collect([$this->memberId]);
        }

        if ($this->teamId) {
            return $this->resolvedEmployeeIds = $this->teamEmployeeIds($this->teamId);
        }

        return $this->resolvedEmployeeIds = $this->hrmScope
            ->employeeIds($this->departmentCode, $this->allDepartments, $this->unitKey)
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    private function teamEmployeeIds(int $teamId): Collection
    {
        $team = OrgTeam::query()->find($teamId);
        if (! $team) {
            return collect();
        }

        $teamIds = array_merge([$team->id], $team->descendantIds());

        $memberIds = OrgTeamMember::query()
            ->whereIn('org_team_id', $teamIds)
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id);

        $leaderIds = OrgTeam::query()
            ->whereIn('id', $teamIds)
            ->whereNotNull('leader_id')
            ->pluck('leader_id')
            ->map(fn ($id) => (int) $id);

        return $memberIds->merge($leaderIds)->unique()->values();
    }

    /**
     * Echo lại filter + options cho frontend (sticky filter bar).
     *
     * @return array<string, mixed>
     */
    public function toClientArray(): array
    {
        return [
            'period' => $this->periodType,
            'date' => $this->anchorDate,
            'label' => $this->label,
            'range' => [
                'start' => $this->start->toDateString(),
                'end' => $this->end->toDateString(),
            ],
            'sprint' => $this->sprintId,
            'department' => $this->allDepartments ? PerformancePersonnelResolver::ALL : $this->departmentCode,
            'department_name' => $this->departmentName,
            'team' => $this->unitKey ?? $this->teamId,
            'member' => $this->memberId,
            'project' => $this->projectId,
            'status' => $this->statuses,
        ];
    }

    /**
     * Danh sách lựa chọn cho các dropdown bộ lọc (chỉ dữ liệu thật).
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        $memberIds = $this->employeeIds();

        return [
            'periods' => [
                ['value' => 'week', 'label' => 'Tuần'],
                ['value' => 'month', 'label' => 'Tháng'],
                ['value' => 'quarter', 'label' => 'Quý'],
                ['value' => 'year', 'label' => 'Năm'],
                ['value' => 'sprint', 'label' => 'Sprint'],
            ],
            'statuses' => TaskStatus::options(),
            'departments' => $this->hrmScope->departmentOptions(),
            'teams' => $this->unitOptions(),
            'members' => Employee::query()
                ->whereIn('id', $memberIds)
                ->orderBy('full_name')
                ->get(['id', 'full_name'])
                ->map(fn (Employee $e) => ['value' => $e->id, 'label' => $e->full_name])
                ->values(),
            'projects' => Project::query()
                ->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'color'])
                ->map(fn (Project $p) => ['value' => $p->id, 'label' => $p->name, 'color' => $p->color])
                ->values(),
            'sprints' => Project::query()
                ->with(['sprints:id,project_id,name,start_date,end_date'])
                ->get(['id', 'name'])
                ->flatMap(fn (Project $p) => $p->sprints->map(fn (Sprint $s) => [
                    'value' => $s->id,
                    'label' => $p->name.' · '.$s->name,
                ]))
                ->values(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{value: string, label: string}>
     */
    private function unitOptions()
    {
        $fromHrm = collect($this->hrmScope->unitOptions($this->allDepartments ? null : $this->departmentCode));
        if ($fromHrm->isNotEmpty()) {
            return $fromHrm->values();
        }

        return OrgTeam::query()->where('is_active', true)
            ->orderBy('level')->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (OrgTeam $t) => ['value' => (string) $t->id, 'label' => $t->name])
            ->values();
    }

    private static function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
