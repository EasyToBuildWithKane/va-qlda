<?php

namespace App\Support\DailyReport;

use App\Models\DailyReport\DailyReportScoringConfig;
use App\Models\Employee;
use App\Support\Profile\ProfileOrgRelations;

/**
 * Resolves the scoring rubric (weights + kaizen bonus) for an employee/department.
 * Falls back to config('daily_report') when no active Workspace config exists.
 */
final class DailyReportScoringResolver
{
    public const SOURCE_DEPARTMENT = 'department';

    public const SOURCE_SYSTEM = 'system';

    /**
     * @return array{
     *     weights: array{task_completion: float, skill_score: float, attitude_score: float, expertise_score: float},
     *     kaizen_bonus_max: float,
     *     source: string,
     *     department_code: string|null,
     *     department_name: string|null,
     *     config_id: int|null
     * }
     */
    public function forEmployee(?Employee $employee): array
    {
        $code = $this->departmentCodeFor($employee);
        if ($code === null) {
            return $this->systemDefault(null, null);
        }

        $name = $this->departmentNameFor($employee, $code);

        return $this->forDepartmentCode($code, $name);
    }

    /**
     * @return array{
     *     weights: array{task_completion: float, skill_score: float, attitude_score: float, expertise_score: float},
     *     kaizen_bonus_max: float,
     *     source: string,
     *     department_code: string|null,
     *     department_name: string|null,
     *     config_id: int|null
     * }
     */
    public function forDepartmentCode(?string $departmentCode, ?string $departmentName = null): array
    {
        $code = trim((string) $departmentCode);
        if ($code === '') {
            return $this->systemDefault(null, null);
        }

        $config = DailyReportScoringConfig::query()
            ->active()
            ->forDepartment($code)
            ->first();

        if ($config === null) {
            return $this->systemDefault($code, $departmentName);
        }

        return [
            'weights' => $this->normalizeWeights(is_array($config->weights) ? $config->weights : []),
            'kaizen_bonus_max' => (float) $config->kaizen_bonus_max,
            'source' => self::SOURCE_DEPARTMENT,
            'department_code' => $config->department_code,
            'department_name' => $config->department_name ?? $departmentName,
            'config_id' => (int) $config->id,
        ];
    }

    /**
     * Snapshot stored on DailyReportScore at review time.
     *
     * @param  array<string, mixed>  $rubric
     * @return array{weights: array<string, float>, kaizen_bonus_max: float, source: string, department_code: string|null}
     */
    public function toSnapshot(array $rubric): array
    {
        return [
            'weights' => $this->normalizeWeights(is_array($rubric['weights'] ?? null) ? $rubric['weights'] : []),
            'kaizen_bonus_max' => (float) ($rubric['kaizen_bonus_max'] ?? config('daily_report.kaizen_bonus_max', 2.0)),
            'source' => (string) ($rubric['source'] ?? self::SOURCE_SYSTEM),
            'department_code' => isset($rubric['department_code']) ? (string) $rubric['department_code'] : null,
        ];
    }

    /**
     * Default weights from config/daily_report.php (for Workspace «restore» UI).
     *
     * @return array{
     *     weights: array{task_completion: float, skill_score: float, attitude_score: float, expertise_score: float},
     *     kaizen_bonus_max: float
     * }
     */
    public function systemDefaultsPayload(): array
    {
        $system = $this->systemDefault(null, null);

        return [
            'weights' => $system['weights'],
            'kaizen_bonus_max' => $system['kaizen_bonus_max'],
        ];
    }

    public function departmentCodeFor(?Employee $employee): ?string
    {
        if ($employee === null) {
            return null;
        }

        $meta = is_array($employee->meta) ? $employee->meta : [];
        $fromMeta = trim((string) ($meta['department_code'] ?? ''));
        if ($fromMeta !== '') {
            return $fromMeta;
        }

        $resolved = ProfileOrgRelations::departmentCode($meta);

        return filled($resolved) ? (string) $resolved : null;
    }

    private function departmentNameFor(?Employee $employee, string $code): ?string
    {
        if ($employee === null) {
            return null;
        }

        $meta = is_array($employee->meta) ? $employee->meta : [];
        $name = trim((string) ($meta['department_name'] ?? $meta['department'] ?? ''));

        return $name !== '' ? $name : $code;
    }

    /**
     * @return array{
     *     weights: array{task_completion: float, skill_score: float, attitude_score: float, expertise_score: float},
     *     kaizen_bonus_max: float,
     *     source: string,
     *     department_code: string|null,
     *     department_name: string|null,
     *     config_id: int|null
     * }
     */
    private function systemDefault(?string $departmentCode, ?string $departmentName): array
    {
        $weights = config('daily_report.weights', []);

        return [
            'weights' => $this->normalizeWeights(is_array($weights) ? $weights : []),
            'kaizen_bonus_max' => (float) config('daily_report.kaizen_bonus_max', 2.0),
            'source' => self::SOURCE_SYSTEM,
            'department_code' => $departmentCode,
            'department_name' => $departmentName,
            'config_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $weights
     * @return array{task_completion: float, skill_score: float, attitude_score: float, expertise_score: float}
     */
    private function normalizeWeights(array $weights): array
    {
        $defaults = [
            'task_completion' => 0.30,
            'skill_score' => 0.20,
            'attitude_score' => 0.15,
            'expertise_score' => 0.20,
        ];

        $out = [];
        foreach (DailyReportScoringConfig::WEIGHT_KEYS as $key) {
            $value = (float) ($weights[$key] ?? $defaults[$key]);
            $out[$key] = $value > 0 ? $value : $defaults[$key];
        }

        return $out;
    }
}
