<?php

namespace App\Policies;

use App\Models\CoachingCourse;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;

class CoachingCoursePolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return in_array($account->role, [SystemRole::Admin, SystemRole::Lead, SystemRole::Member], true);
    }

    public function view(SystemAccount $account, CoachingCourse $course): bool
    {
        if ($account->allows('coaching.view')) {
            return true;
        }

        return $account->employee_id !== null
            && $account->employee_id === $course->student_id;
    }

    public function create(SystemAccount $account): bool
    {
        return $account->allows('coaching.create');
    }

    public function update(SystemAccount $account, CoachingCourse $course): bool
    {
        if (! $account->allows('coaching.update')) {
            return false;
        }

        // Admin tier edits any course; a finer grant is scoped to the coach
        // (or unassigned leads) — mirrors the previous lead-coach rule.
        return $account->isAdminTier()
            || $account->employee_id === null
            || $account->employee_id === $course->coach_id;
    }

    public function delete(SystemAccount $account, CoachingCourse $course): bool
    {
        return $account->allows('coaching.delete');
    }

    public function exportReport(SystemAccount $account): bool
    {
        return $account->allows('coaching.export');
    }
}
