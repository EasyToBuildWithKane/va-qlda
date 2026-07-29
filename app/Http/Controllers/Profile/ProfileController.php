<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeProfileResource;
use App\Models\Employee;
use App\Services\Hrm\HrmIdentityResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Hồ sơ của tôi" — HR identity + skills mirrored from VA-HRM (read-only).
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
        ]);
    }

    private function currentEmployee(Request $request): ?Employee
    {
        $id = $request->user()?->employee_id;

        return $id ? Employee::find($id) : null;
    }
}
