<?php

namespace App\Support\Evaluation;

use App\Models\Employee;
use App\Services\Hrm\HrmApiClient;
use App\Services\Hrm\HrmIdentityResolver;
use App\Support\PublicMediaUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Danh sách nhân sự cho phiếu đánh giá.
 * Ưu tiên đồng bộ từ HRM Public API `GET /employees` (ability employee:read),
 * rồi trả options từ `employees` local (đã upsert).
 */
final class HrmEmployeeDirectory
{
    public const CACHE_KEY = 'evaluation.employee_directory.v1';

    public const CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly HrmApiClient $hrmApi,
        private readonly HrmIdentityResolver $identity,
    ) {}

    /**
     * @return list<array{id: int, name: string, email: string|null, code: string|null, department_code: string|null, department_name: string|null, avatar_path: string|null}>
     */
    public function options(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            $this->syncFromHrm();

            return true;
        });

        return $this->localOptions();
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function syncFromHrm(): void
    {
        if (! $this->hrmApi->isConfigured()) {
            Log::info('evaluation.employee_directory.hrm_api_skipped');

            return;
        }

        try {
            $rows = $this->hrmApi->listEmployees([
                'status' => 'active',
                'per_page' => 100,
            ]);
        } catch (\Throwable $e) {
            Log::warning('evaluation.employee_directory.hrm_api_failed', [
                'message' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['status'] ?? null) !== null && ($row['status'] ?? null) !== 'active') {
                continue;
            }
            try {
                $this->identity->upsertFromApiEmployee($row);
            } catch (\Throwable $e) {
                Log::info('evaluation.employee_directory.upsert_skip', [
                    'uuid' => $row['uuid'] ?? null,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return list<array{id: int, name: string, email: string|null, code: string|null, department_code: string|null, department_name: string|null, avatar_path: string|null}>
     */
    private function localOptions(): array
    {
        return Employee::query()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'email', 'code', 'avatar_path', 'meta'])
            ->map(function (Employee $e) {
                $meta = is_array($e->meta) ? $e->meta : [];

                return [
                    'id' => $e->id,
                    'name' => $e->full_name,
                    'email' => $e->email,
                    'code' => $e->code,
                    'department_code' => $meta['department_code'] ?? null,
                    'department_name' => $meta['department_name'] ?? null,
                    'avatar_path' => PublicMediaUrl::fromPublicDisk($e->avatar_path),
                ];
            })
            ->values()
            ->all();
    }
}
