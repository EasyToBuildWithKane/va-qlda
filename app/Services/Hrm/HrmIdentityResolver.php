<?php

namespace App\Services\Hrm;

use App\Models\Employee;
use App\Models\SystemAccount;
use App\Support\Hrm\HrmApiEmployeeMapper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Danh tính HRM → va_prd_employees — chỉ qua Public API v1 (M2M).
 *
 * Không đọc hrm_mysql / va_hrm_users. JWT SSO ≠ Bearer HRM_API_TOKEN.
 */
final class HrmIdentityResolver
{
    private HrmApiClient $api;

    public function __construct(?HrmApiClient $api = null)
    {
        $this->api = $api ?? new HrmApiClient;
    }

    public function isHrmConfigured(): bool
    {
        return $this->isApiConfigured();
    }

    public function isApiConfigured(): bool
    {
        return $this->api->isConfigured();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        return $this->safeApiFindActiveByEmail($email);
    }

    /**
     * Upsert nhân sự QLDA từ payload API và trả về Employee đã liên kết.
     *
     * @param  array<string, mixed>  $payload
     */
    public function ensureEmployeeFromApi(array $payload): Employee
    {
        $this->upsertFromApiEmployee($payload);

        $uuid = is_string($payload['uuid'] ?? null) ? $payload['uuid'] : null;
        if ($uuid !== null && $uuid !== '') {
            return Employee::query()
                ->where('hrm_employee_uuid', $uuid)
                ->firstOrFail();
        }

        $email = strtolower(trim((string) (
            $payload['company_email'] ?? $payload['personal_email'] ?? ''
        )));

        return Employee::query()
            ->where('email', $email)
            ->firstOrFail();
    }

    /**
     * Tra API theo email → lazy upsert. Null khi thiếu cấu hình API hoặc không có nhân sự active.
     */
    public function ensureEmployeeByEmail(string $email): ?Employee
    {
        $payload = $this->findActiveByEmail($email);
        if ($payload === null) {
            return null;
        }

        return $this->ensureEmployeeFromApi($payload);
    }

    /**
     * Refresh QLDA employee từ HRM API khi đã liên kết (login / hồ sơ).
     * API miss hoặc lỗi → giữ nguyên bản ghi QLDA (không fallback DB).
     */
    public function refreshEmployeeIfLinked(Employee $employee): Employee
    {
        $refreshed = $this->refreshFromApi($employee);

        return $refreshed ?? $employee;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return 'created'|'updated'|'skipped'
     */
    public function upsertFromApiEmployee(array $payload): string
    {
        $attributes = HrmApiEmployeeMapper::toEmployeeAttributes($payload);

        return $this->upsertEmployeeAttributes(
            $attributes,
            preferHrmUserId: isset($attributes['hrm_user_id']) ? (int) $attributes['hrm_user_id'] : null,
            preferUuid: is_string($attributes['hrm_employee_uuid'] ?? null)
                ? $attributes['hrm_employee_uuid']
                : null,
        );
    }

    private function refreshFromApi(Employee $employee): ?Employee
    {
        if (! $this->isApiConfigured()) {
            return null;
        }

        $payload = null;
        if (filled($employee->hrm_employee_uuid)) {
            $payload = $this->safeApiFindByUuid((string) $employee->hrm_employee_uuid);
        }

        if ($payload === null && $employee->hrm_user_id !== null) {
            $payload = $this->safeApiFindByLegacyUserId((int) $employee->hrm_user_id);
        }

        if ($payload === null && filled($employee->email)) {
            $payload = $this->safeApiFindActiveByEmail((string) $employee->email);
        }

        if ($payload === null) {
            return null;
        }

        $this->upsertFromApiEmployee($payload);

        return $employee->fresh() ?? $employee;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return 'created'|'updated'|'skipped'
     */
    private function upsertEmployeeAttributes(
        array $attributes,
        ?int $preferHrmUserId = null,
        ?string $preferUuid = null,
    ): string {
        if (($attributes['email'] ?? null) === null) {
            return 'skipped';
        }

        $employee = null;

        if ($preferUuid !== null && $preferUuid !== '') {
            $employee = Employee::query()->where('hrm_employee_uuid', $preferUuid)->first();
        }

        if ($employee === null && $preferHrmUserId !== null && $preferHrmUserId > 0) {
            $employee = Employee::query()->where('hrm_user_id', $preferHrmUserId)->first();
        }

        if ($employee === null) {
            $employee = Employee::query()
                ->whereNull('hrm_user_id')
                ->whereNull('hrm_employee_uuid')
                ->where('email', $attributes['email'])
                ->first();
        }

        if ($employee === null && filled($attributes['email'])) {
            $employee = Employee::query()
                ->where('email', $attributes['email'])
                ->first();
        }

        if ($employee === null) {
            DB::transaction(function () use ($attributes) {
                Employee::query()->create($attributes);
            });

            $created = null;
            if ($preferUuid) {
                $created = Employee::query()->where('hrm_employee_uuid', $preferUuid)->first();
            }
            if ($created === null && $preferHrmUserId) {
                $created = Employee::query()->where('hrm_user_id', $preferHrmUserId)->first();
            }
            if ($created === null) {
                $created = Employee::query()->where('email', $attributes['email'])->first();
            }

            if ($created !== null) {
                $this->syncLoginActiveFromEmployee($created);
            }

            Cache::forget('options.employees');

            return 'created';
        }

        $payload = $attributes;
        if ($employee->code !== null && $employee->code !== ''
            && $employee->hrm_user_id === null
            && $employee->hrm_employee_uuid === null) {
            unset($payload['code']);
        }

        // Không ghi đè uuid/hrm_user_id bằng null từ payload thiếu field.
        foreach (['hrm_employee_uuid', 'hrm_user_id'] as $linkKey) {
            if (! array_key_exists($linkKey, $payload) || $payload[$linkKey] === null) {
                unset($payload[$linkKey]);
            }
        }

        // Meta HRM merge vào meta QLDA (giữ bio / socials / skill_details…).
        if (array_key_exists('meta', $payload)) {
            $payload['meta'] = self::mergeEmployeeMeta(
                is_array($employee->meta) ? $employee->meta : [],
                is_array($payload['meta']) ? $payload['meta'] : null,
            );
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

    /**
     * @return array<string, mixed>|null
     */
    private function safeApiFindActiveByEmail(string $email): ?array
    {
        if (! $this->isApiConfigured()) {
            return null;
        }

        try {
            return $this->api->findActiveByEmail($email);
        } catch (\Throwable $e) {
            Log::warning('hrm.api.find_by_email_failed', [
                'email' => $email,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function safeApiFindByLegacyUserId(int $id): ?array
    {
        if (! $this->isApiConfigured() || $id < 1) {
            return null;
        }

        try {
            return $this->api->findByLegacyUserId($id);
        } catch (\Throwable $e) {
            Log::warning('hrm.api.find_by_legacy_failed', [
                'legacy_user_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function safeApiFindByUuid(string $uuid): ?array
    {
        if (! $this->isApiConfigured() || $uuid === '') {
            return null;
        }

        try {
            return $this->api->findByUuid($uuid);
        } catch (\Throwable $e) {
            Log::warning('hrm.api.find_by_uuid_failed', [
                'uuid' => $uuid,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
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
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>|null  $incoming
     * @return array<string, mixed>|null
     */
    private static function mergeEmployeeMeta(array $existing, ?array $incoming): ?array
    {
        if ($incoming === null || $incoming === []) {
            return $existing === [] ? null : $existing;
        }

        $merged = array_merge($existing, $incoming);

        return $merged === [] ? null : $merged;
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
