<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeProfileResource;
use App\Http\Resources\MemberCardResource;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Member directory + member profile pages (Hồ sơ thành viên).
 *
 * Read-mostly: identity, skills and growth sections from talent tables.
 */
class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        $query = Employee::query()
            ->select([
                'id',
                'code',
                'full_name',
                'avatar_path',
                'role_title',
                'email',
                'is_active',
                'skills',
                'meta',
            ])
            ->with([
                'projects' => fn ($q) => $q
                    ->tap(fn ($q) => Employee::applyActiveProjectPivotFilter($q))
                    ->select('projects.id', 'projects.name', 'projects.code', 'projects.color')
                    ->orderBy('projects.name'),
                'managedProjects' => fn ($q) => $q
                    ->select('projects.id', 'projects.manager_id', 'projects.name', 'projects.code', 'projects.color')
                    ->orderBy('projects.name'),
            ])
            ->orderBy('full_name');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('role_title', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('projects', function ($pq) use ($search) {
                        Employee::applyActiveProjectPivotFilter($pq);
                        $pq->where(function ($pq2) use ($search) {
                            $pq2->where('projects.name', 'like', "%{$search}%")
                                ->orWhere('projects.code', 'like', "%{$search}%");
                        });
                    })
                    ->orWhereHas('managedProjects', function ($mq) use ($search) {
                        $mq->where(function ($mq2) use ($search) {
                            $mq2->where('projects.name', 'like', "%{$search}%")
                                ->orWhere('projects.code', 'like', "%{$search}%");
                        });
                    });
            });
        }

        $status = $request->query('status');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $projectScope = $request->query('project');
        if ($projectScope === 'assigned') {
            $query->participatingInProjects();
        } elseif ($projectScope === 'unassigned') {
            $query->where('is_active', true)->notParticipatingInProjects();
        }

        $perPage = $request->integer('per_page', 12);
        if (! in_array($perPage, [12, 24, 48], true)) {
            $perPage = 12;
        }

        return Inertia::render('Member/Index', [
            'members' => MemberCardResource::collection(
                $query->paginate($perPage)->withQueryString(),
            ),
            'filters' => (object) $request->only(['q', 'status', 'project', 'per_page']),
            'summary' => $this->memberDirectorySummary(),
        ]);
    }

    /**
     * @return array{total: int, active: int, inactive: int, on_project: int, no_project: int}
     */
    private function memberDirectorySummary(): array
    {
        $row = Employee::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')
            ->selectRaw('SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive')
            ->first();

        $onProject = Employee::query()->participatingInProjects()->count();

        $noProject = Employee::query()
            ->where('is_active', true)
            ->notParticipatingInProjects()
            ->count();

        return [
            'total' => (int) ($row->total ?? 0),
            'active' => (int) ($row->active ?? 0),
            'inactive' => (int) ($row->inactive ?? 0),
            'on_project' => $onProject,
            'no_project' => $noProject,
        ];
    }

    public function show(Request $request, Employee $employee): Response
    {
        $this->authorize('view', $employee);

        $employee->load([
            'account:id,employee_id,role',
            'orgMemberships.team:id,name,leader_id',
            'orgMemberships.team.leader:id,full_name,avatar_path,code,email,role_title',
            'orgMemberships.section:id,org_team_id,title',
            'projects' => function ($q) {
                Employee::applyActiveProjectPivotFilter($q);
            },
            'managedProjects',
        ]);

        return Inertia::render('Member/Show', [
            'profile' => (new EmployeeProfileResource($employee))->toArray($request),
            'editable' => false,
        ]);
    }
}
