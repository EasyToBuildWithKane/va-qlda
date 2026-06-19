<?php

namespace App\Support\OrgTeam;

use App\Models\OrgTeam;

/**
 * Suy ra "lĩnh vực" (đội con của sơ đồ tổ chức — vd. Phần cứng / Phần mềm dưới
 * Phòng Công nghệ) cho từng nhân sự, để nhóm dự án theo đội của người quản lý.
 *
 * Chỉ dùng dữ liệu org-teams có sẵn — không bịa thêm trường trên dự án.
 */
class EmployeeOrgTeamMap
{
    /** Palette khớp với map `dot`/`stripe` ở Kanban (Index.vue / ProjectCard.vue). */
    private const PALETTE = ['brand', 'sky', 'emerald', 'violet', 'amber', 'cyan', 'rose', 'slate'];

    /**
     * @return array{
     *     teams: list<array{id:int, name:string, color:string}>,
     *     byEmployee: array<int, int>
     * }
     */
    public static function build(): array
    {
        $teams = OrgTeam::query()
            ->where('is_active', true)
            ->where('level', '>', 1)
            ->with('members:id,org_team_id,employee_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'leader_id', 'sort_order']);

        $options = [];
        $byEmployee = [];

        foreach ($teams->values() as $i => $team) {
            $options[] = [
                'id' => $team->id,
                'name' => $team->name,
                'color' => self::PALETTE[$i % count(self::PALETTE)],
            ];

            // Đội trưởng trước, rồi tới thành viên; ai gặp đầu tiên thì giữ.
            if ($team->leader_id && ! isset($byEmployee[$team->leader_id])) {
                $byEmployee[$team->leader_id] = $team->id;
            }
            foreach ($team->members as $member) {
                if ($member->employee_id && ! isset($byEmployee[$member->employee_id])) {
                    $byEmployee[$member->employee_id] = $team->id;
                }
            }
        }

        return ['teams' => $options, 'byEmployee' => $byEmployee];
    }
}
