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
            ])
            ->withCount('projects')
            ->orderBy('full_name');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('role_title', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $status = $request->query('status');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $perPage = $request->integer('per_page', 12);
        if (! in_array($perPage, [12, 24, 48], true)) {
            $perPage = 12;
        }

        return Inertia::render('Member/Index', [
            'members' => MemberCardResource::collection(
                $query->paginate($perPage)->withQueryString(),
            ),
            'filters' => (object) $request->only(['q', 'status', 'per_page']),
            'summary' => $this->memberDirectorySummary(),
        ]);
    }

    /**
     * @return array{total: int, active: int, inactive: int}
     */
    private function memberDirectorySummary(): array
    {
        $row = Employee::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')
            ->selectRaw('SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'active' => (int) ($row->active ?? 0),
            'inactive' => (int) ($row->inactive ?? 0),
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
            'projects',
        ]);

        return Inertia::render('Member/Show', [
            'profile' => (new EmployeeProfileResource($employee))->toArray($request),
            'editable' => false,
        ]);
    }
}
