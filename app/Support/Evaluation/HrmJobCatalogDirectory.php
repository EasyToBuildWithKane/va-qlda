<?php

namespace App\Support\Evaluation;

use App\Models\Employee;
use App\Services\Hrm\HrmApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Danh mục chức danh + cấp bậc cho mẫu đánh giá.
 * Ưu tiên HRM Public API; fallback distinct từ nhân sự đã sync.
 *
 * @phpstan-type CatalogRow array{code: string, name: string, source: string, hrm_uuid?: string|null}
 */
final class HrmJobCatalogDirectory
{
    public const CACHE_KEY_TITLES = 'evaluation.job_titles.v3';

    public const CACHE_KEY_RANKS = 'evaluation.job_ranks.v4';

    public const CACHE_TTL_SECONDS = 86400;

    public function __construct(
        private readonly HrmApiClient $hrmApi,
    ) {}

    /**
     * @return list<CatalogRow>
     */
    public function titles(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY_TITLES);
        }

        /** @var list<CatalogRow> */
        return Cache::remember(self::CACHE_KEY_TITLES, self::CACHE_TTL_SECONDS, fn () => $this->buildTitles());
    }

    /**
     * @return list<CatalogRow>
     */
    public function ranks(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY_RANKS);
        }

        /** @var list<CatalogRow> */
        return Cache::remember(self::CACHE_KEY_RANKS, self::CACHE_TTL_SECONDS, fn () => $this->buildRanks());
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY_TITLES);
        Cache::forget(self::CACHE_KEY_RANKS);
    }

    /**
     * @return CatalogRow|null
     */
    public function findTitleByCode(string $code): ?array
    {
        return $this->findIn($this->titles(), $code);
    }

    /**
     * @return CatalogRow|null
     */
    public function findRankByCode(string $code): ?array
    {
        return $this->findIn($this->ranks(), $code);
    }

    public static function codeFromName(string $prefix, string $name): string
    {
        $slug = Str::upper(Str::slug($name, '_'));
        if ($slug === '') {
            $slug = substr(md5($name), 0, 8);
        }

        return $prefix.'_'.$slug;
    }

    /**
     * @return list<CatalogRow>
     */
    private function buildTitles(): array
    {
        /** @var array<string, CatalogRow> $byCode */
        $byCode = [];

        if ($this->hrmApi->isConfigured()) {
            try {
                foreach ($this->hrmApi->listJobTitles() as $row) {
                    $mapped = $this->mapApiRow($row, 'TITLE', 'hrm');
                    if ($mapped !== null) {
                        $byCode[strtoupper($mapped['code'])] = $mapped;
                    }
                }
            } catch (\Throwable $e) {
                Log::info('evaluation.job_titles.hrm_failed', ['message' => $e->getMessage()]);
            }
        }

        $this->mergeEmployeeTitles($byCode);

        $list = array_values($byCode);
        usort($list, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $list;
    }

    /**
     * @return list<CatalogRow>
     */
    private function buildRanks(): array
    {
        /** @var array<string, CatalogRow> $byCode */
        $byCode = [];

        if ($this->hrmApi->isConfigured()) {
            try {
                foreach ($this->hrmApi->listRanks() as $row) {
                    $mapped = $this->mapApiRow($row, 'RANK', 'hrm');
                    if ($mapped !== null) {
                        $byCode[strtoupper($mapped['code'])] = $mapped;
                    }
                }
            } catch (\Throwable $e) {
                Log::info('evaluation.job_ranks.hrm_failed', ['message' => $e->getMessage()]);
            }

            // HRM chưa có endpoint ranks — suy cấp bậc từ field `level` trên chức danh.
            if ($byCode === []) {
                $this->mergeLevelsFromJobTitles($byCode);
            }
        }

        $this->mergeEmployeeRanks($byCode);

        $list = array_values($byCode);
        usort($list, function (array $a, array $b): int {
            $na = $this->levelNumberFromCode($a['code']);
            $nb = $this->levelNumberFromCode($b['code']);
            if ($na !== null && $nb !== null && $na !== $nb) {
                return $na <=> $nb;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return $list;
    }

    /**
     * Suy cấp bậc từ GET /job-titles khi API ranks trống.
     * Ưu tiên tên thật (rank_name / level_name / nested rank); fallback «Cấp N».
     *
     * @param  array<string, CatalogRow>  $byCode
     */
    private function mergeLevelsFromJobTitles(array &$byCode): void
    {
        try {
            foreach ($this->hrmApi->listJobTitles() as $row) {
                $named = $this->extractRankFromJobTitleRow($row);
                if ($named !== null) {
                    $key = strtoupper($named['code']);
                    if (! isset($byCode[$key])) {
                        $byCode[$key] = $named;
                    }

                    continue;
                }

                if (! is_numeric($row['level'] ?? null)) {
                    continue;
                }
                $level = (int) $row['level'];
                if ($level < 1) {
                    continue;
                }
                $code = 'L'.$level;
                $key = strtoupper($code);
                if (isset($byCode[$key])) {
                    continue;
                }
                $byCode[$key] = [
                    'code' => $code,
                    'name' => 'Cấp '.$level,
                    'source' => 'hrm_level',
                    'hrm_uuid' => null,
                ];
            }
        } catch (\Throwable $e) {
            Log::info('evaluation.job_ranks.level_derive_failed', ['message' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return CatalogRow|null
     */
    private function extractRankFromJobTitleRow(array $row): ?array
    {
        if (is_array($row['rank'] ?? null)) {
            $mapped = $this->mapApiRow($row['rank'], 'RANK', 'hrm_title_rank');
            if ($mapped !== null) {
                return $mapped;
            }
        }

        if (is_array($row['job_level'] ?? null)) {
            $mapped = $this->mapApiRow($row['job_level'], 'RANK', 'hrm_title_rank');
            if ($mapped !== null) {
                return $mapped;
            }
        }

        $candidates = [
            $row['rank_name'] ?? null,
            $row['level_name'] ?? null,
            $row['grade_name'] ?? null,
            is_string($row['grade'] ?? null) ? $row['grade'] : null,
            is_string($row['rank'] ?? null) ? $row['rank'] : null,
            (is_string($row['job_level'] ?? null) && ! is_numeric($row['job_level']))
                ? $row['job_level']
                : null,
        ];

        $name = '';
        foreach ($candidates as $candidate) {
            $candidate = trim((string) ($candidate ?? ''));
            if ($candidate !== '') {
                $name = $candidate;
                break;
            }
        }

        if ($name === '') {
            return null;
        }

        $name = $this->normalizeRankDisplayName($name, $row['level'] ?? null);

        $code = trim((string) ($row['rank_code'] ?? $row['level_code'] ?? $row['grade_code'] ?? ''));
        if ($code === '' && is_numeric($row['level'] ?? null)) {
            $code = 'L'.(int) $row['level'];
        }
        if ($code === '' && preg_match('/^Cấp\s+(\d+)$/u', $name, $m) === 1) {
            $code = 'L'.(int) $m[1];
        }
        if ($code === '') {
            $code = self::codeFromName('RANK', $name);
        }

        return [
            'code' => $code,
            'name' => $name,
            'source' => 'hrm_title_rank',
            'hrm_uuid' => null,
        ];
    }

    private function levelNumberFromCode(string $code): ?int
    {
        if (preg_match('/^L(\d+)$/i', trim($code), $m) === 1) {
            return (int) $m[1];
        }
        if (ctype_digit(trim($code))) {
            return (int) $code;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return CatalogRow|null
     */
    private function mapApiRow(array $row, string $prefix, string $source): ?array
    {
        $name = trim((string) (
            $row['name']
            ?? $row['title']
            ?? $row['label']
            ?? $row['display_name']
            ?? $row['job_title_name']
            ?? $row['rank_name']
            ?? $row['level_name']
            ?? $row['grade_name']
            ?? $row['ten']
            ?? $row['ten_cap_bac']
            ?? ''
        ));
        if ($name === '' && is_numeric($row['level'] ?? null)) {
            $name = 'Cấp '.(int) $row['level'];
        }
        if ($name === '') {
            return null;
        }

        if ($prefix === 'RANK') {
            $name = $this->normalizeRankDisplayName($name, $row['level'] ?? null);
        }

        $code = trim((string) ($row['code'] ?? $row['slug'] ?? $row['ma'] ?? $row['ma_cap_bac'] ?? ''));
        if ($code === '' && is_numeric($row['level'] ?? null)) {
            $code = 'L'.(int) $row['level'];
        }
        if ($code === '' && preg_match('/^Cấp\s+(\d+)$/u', $name, $m) === 1) {
            $code = 'L'.(int) $m[1];
        }
        if ($code === '') {
            $code = self::codeFromName($prefix, $name);
        }

        $uuid = trim((string) ($row['id'] ?? $row['uuid'] ?? ''));

        return [
            'code' => $code,
            'name' => $name,
            'source' => $source,
            'hrm_uuid' => $uuid !== '' ? $uuid : null,
        ];
    }

    /**
     * HRM/meta đôi khi trả tên cấp = "1"/"2" — luôn hiển thị «Cấp N».
     */
    private function normalizeRankDisplayName(string $name, mixed $levelHint = null): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return $trimmed;
        }

        if (preg_match('/^Cấp\s+\d+$/ui', $trimmed) === 1) {
            return 'Cấp '.(int) preg_replace('/\D+/', '', $trimmed);
        }

        if (ctype_digit($trimmed) && (int) $trimmed >= 1) {
            return 'Cấp '.(int) $trimmed;
        }

        if (preg_match('/^L(\d+)$/i', $trimmed, $m) === 1) {
            return 'Cấp '.(int) $m[1];
        }

        if (is_numeric($levelHint) && (int) $levelHint >= 1
            && (strcasecmp($trimmed, (string) (int) $levelHint) === 0
                || strcasecmp($trimmed, 'L'.(int) $levelHint) === 0)) {
            return 'Cấp '.(int) $levelHint;
        }

        return $trimmed;
    }

    /**
     * @param  array<string, CatalogRow>  $byCode
     */
    private function mergeEmployeeTitles(array &$byCode): void
    {
        Employee::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'role_title', 'meta'])
            ->each(function (Employee $employee) use (&$byCode) {
                $meta = is_array($employee->meta) ? $employee->meta : [];
                $candidates = array_filter([
                    trim((string) ($employee->role_title ?? '')),
                    trim((string) ($meta['position_name'] ?? '')),
                    trim((string) ($meta['job_title_name'] ?? '')),
                    trim((string) ($meta['job_position'] ?? '')),
                ], static fn (string $v): bool => $v !== '');

                foreach ($candidates as $name) {
                    $code = self::codeFromName('TITLE', $name);
                    $key = strtoupper($code);
                    if (isset($byCode[$key])) {
                        continue;
                    }
                    $byCode[$key] = [
                        'code' => $code,
                        'name' => $name,
                        'source' => 'employee',
                        'hrm_uuid' => null,
                    ];
                }
            });
    }

    /**
     * @param  array<string, CatalogRow>  $byCode
     */
    private function mergeEmployeeRanks(array &$byCode): void
    {
        Employee::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'meta'])
            ->each(function (Employee $employee) use (&$byCode) {
                $meta = is_array($employee->meta) ? $employee->meta : [];
                $candidates = array_filter([
                    trim((string) ($meta['rank'] ?? '')),
                    trim((string) ($meta['rank_name'] ?? '')),
                    trim((string) ($meta['job_level'] ?? '')),
                    trim((string) ($meta['level_name'] ?? '')),
                    trim((string) ($meta['grade'] ?? '')),
                    trim((string) ($meta['employee_level'] ?? '')),
                ], static fn (string $v): bool => $v !== '');

                foreach ($candidates as $rawName) {
                    $name = $this->normalizeRankDisplayName($rawName);
                    if ($name === '') {
                        continue;
                    }
                    $code = preg_match('/^Cấp\s+(\d+)$/u', $name, $m) === 1
                        ? 'L'.(int) $m[1]
                        : self::codeFromName('RANK', $name);
                    $key = strtoupper($code);
                    if (isset($byCode[$key])) {
                        continue;
                    }
                    $byCode[$key] = [
                        'code' => $code,
                        'name' => $name,
                        'source' => 'employee',
                        'hrm_uuid' => null,
                    ];
                }
            });
    }

    /**
     * @param  list<CatalogRow>  $list
     * @return CatalogRow|null
     */
    private function findIn(array $list, string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        foreach ($list as $row) {
            if (strcasecmp($row['code'], $code) === 0) {
                return $row;
            }
        }

        return null;
    }
}
