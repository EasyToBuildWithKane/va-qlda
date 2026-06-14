<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\EmployeeProfileResource;
use App\Models\Certification;
use App\Models\Employee;
use App\Support\Enums\SystemRole;
use App\Support\Profile\ProfileSnapshot;
use App\Support\Profile\SkillCatalog;
use App\Support\Profile\TalentProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Hồ sơ của tôi" — the authenticated person's own profile, with self-service
 * editing. Reuses the same presentation as the member profile; performance is
 * always visible since you are viewing yourself.
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
                'canViewPerformance' => false,
                'stats' => null,
                'projectExperience' => null,
                'activity' => null,
            ]);
        }

        $employee->load([
            'account:id,employee_id,role',
            'orgMemberships.team:id,name,leader_id',
            'orgMemberships.team.leader:id,full_name,avatar_path,code,email,role_title',
            'orgMemberships.section:id,org_team_id,title',
            'projects',
            ...TalentProfile::EAGER,
        ]);

        // Succession (risk/retention) is a management view — visible on your own
        // profile only if you are a manager yourself.
        $canSuccession = $request->user()->hasRole(SystemRole::Admin, SystemRole::Lead);

        return Inertia::render('Profile/Show', [
            'profile' => (new EmployeeProfileResource($employee))->toArray($request),
            'editable' => true,
            'canViewPerformance' => true,
            'canViewSuccession' => $canSuccession,
            'stats' => ProfileSnapshot::stats($employee),
            'projectExperience' => ProfileSnapshot::projectExperience($employee),
            'activity' => ProfileSnapshot::activity($employee),
            ...TalentProfile::bundle($employee, true, $canSuccession),
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

        $attributes = [
            'phone' => $data['phone'] ?? null,
            'role_title' => $data['role_title'] ?? null,
            // Keep the quick JSON list (names) in sync for the directory preview.
            'skills' => array_values(array_filter(array_map(fn ($s) => $s['name'] ?? null, $skills))),
            'meta' => $meta,
        ];

        if ($request->hasFile('avatar')) {
            $old = $employee->avatar_path;
            $attributes['avatar_path'] = $request->file('avatar')->store('avatars', 'public');

            // Only clean up files we previously uploaded (avatars/*), never seeds.
            if (is_string($old) && str_starts_with($old, 'avatars/')) {
                Storage::disk('public')->delete($old);
            }
        }

        DB::transaction(function () use ($employee, $attributes, $skills, $data) {
            $employee->update($attributes);
            $this->syncSkills($employee, $skills);
            $this->syncCertifications($employee, array_values($data['certifications'] ?? []));
        });

        return back()->with('success', 'Đã cập nhật hồ sơ.');
    }

    /**
     * Replace the leveled skill matrix with the submitted set (self-managed list).
     *
     * @param  list<array<string, mixed>>  $skills
     */
    private function syncSkills(Employee $employee, array $skills): void
    {
        $employee->skillEntries()->delete();

        $rows = [];
        $order = 0;
        foreach ($skills as $s) {
            $name = trim((string) ($s['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $rows[] = [
                'name' => $name,
                'category' => $s['category'] ?? SkillCatalog::categoryFor($name),
                'level' => (int) ($s['level'] ?? 3),
                'years_experience' => isset($s['years']) && $s['years'] !== '' ? (float) $s['years'] : null,
                'sort_order' => $order++,
            ];
        }

        if ($rows !== []) {
            $employee->skillEntries()->createMany($rows);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $certs
     */
    private function syncCertifications(Employee $employee, array $certs): void
    {
        $employee->certifications()->delete();

        $rows = [];
        foreach ($certs as $c) {
            $name = trim((string) ($c['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $rows[] = new Certification([
                'name' => $name,
                'provider' => $c['provider'] ?? null,
                'credential_url' => $c['credential_url'] ?? null,
                'issued_at' => $c['issued_at'] ?? null,
                'expires_at' => $c['expires_at'] ?? null,
            ]);
        }

        if ($rows !== []) {
            $employee->certifications()->saveMany($rows);
        }
    }

    private function currentEmployee(Request $request): ?Employee
    {
        $id = $request->user()?->employee_id;

        return $id ? Employee::find($id) : null;
    }
}
