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
        if (in_array($account->role, [SystemRole::Admin, SystemRole::Lead], true)) {
            return true;
        }

        return $account->employee_id !== null
            && $account->employee_id === $course->student_id;
    }

    public function create(SystemAccount $account): bool
    {
        return in_array($account->role, [SystemRole::Admin, SystemRole::Lead], true);
    }

    public function update(SystemAccount $account, CoachingCourse $course): bool
    {
        if ($account->role === SystemRole::Admin) {
            return true;
        }

        if ($account->role === SystemRole::Lead) {
            return $account->employee_id === null
                || $account->employee_id === $course->coach_id;
        }

        return false;
    }

    public function delete(SystemAccount $account, CoachingCourse $course): bool
    {
        return $account->role === SystemRole::Admin;
    }

    public function exportReport(SystemAccount $account): bool
    {
        return in_array($account->role, [SystemRole::Admin, SystemRole::Lead], true);
    }
}
