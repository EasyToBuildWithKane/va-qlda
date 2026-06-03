<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsUser;
use App\Models\Employee;
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
     * @return array{created:int, updated:int, skipped:int, errors:int}
     */
    public function syncAll(bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

        CmsUser::query()
            ->withTrashed()
            ->with('info')
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($dryRun, &$stats) {
                foreach ($users as $cmsUser) {
                    try {
                        $result = $this->upsertFromCmsUser($cmsUser, $dryRun);
                        $stats[$result]++;
                    } catch (\Throwable) {
                        $stats['errors']++;
                    }
                }
            });

        if (! $dryRun) {
            Cache::forget('options.employees');
        }

        return $stats;
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
