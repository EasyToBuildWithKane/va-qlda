<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\EmployeeProfileResource;
use App\Models\Employee;
use App\Support\Profile\ProfileSnapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'orgMemberships.section:id,name',
            'projects',
        ]);

        return Inertia::render('Profile/Show', [
            'profile' => (new EmployeeProfileResource($employee))->toArray($request),
            'editable' => true,
            'canViewPerformance' => true,
            'stats' => ProfileSnapshot::stats($employee),
            'projectExperience' => ProfileSnapshot::projectExperience($employee),
            'activity' => ProfileSnapshot::activity($employee),
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

        $attributes = [
            'phone' => $data['phone'] ?? null,
            'role_title' => $data['role_title'] ?? null,
            'skills' => array_values($data['skills'] ?? []),
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

        $employee->update($attributes);

        return back()->with('success', 'Đã cập nhật hồ sơ.');
    }

    private function currentEmployee(Request $request): ?Employee
    {
        $id = $request->user()?->employee_id;

        return $id ? Employee::find($id) : null;
    }
}
