<?php

namespace App\Services\Auth;

use App\Models\Employee;
use App\Models\SystemAccount;
use App\Services\Cms\SystemAccountProvisioner;
use App\Support\Enums\SystemRole;
use Illuminate\Support\Str;

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

        if (isset($map[$key])) {
            return SystemRole::tryFrom($map[$key]);
        }

        /** @var array<string, array<int, string>> $aliases */
        $aliases = config('va_permissions.bootstrap_email_aliases', []);

        foreach ($aliases as $bootstrapEmail => $alts) {
            $bootstrapEmail = strtolower(trim($bootstrapEmail));
            $normalizedAlts = array_map(fn ($a) => strtolower(trim((string) $a)), $alts);

            if ($key === $bootstrapEmail || in_array($key, $normalizedAlts, true)) {
                return SystemRole::tryFrom($map[$bootstrapEmail] ?? '');
            }
        }

        $local = Str::before($key, '@');
        foreach (array_keys($map) as $bootstrapEmail) {
            if (Str::before(strtolower($bootstrapEmail), '@') === $local) {
                return SystemRole::tryFrom($map[$bootstrapEmail]);
            }
        }

        return null;
    }

    /**
     * @return array{
     *     updated:int,
     *     created:int,
     *     missing_employee:int,
     *     skipped:int,
     *     missing_emails: array<int, string>,
     *     hints: array<int, string>
     * }
     */
    public function applyBootstrapRoles(bool $createMissingAccounts = true): array
    {
        /** @var array<string, string> $map */
        $map = config('va_permissions.bootstrap_accounts', []);

        $stats = [
            'updated' => 0,
            'created' => 0,
            'missing_employee' => 0,
            'skipped' => 0,
            'missing_emails' => [],
            'hints' => [],
        ];

        $provisioner = app(SystemAccountProvisioner::class);

        foreach ($map as $email => $roleValue) {
            $email = strtolower(trim($email));
            $role = SystemRole::tryFrom($roleValue);

            if ($role === null) {
                $stats['skipped']++;

                continue;
            }

            $employee = $this->findEmployeeForBootstrapEmail($email);

            if ($employee === null) {
                $stats['missing_employee']++;
                $stats['missing_emails'][] = $email;
                $hint = $this->suggestEmployeeEmail($email);
                if ($hint !== null) {
                    $stats['hints'][] = "{$email} → thử: {$hint}";
                }

                continue;
            }

            $account = SystemAccount::query()->where('employee_id', $employee->id)->first();

            if ($account === null) {
                if (! $createMissingAccounts) {
                    $stats['missing_employee']++;
                    $stats['missing_emails'][] = $email;

                    continue;
                }

                $provisioner->ensureForEmployee($employee, $role);
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

    public function findEmployeeForBootstrapEmail(string $configuredEmail): ?Employee
    {
        $configuredEmail = strtolower(trim($configuredEmail));

        $employee = Employee::query()->where('email', $configuredEmail)->first();
        if ($employee !== null) {
            return $employee;
        }

        /** @var array<string, array<int, string>> $aliases */
        $aliases = config('va_permissions.bootstrap_email_aliases', []);

        foreach ($aliases[$configuredEmail] ?? [] as $alt) {
            $employee = Employee::query()->where('email', strtolower(trim((string) $alt)))->first();
            if ($employee !== null) {
                return $employee;
            }
        }

        $local = Str::before($configuredEmail, '@');
        if ($local === '') {
            return null;
        }

        $candidates = Employee::query()
            ->whereNotNull('cms_user_id')
            ->where(function ($q) use ($local) {
                $q->where('email', 'like', $local.'@%');
            })
            ->get();

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        return null;
    }

    private function suggestEmployeeEmail(string $configuredEmail): ?string
    {
        $local = Str::before(strtolower(trim($configuredEmail)), '@');
        if ($local === '') {
            return null;
        }

        $matches = Employee::query()
            ->whereNotNull('cms_user_id')
            ->where('email', 'like', $local.'@%')
            ->limit(3)
            ->pluck('email');

        if ($matches->isEmpty()) {
            return null;
        }

        return $matches->implode(', ');
    }
}
