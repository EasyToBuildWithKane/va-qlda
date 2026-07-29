<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\EmployeeProfileResource;
use App\Models\Employee;
use App\Services\Hrm\HrmIdentityResolver;
use App\Support\Profile\SkillCatalog;
use App\Support\SecurityAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Hồ sơ của tôi" — HR identity is mirrored from VA-HRM (read-only here).
 * Self-service writes are limited to Workspace skill matrix.
 */
class ProfileController extends Controller
{
    public function show(Request $request, HrmIdentityResolver $hrmResolver): Response
    {
        $employee = $this->currentEmployee($request);

        if ($employee !== null) {
            $employee = $hrmResolver->refreshEmployeeIfLinked($employee);
        }

        if ($employee === null) {
            return Inertia::render('Profile/Show', [
                'profile' => null,
                'editable' => false,
            ]);
        }

        $employee->load([
            'account:id,employee_id,role',
            'orgMemberships.team:id,name,leader_id,parent_id,level',
            'orgMemberships.team.leader:id,full_name,avatar_path,code,email,role_title',
            'orgMemberships.team.parent:id,name,leader_id,parent_id,level',
            'orgMemberships.team.parent.leader:id,full_name,avatar_path,code,email,role_title',
            'orgMemberships.section:id,org_team_id,title',
            'ledTeams:id,name,leader_id,parent_id,level',
            'ledTeams.parent:id,name,leader_id,parent_id,level',
            'ledTeams.parent.leader:id,full_name,avatar_path,code,email,role_title',
            'projects',
        ]);

        return Inertia::render('Profile/Show', [
            'profile' => (new EmployeeProfileResource($employee))->toArray($request),
            'editable' => true,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        /** @var \App\Models\Employee $employee */
        $employee = $this->currentEmployee($request) ?? abort(404);

        $this->authorize('update', $employee);

        $data = $request->validated();
        $meta = is_array($employee->meta) ? $employee->meta : [];

        [$skillNames, $meta['skill_details']] = $this->normalizeSkills(array_values($data['skills'] ?? []));

        DB::transaction(fn () => $employee->update([
            'skills' => $skillNames,
            'meta' => $meta,
        ]));

        SecurityAuditLogger::employeeUpdated($request->user(), $employee->id, (string) $employee->full_name);

        return back()->with('success', 'Đã cập nhật kỹ năng.');
    }

    /**
     * @param  list<array<string, mixed>>  $skills
     * @return array{0: list<string>, 1: list<array<string, mixed>>}
     */
    private function normalizeSkills(array $skills): array
    {
        $names = [];
        $details = [];
        foreach ($skills as $s) {
            $name = trim((string) ($s['name'] ?? $s['title'] ?? ''));
            if ($name === '') {
                continue;
            }
            $names[] = $name;
            $group = trim((string) ($s['category'] ?? ''));
            $details[] = [
                'name' => $name,
                'level' => (int) ($s['level'] ?? 3),
                'category' => $group !== '' ? $group : SkillCatalog::DEFAULT_GROUP,
                'years' => isset($s['years']) && $s['years'] !== '' ? (float) $s['years'] : null,
                'note' => isset($s['note']) && trim((string) $s['note']) !== '' ? trim((string) $s['note']) : null,
            ];
        }

        return [$names, $details];
    }

    private function currentEmployee(Request $request): ?Employee
    {
        $id = $request->user()?->employee_id;

        return $id ? Employee::find($id) : null;
    }
}
