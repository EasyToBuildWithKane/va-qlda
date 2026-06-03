<?php

namespace App\Services\Cms;

use App\Models\Employee;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates QLDA login rows for employees synced from CMS (Google login path).
 */
final class SystemAccountProvisioner
{
    public function ensureForEmployee(
        Employee $employee,
        SystemRole $role = SystemRole::Member,
    ): SystemAccount {
        $existing = SystemAccount::query()
            ->where('employee_id', $employee->id)
            ->first();

        if ($existing !== null) {
            return $this->refreshExisting($existing, $employee, $role);
        }

        return DB::transaction(function () use ($employee, $role) {
            return SystemAccount::query()->create([
                'username' => $this->uniqueUsername($employee),
                'password' => Str::password(32),
                'display_name' => $employee->full_name,
                'role' => $role,
                'employee_id' => $employee->id,
                'is_active' => $employee->is_active,
            ]);
        });
    }

    private function refreshExisting(
        SystemAccount $account,
        Employee $employee,
        SystemRole $role,
    ): SystemAccount {
        $dirty = false;

        if ($account->display_name !== $employee->full_name) {
            $account->display_name = $employee->full_name;
            $dirty = true;
        }

        if ($account->is_active !== $employee->is_active) {
            $account->is_active = $employee->is_active;
            $dirty = true;
        }

        if ($dirty) {
            $account->save();
        }

        return $account;
    }

    private function uniqueUsername(Employee $employee): string
    {
        $email = strtolower(trim((string) $employee->email));
        if ($email !== '' && ! $this->usernameTaken($email, $employee->id)) {
            return $email;
        }

        $base = $email !== ''
            ? Str::before($email, '@')
            : 'cms-'.($employee->cms_user_id ?? $employee->id);

        $candidate = $base;
        $suffix = 0;

        while ($this->usernameTaken($candidate, $employee->id)) {
            $suffix++;
            $candidate = $base.'-'.$suffix;
        }

        return $candidate;
    }

    private function usernameTaken(string $username, int $employeeId): bool
    {
        return SystemAccount::query()
            ->where('username', $username)
            ->where('employee_id', '!=', $employeeId)
            ->exists();
    }
}
