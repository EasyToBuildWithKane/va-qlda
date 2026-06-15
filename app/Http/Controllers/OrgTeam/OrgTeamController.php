<?php

namespace App\Http\Controllers\OrgTeam;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrgTeam\StoreOrgTeamRequest;
use App\Http\Requests\OrgTeam\UpdateOrgTeamRequest;
use App\Http\Resources\OrgTeamRosterEntryResource;
use App\Models\OrgTeam;
use App\Support\Options;
use App\Support\OrgTeam\OrgTeamOverviewBuilder;
use App\Support\OrgTeam\OrgTeamRosterBuilder;
use App\Support\OrgTeamTreeBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
            'overview' => OrgTeamOverviewBuilder::build(),
            'parentOptions' => $flatTeams->map(fn (OrgTeam $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'level' => $t->level,
                'parent_id' => $t->parent_id,
                'label' => str_repeat('— ', max(0, $t->level - 1)).$t->name,
            ])->values(),
            'employees' => Options::employees(),
            'can' => [
                'create' => $request->user()->can('create', OrgTeam::class),
            ],
        ]);
    }

    public function members(Request $request): Response
    {
        $this->authorize('viewAny', OrgTeam::class);

        $allRows = OrgTeamRosterBuilder::allRows();
        $filtered = OrgTeamRosterBuilder::filtered($request);

        $perPage = $request->integer('per_page', 24);
        if (! in_array($perPage, [24, 48, 96], true)) {
            $perPage = 24;
        }

        $page = max(1, $request->integer('page', 1));
        $items = $filtered->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $items,
            $filtered->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $flatTeams = OrgTeam::query()
            ->orderBy('level')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'level', 'parent_id']);

        return Inertia::render('OrgTeam/Members', [
            'roster' => OrgTeamRosterEntryResource::collection($paginator),
            'filters' => (object) $request->only(['q', 'root_id', 'team_id', 'branch', 'status', 'per_page']),
            'summary' => OrgTeamRosterBuilder::summary($allRows),
            'filteredCount' => $filtered->count(),
            'rootOptions' => OrgTeam::query()->whereNull('parent_id')->orderBy('name')->get(['id', 'name']),
            'teamOptions' => $flatTeams->map(fn (OrgTeam $t) => [
                'id' => $t->id,
                'label' => str_repeat('— ', max(0, $t->level - 1)).$t->name,
            ])->values(),
            'branchOptions' => OrgTeamRosterBuilder::branchOptions(),
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

            $this->syncSectionsAndMembers(
                $team,
                $request->validated('sections') ?? [],
                $request->validated('members') ?? [],
            );
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

            if ($request->has('members') || $request->has('sections')) {
                $this->syncSectionsAndMembers(
                    $orgTeam,
                    $request->validated('sections') ?? [],
                    $request->validated('members') ?? [],
                );
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

    private function recalcDescendantLevels(OrgTeam $team): void
    {
        $team->load('children');
        foreach ($team->children as $child) {
            $child->update(['level' => $team->level + 1]);
            $this->recalcDescendantLevels($child);
        }
    }

    /**
     * @param  list<array{title: string, sort_order?: int}>  $sections
     * @param  list<array{employee_id: int, branch?: string|null, section_index?: int|null, sort_order?: int}>  $rows
     */
    private function syncSectionsAndMembers(OrgTeam $team, array $sections, array $rows): void
    {
        $team->members()->delete();
        $team->sections()->delete();

        /** @var list<int> $sectionIds */
        $sectionIds = [];
        foreach (array_values($sections) as $index => $sectionRow) {
            $title = trim((string) $sectionRow['title']);
            if ($title === '') {
                continue;
            }
            $section = $team->sections()->create([
                'title' => $title,
                'sort_order' => $sectionRow['sort_order'] ?? $index,
            ]);
            $sectionIds[$index] = $section->id;
        }

        foreach (array_values($rows) as $index => $row) {
            $sectionId = null;
            if (isset($row['section_index']) && $row['section_index'] !== '' && $row['section_index'] !== null) {
                $sectionIndex = (int) $row['section_index'];
                $sectionId = $sectionIds[$sectionIndex] ?? null;
            }

            $team->members()->create([
                'employee_id' => (int) $row['employee_id'],
                'section_id' => $sectionId,
                'branch' => ! empty($row['branch']) ? $row['branch'] : null,
                'sort_order' => $row['sort_order'] ?? $index,
            ]);
        }
    }
}
