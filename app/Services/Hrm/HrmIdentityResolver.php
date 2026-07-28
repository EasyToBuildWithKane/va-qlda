<?php

namespace App\Services\Hrm;

use App\Models\Employee;
use App\Models\Hrm\HrmUser;
use App\Models\SystemAccount;
use App\Support\Hrm\HrmEmployeeMapper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Danh tính từ HRM (`va_hrm` — SSOT user, connection `hrm_mysql`, read-only).
 *
 * Không microservices: cùng process Laravel mở PDO MySQL thứ hai tới DB `va_hrm`.
 * Không bulk sync: tra cứu theo email lúc Google login và lazy upsert từng
 * nhân sự vào `va_prd_employees` (link qua `hrm_user_id`).
 */
final class HrmIdentityResolver
{
    public function isHrmConfigured(): bool
    {
        $connection = config('database.connections.hrm_mysql');

        return filled($connection['database'] ?? null)
            && filled($connection['username'] ?? null);
    }

    /**
     * Tìm user HRM đang hoạt động (không soft-deleted) theo email.
     */
    public function findActiveHrmUserByEmail(string $email): ?HrmUser
    {
        $email = strtolower(trim($email));

        if ($email === '' || ! $this->isHrmConfigured()) {
            return null;
        }

        return HrmUser::query()
            ->with('info')
            ->where('email', $email)
            ->first();
    }

    /**
     * Upsert một nhân sự QLDA từ user HRM và trả về Employee đã liên kết.
     */
    public function ensureEmployeeFromHrm(HrmUser $hrmUser): Employee
    {
        $this->upsertFromHrmUser($hrmUser);

        return Employee::query()
            ->where('hrm_user_id', $hrmUser->id)
            ->firstOrFail();
    }

    /**
     * Refresh QLDA employee từ HRM khi đã liên kết (vd. sau Google login / mở hồ sơ).
     */
    public function refreshEmployeeIfLinked(Employee $employee): Employee
    {
        if ($employee->hrm_user_id === null || ! $this->isHrmConfigured()) {
            return $employee;
        }

        $hrmUser = HrmUser::query()
            ->withTrashed()
            ->with('info')
            ->find($employee->hrm_user_id);

        if ($hrmUser === null) {
            return $employee;
        }

        $this->upsertFromHrmUser($hrmUser);

        return $employee->fresh() ?? $employee;
    }

    /**
     * @return 'created'|'updated'|'skipped'
     */
    public function upsertFromHrmUser(HrmUser $hrmUser): string
    {
        $info = $hrmUser->relationLoaded('info') ? $hrmUser->info : null;
        $attributes = HrmEmployeeMapper::toEmployeeAttributes($hrmUser, $info);

        if ($attributes['email'] === null) {
            return 'skipped';
        }

        $employee = Employee::query()
            ->where('hrm_user_id', $hrmUser->id)
            ->first();

        if ($employee === null) {
            $employee = Employee::query()
                ->whereNull('hrm_user_id')
                ->where('email', $attributes['email'])
                ->first();
        }

        if ($employee === null) {
            DB::transaction(function () use ($attributes) {
                Employee::query()->create($attributes);
            });

            $created = Employee::query()->where('hrm_user_id', $hrmUser->id)->first();
            if ($created !== null) {
                $this->syncLoginActiveFromEmployee($created);
            }

            Cache::forget('options.employees');

            return 'created';
        }

        $payload = $attributes;
        if ($employee->code !== null && $employee->code !== '' && $employee->hrm_user_id === null) {
            unset($payload['code']);
        }

        if ($this->employeeMatchesPayload($employee, $payload)) {
            return 'skipped';
        }

        DB::transaction(function () use ($employee, $payload) {
            $employee->fill($payload);
            $employee->save();
        });

        $this->syncLoginActiveFromEmployee($employee->fresh());

        Cache::forget('options.employees');

        return 'updated';
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
