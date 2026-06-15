<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\EmployeeProfileResource;
use App\Models\Employee;
use App\Services\Cms\CmsEmployeeSyncService;
use App\Support\Profile\SkillCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Hồ sơ của tôi" — the authenticated person's own profile, with self-service
 * editing. Reuses the same presentation as the member profile.
 */
class ProfileController extends Controller
{
    public function show(Request $request, CmsEmployeeSyncService $cmsSync): Response
    {
        $employee = $this->currentEmployee($request);

        if ($employee !== null) {
            $employee = $cmsSync->refreshEmployeeIfLinked($employee);
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

        // Partial-safe: only touch fields the request actually carries, so the
        // identity editor and the skill-matrix editor never clobber each other.
        $attributes = [];
        $meta = is_array($employee->meta) ? $employee->meta : [];
        $touchMeta = false;

        if ($request->has('phone')) {
            $attributes['phone'] = $data['phone'] ?? null;
        }
        if ($request->has('role_title')) {
            $attributes['role_title'] = $data['role_title'] ?? null;
        }
        if ($request->has('bio')) {
            $meta['bio'] = $data['bio'] ?? null;
            $touchMeta = true;
        }
        if ($request->has('location')) {
            $meta['location'] = $data['location'] ?? null;
            $touchMeta = true;
        }
        if ($request->hasAny(['github', 'linkedin', 'portfolio', 'website'])) {
            $meta['socials'] = array_filter([
                'github' => $data['github'] ?? null,
                'linkedin' => $data['linkedin'] ?? null,
                'portfolio' => $data['portfolio'] ?? null,
                'website' => $data['website'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');
            $touchMeta = true;
        }
        if ($request->has('skills')) {
            [$skillNames, $meta['skill_details']] = $this->normalizeSkills(array_values($data['skills'] ?? []));
            $attributes['skills'] = $skillNames;
            $touchMeta = true;
        }

        if ($touchMeta) {
            $attributes['meta'] = $meta;
        }

        if ($request->hasFile('avatar')) {
            $old = $employee->avatar_path;
            $attributes['avatar_path'] = $request->file('avatar')->store('avatars', 'public');

            if (is_string($old) && str_starts_with($old, 'avatars/')) {
                Storage::disk('public')->delete($old);
            }
        }

        if ($attributes !== []) {
            DB::transaction(fn () => $employee->update($attributes));
        }

        return back()->with('success', 'Đã cập nhật hồ sơ.');
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
            $name = trim((string) ($s['name'] ?? ''));
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
