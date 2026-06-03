<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsUser;
use App\Models\Employee;
use App\Models\SystemAccount;
use App\Support\Cms\CmsEmployeeMapper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class CmsEmployeeSyncService
{
    public function isCmsConfigured(): bool
    {
        $connection = config('database.connections.cms_mysql');

        return filled($connection['database'] ?? null)
            && filled($connection['username'] ?? null);
    }

    /**
     * @return array{created:int, updated:int, skipped:int, errors:int, accounts:int}
     */
    public function syncAll(bool $dryRun = false, bool $provisionAccounts = true, ?callable $onSyncProgress = null): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'accounts' => 0];

        $total = $this->countCmsUsers();
        $processed = 0;

        if ($onSyncProgress !== null) {
            $onSyncProgress(0, $total);
        }

        CmsUser::query()
            ->withTrashed()
            ->with('info')
            ->orderBy('id')
            ->chunkById(50, function ($users) use ($dryRun, &$stats, &$processed, $total, $onSyncProgress) {
                foreach ($users as $cmsUser) {
                    $processed++;

                    try {
                        $result = $this->upsertFromCmsUser($cmsUser, $dryRun);
                        $stats[$result]++;
                    } catch (\Throwable) {
                        $stats['errors']++;
                    }

                    if ($onSyncProgress !== null) {
                        $onSyncProgress($processed, $total);
                    }
                }
            });

        if ($provisionAccounts) {
            $stats['accounts'] = $this->provisionMissingLoginAccounts($dryRun);
        }

        if (! $dryRun) {
            Cache::forget('options.employees');
        }

        return $stats;
    }

    /**
     * @param  callable(int $processed, int $total): void|null  $onProgress
     */
    public function provisionMissingLoginAccounts(bool $dryRun = false, ?callable $onProgress = null): int
    {
        $provisioner = app(SystemAccountProvisioner::class);
        $created = 0;

        $query = Employee::query()
            ->whereNotNull('cms_user_id')
            ->where('is_active', true)
            ->whereDoesntHave('account');

        $total = (clone $query)->count();

        if ($onProgress !== null) {
            $onProgress(0, $total);
        }

        $processed = 0;

        $query->orderBy('id')->chunkById(50, function ($employees) use (
            $dryRun,
            $provisioner,
            &$created,
            &$processed,
            $total,
            $onProgress,
        ) {
            foreach ($employees as $employee) {
                $processed++;

                if ($dryRun) {
                    $created++;
                } else {
                    $provisioner->ensureForEmployee($employee);
                    $created++;
                }

                if ($onProgress !== null) {
                    $onProgress($processed, $total);
                }
            }
        });

        return $created;
    }

    private function syncLoginActiveFromEmployee(?Employee $employee): void
    {
        if ($employee === null) {
            return;
        }

        SystemAccount::query()
            ->where('employee_id', $employee->id)
            ->update(['is_active' => $employee->is_active]);
    }

    /**
     * @return array{cms_users:int, employees_linked:int, employees_active:int, missing_login:int, with_login:int}
     */
    public function qldaLinkStats(): array
    {
        $linked = Employee::query()->whereNotNull('cms_user_id')->count();
        $active = Employee::query()->whereNotNull('cms_user_id')->where('is_active', true)->count();
        $missing = Employee::query()
            ->whereNotNull('cms_user_id')
            ->where('is_active', true)
            ->whereDoesntHave('account')
            ->count();

        return [
            'cms_users' => 0,
            'employees_linked' => $linked,
            'employees_active' => $active,
            'missing_login' => $missing,
            'with_login' => max(0, $active - $missing),
        ];
    }

    public function countCmsUsers(): int
    {
        return CmsUser::query()->withTrashed()->count();
    }

    /**
     * Refresh QLDA employee row from CMS when linked (e.g. after Google login).
     */
    public function refreshEmployeeIfLinked(Employee $employee): Employee
    {
        if ($employee->cms_user_id === null || ! $this->isCmsConfigured()) {
            return $employee;
        }

        $cmsUser = CmsUser::query()
            ->withTrashed()
            ->with('info')
            ->find($employee->cms_user_id);

        if ($cmsUser === null) {
            return $employee;
        }

        $this->upsertFromCmsUser($cmsUser);

        return $employee->fresh() ?? $employee;
    }

    /**
     * @return 'created'|'updated'|'skipped'
     */
    public function upsertFromCmsUser(CmsUser $cmsUser, bool $dryRun = false): string
    {
        $info = $cmsUser->relationLoaded('info') ? $cmsUser->info : null;
        $attributes = CmsEmployeeMapper::toEmployeeAttributes($cmsUser, $info);

        if ($attributes['email'] === null) {
            return 'skipped';
        }

        $employee = Employee::query()
            ->where('cms_user_id', $cmsUser->id)
            ->first();

        if ($employee === null) {
            $employee = Employee::query()
                ->whereNull('cms_user_id')
                ->where('email', $attributes['email'])
                ->first();
        }

        if ($employee === null) {
            if ($dryRun) {
                return 'created';
            }

            DB::transaction(function () use ($attributes) {
                Employee::query()->create($attributes);
            });

            $created = Employee::query()->where('cms_user_id', $cmsUser->id)->first();
            if ($created !== null) {
                $this->syncLoginActiveFromEmployee($created);
            }

            return 'created';
        }

        $payload = $attributes;
        if ($employee->code !== null && $employee->code !== '' && $employee->cms_user_id === null) {
            unset($payload['code']);
        }

        if ($this->employeeMatchesPayload($employee, $payload)) {
            return 'skipped';
        }

        if ($dryRun) {
            return 'updated';
        }

        DB::transaction(function () use ($employee, $payload) {
            $employee->fill($payload);
            $employee->save();
        });

        $this->syncLoginActiveFromEmployee($employee->fresh());

        return 'updated';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function employeeMatchesPayload(Employee $employee, array $payload): bool
    {
        foreach ($payload as $key => $value) {
            $current = $employee->getAttribute($key);

            if ($key === 'meta') {
                if ($this->normalizeMeta($current) !== $this->normalizeMeta($value)) {
                    return false;
                }

                continue;
            }

            if ($key === 'join_date') {
                $currentDate = $current?->format('Y-m-d') ?? null;
                if ($currentDate !== $value) {
                    return false;
                }

                continue;
            }

            if ($current != $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>|null  $meta
     * @return array<string, mixed>|null
     */
    private function normalizeMeta(mixed $meta): ?array
    {
        if ($meta === null || $meta === []) {
            return null;
        }

        if (! is_array($meta)) {
            return null;
        }

        ksort($meta);

        return $meta;
    }
}
