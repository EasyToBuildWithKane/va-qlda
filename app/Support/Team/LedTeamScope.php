<?php

namespace App\Support\Team;

use App\Models\Employee;
use App\Models\OrgTeam;
use App\Models\OrgTeamMember;
use Illuminate\Support\Collection;

/**
 * "Nhân sự dưới quyền tôi" — nhân viên thuộc các Org Team mà người dùng làm
 * trưởng nhóm (leader_id), gồm cả nhóm con (descendantIds) và trưởng nhóm con.
 *
 * Hai lớp tách bạch trong RBAC của module "Việc của tôi":
 *   - Cổng quyền  (my_work.view_team / my_work.act_team) — do ma trận điều khiển.
 *   - Phạm vi dữ liệu (lớp này) — giới hạn để "thành viên của tôi" đúng nghĩa.
 *
 * Công thức member + leader tái dùng từ PerformanceFilter::teamEmployeeIds()
 * (app/Support/Performance/PerformanceFilter.php) — chỉ khác ở chỗ phạm vi neo
 * theo *trưởng nhóm* thay vì một teamId tuỳ chọn.
 */
class LedTeamScope
{
    /**
     * Org team đang hoạt động mà $leaderEmployeeId làm trưởng.
     *
     * @return Collection<int, OrgTeam>
     */
    public static function ledTeams(int $leaderEmployeeId): Collection
    {
        if ($leaderEmployeeId <= 0) {
            return collect();
        }

        return OrgTeam::query()
            ->where('is_active', true)
            ->where('leader_id', $leaderEmployeeId)
            ->orderBy('level')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Id mọi nhân viên trong các nhóm tôi phụ trách (+ nhóm con + trưởng nhóm con).
     * KHÔNG gồm chính người lead — "thành viên của tôi" = cấp dưới.
     *
     * @return Collection<int, int>
     */
    public static function memberIds(int $leaderEmployeeId): Collection
    {
        $teamIds = self::scopedTeamIds($leaderEmployeeId);
        if ($teamIds->isEmpty()) {
            return collect();
        }

        $memberIds = OrgTeamMember::query()
            ->whereIn('org_team_id', $teamIds)
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id);

        $subLeaderIds = OrgTeam::query()
            ->whereIn('id', $teamIds)
            ->whereNotNull('leader_id')
            ->pluck('leader_id')
            ->map(fn ($id) => (int) $id);

        return $memberIds
            ->merge($subLeaderIds)
            ->reject(fn (int $id) => $id === $leaderEmployeeId)
            ->unique()
            ->values();
    }

    /**
     * Mọi org team (trực tiếp + con) trong phạm vi một trưởng nhóm.
     *
     * @return Collection<int, int>
     */
    public static function scopedTeamIds(int $leaderEmployeeId): Collection
    {
        $ledTeams = self::ledTeams($leaderEmployeeId);
        if ($ledTeams->isEmpty()) {
            return collect();
        }

        return $ledTeams
            ->flatMap(fn (OrgTeam $t) => array_merge([$t->id], $t->descendantIds()))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Meta phạm vi cho UI — nhóm tổ chức lead đang phụ trách.
     *
     * @return array{ledTeams: list<array{id:int, name:string, level:int}>, scopeLabel: string}
     */
    public static function scopeMeta(int $leaderEmployeeId): array
    {
        $ledTeams = self::ledTeams($leaderEmployeeId);

        return [
            'ledTeams' => $ledTeams->map(fn (OrgTeam $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'level' => $t->level,
            ])->values()->all(),
            'scopeLabel' => $ledTeams->isEmpty()
                ? 'Chưa gán nhóm tổ chức'
                : $ledTeams->pluck('name')->join(' · '),
        ];
    }

    /**
     * $leaderEmployeeId có phụ trách (trực/gián tiếp) nhân viên $targetEmployeeId?
     */
    public static function leads(int $leaderEmployeeId, int $targetEmployeeId): bool
    {
        if ($leaderEmployeeId <= 0 || $targetEmployeeId <= 0) {
            return false;
        }

        return self::memberIds($leaderEmployeeId)->contains($targetEmployeeId);
    }

    /**
     * Bất kỳ id nào trong $targetEmployeeIds nằm dưới quyền $leaderEmployeeId?
     *
     * @param  array<int, int>  $targetEmployeeIds
     */
    public static function leadsAny(int $leaderEmployeeId, array $targetEmployeeIds): bool
    {
        if ($leaderEmployeeId <= 0 || $targetEmployeeIds === []) {
            return false;
        }

        $members = self::memberIds($leaderEmployeeId);

        foreach ($targetEmployeeIds as $id) {
            if ($members->contains((int) $id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Nhân viên (đang hoạt động) dưới quyền — dùng cho roster + dropdown chọn người.
     *
     * @return Collection<int, Employee>
     */
    public static function members(int $leaderEmployeeId): Collection
    {
        $ids = self::memberIds($leaderEmployeeId);
        if ($ids->isEmpty()) {
            return collect();
        }

        return Employee::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'avatar_path', 'role_title']);
    }
}
