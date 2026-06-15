<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\EmployeeProfileResource;
use App\Models\Employee;
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
    public function show(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        if ($employee === null) {
            return Inertia::render('Profile/Show', [
                'profile' => null,
                'editable' => false,
            ]);
        }

        $employee->load([
            'account:id,employee_id,role',
            'orgMemberships.team:id,name,leader_id',
            'orgMemberships.team.leader:id,full_name,avatar_path,code,email,role_title',
            'orgMemberships.section:id,org_team_id,title',
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
        $meta['bio'] = $data['bio'] ?? null;
        $meta['location'] = $data['location'] ?? null;
        $meta['socials'] = array_filter([
            'github' => $data['github'] ?? null,
            'linkedin' => $data['linkedin'] ?? null,
            'portfolio' => $data['portfolio'] ?? null,
            'website' => $data['website'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $skills = array_values($data['skills'] ?? []);
        [$skillNames, $meta['skill_details']] = $this->normalizeSkills($skills);

        $attributes = [
            'phone' => $data['phone'] ?? null,
            'role_title' => $data['role_title'] ?? null,
            'skills' => $skillNames,
            'meta' => $meta,
        ];

        if ($request->hasFile('avatar')) {
            $old = $employee->avatar_path;
            $attributes['avatar_path'] = $request->file('avatar')->store('avatars', 'public');

            if (is_string($old) && str_starts_with($old, 'avatars/')) {
                Storage::disk('public')->delete($old);
            }
        }

        DB::transaction(fn () => $employee->update($attributes));

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
            $details[] = [
                'name' => $name,
                'level' => (int) ($s['level'] ?? 3),
                'category' => $s['category'] ?? SkillCatalog::categoryFor($name),
                'years' => isset($s['years']) && $s['years'] !== '' ? (float) $s['years'] : null,
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
