<?php

namespace App\Services\Auth;

use App\Models\Employee;
use App\Models\SystemAccount;
use App\Services\Cms\SystemAccountProvisioner;
use App\Support\Enums\SystemRole;

/**
 * Applies config/va_permissions.bootstrap_accounts to existing CMS-linked employees.
 */
final class BootstrapAdminRoleService
{
    public function resolveRoleForEmail(?string $email): ?SystemRole
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        $key = strtolower(trim($email));

        /** @var array<string, string> $map */
        $map = config('va_permissions.bootstrap_accounts', []);

        $roleValue = $map[$key] ?? null;
        if ($roleValue === null) {
            return null;
        }

        return SystemRole::tryFrom($roleValue);
    }

    /**
     * @return array{updated:int, created:int, missing_employee:int, skipped:int}
     */
    public function applyBootstrapRoles(bool $createMissingAccounts = true): array
    {
        /** @var array<string, string> $map */
        $map = config('va_permissions.bootstrap_accounts', []);

        $stats = ['updated' => 0, 'created' => 0, 'missing_employee' => 0, 'skipped' => 0];

        $provisioner = app(SystemAccountProvisioner::class);

        foreach ($map as $email => $roleValue) {
            $email = strtolower(trim($email));
            $role = SystemRole::tryFrom($roleValue);

            if ($role === null) {
                $stats['skipped']++;

                continue;
            }

            $employee = Employee::query()->where('email', $email)->first();

            if ($employee === null) {
                $stats['missing_employee']++;

                continue;
            }

            $account = SystemAccount::query()->where('employee_id', $employee->id)->first();

            if ($account === null) {
                if (! $createMissingAccounts) {
                    $stats['missing_employee']++;

                    continue;
                }

                $account = $provisioner->ensureForEmployee($employee, $role);
                $stats['created']++;

                continue;
            }

            if ($account->role !== $role || ! $account->is_active) {
                $account->forceFill([
                    'role' => $role,
                    'is_active' => $employee->is_active,
                    'display_name' => $employee->full_name,
                ])->save();
                $stats['updated']++;
            } else {
                $stats['skipped']++;
            }
        }

        return $stats;
    }
}
