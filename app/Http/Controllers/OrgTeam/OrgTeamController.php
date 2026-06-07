<?php

namespace App\Http\Controllers\OrgTeam;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrgTeam\StoreOrgTeamRequest;
use App\Http\Requests\OrgTeam\UpdateOrgTeamRequest;
use App\Models\OrgTeam;
use App\Support\Enums\OrgTeamMemberBranch;
use App\Support\Options;
use App\Support\OrgTeamTreeBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrgTeamController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', OrgTeam::class);

        $flatTeams = OrgTeam::query()
            ->orderBy('level')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'level', 'parent_id']);

        return Inertia::render('OrgTeam/Index', [
            'trees' => OrgTeamTreeBuilder::forest(),
            'parentOptions' => $flatTeams->map(fn (OrgTeam $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'level' => $t->level,
                'parent_id' => $t->parent_id,
                'label' => str_repeat('— ', max(0, $t->level - 1)).$t->name,
            ])->values(),
            'employees' => Options::employees(),
            'branchOptions' => OrgTeamMemberBranch::options(),
            'levelHints' => [
                1 => 'Cấp 1: Ban / khối (vd. Leader Phần Mềm)',
                2 => 'Cấp 2: Đội nhóm (vd. Đội ngũ Dev)',
                3 => 'Cấp 3: Nhánh / tổ (phân nhánh GVS, phần mềm PB, trợ lý dự án)',
            ],
            'can' => [
                'create' => $request->user()->can('create', OrgTeam::class),
            ],
        ]);
    }

    public function store(StoreOrgTeamRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $team = OrgTeam::create([
                'name' => $request->validated('name'),
                'parent_id' => $request->validated('parent_id'),
                'level' => $request->resolvedLevel(),
                'leader_id' => $request->validated('leader_id'),
                'sort_order' => $request->validated('sort_order') ?? 0,
                'is_active' => $request->validated('is_active') ?? true,
            ]);

            $this->syncMembers($team, $request->validated('members') ?? []);
        });

        return back()->with('success', 'Đã tạo nhóm.');
    }

    public function update(UpdateOrgTeamRequest $request, OrgTeam $orgTeam): RedirectResponse
    {
        DB::transaction(function () use ($request, $orgTeam) {
            $data = $request->safe()->only(['name', 'leader_id', 'sort_order', 'is_active']);

            if ($request->has('parent_id')) {
                $parentId = $request->validated('parent_id');
                $data['parent_id'] = $parentId;
                if ($parentId === null) {
                    $data['level'] = 1;
                } else {
                    $parent = OrgTeam::query()->findOrFail((int) $parentId);
                    $data['level'] = $parent->level + 1;
                }
            }

            $orgTeam->update($data);

            if ($request->has('parent_id')) {
                $orgTeam->refresh();
                $this->recalcDescendantLevels($orgTeam);
            }

            if ($request->has('members')) {
                $this->syncMembers($orgTeam, $request->validated('members') ?? []);
            }
        });

        return back()->with('success', 'Đã cập nhật nhóm.');
    }

    public function destroy(OrgTeam $orgTeam): RedirectResponse
    {
        $this->authorize('delete', $orgTeam);

        $orgTeam->delete();

        return back()->with('success', 'Đã xoá nhóm và các nhóm con.');
    }

    /**
     * @param  list<array{employee_id: int, branch?: string|null, sort_order?: int}>  $rows
     */
    private function recalcDescendantLevels(OrgTeam $team): void
    {
        $team->load('children');
        foreach ($team->children as $child) {
            $child->update(['level' => $team->level + 1]);
            $this->recalcDescendantLevels($child);
        }
    }

    /**
     * @param  list<array{employee_id: int, branch?: string|null, sort_order?: int}>  $rows
     */
    private function syncMembers(OrgTeam $team, array $rows): void
    {
        $team->members()->delete();

        foreach (array_values($rows) as $index => $row) {
            $team->members()->create([
                'employee_id' => (int) $row['employee_id'],
                'branch' => ! empty($row['branch']) ? $row['branch'] : null,
                'sort_order' => $row['sort_order'] ?? $index,
            ]);
        }
    }
}
