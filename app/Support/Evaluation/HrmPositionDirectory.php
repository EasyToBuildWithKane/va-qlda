<?php

namespace App\Support\Evaluation;

use App\Models\Employee;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Danh mục vị trí đánh giá từ nhân sự đã đồng bộ HRM
 * (role_title + meta position fields). Cache 24h.
 *
 * @phpstan-type PositionRow array{code: string, name: string, source: string}
 */
final class HrmPositionDirectory
{
    public const CACHE_KEY = 'evaluation.position_directory.v1';

    public const CACHE_TTL_SECONDS = 86400;

    /**
     * @return list<PositionRow>
     */
    public function all(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        /** @var list<PositionRow> */
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn () => $this->build());
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return PositionRow|null
     */
    public function findByCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        foreach ($this->all() as $row) {
            if (strcasecmp($row['code'], $code) === 0) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return PositionRow|null
     */
    public function findByName(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $needle = $this->normalizeKey($name);
        foreach ($this->all() as $row) {
            if ($this->normalizeKey($row['name']) === $needle
                || strcasecmp($row['code'], $name) === 0) {
                return $row;
            }
        }

        return null;
    }

    public static function codeFromName(string $name): string
    {
        $slug = Str::upper(Str::slug($name, '_'));
        if ($slug === '') {
            $slug = 'POS_'.substr(md5($name), 0, 8);
        }

        return 'POS_'.$slug;
    }

    /**
     * @return list<PositionRow>
     */
    private function build(): array
    {
        /** @var array<string, PositionRow> $byCode */
        $byCode = [];

        Employee::query()
            ->where('status', 'active')
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
                    $code = self::codeFromName($name);
                    $key = strtoupper($code);
                    if (isset($byCode[$key])) {
                        continue;
                    }
                    $byCode[$key] = [
                        'code' => $code,
                        'name' => $name,
                        'source' => 'employee',
                    ];
                }
            });

        $list = array_values($byCode);
        usort($list, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $list;
    }

    private function normalizeKey(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? $value;
    }
}
