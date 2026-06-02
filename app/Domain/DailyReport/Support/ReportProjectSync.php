<?php

namespace App\Domain\DailyReport\Support;

/**
 * Source of truth for daily report ↔ project linkage.
 *
 * @see docs/DAILY_REPORT_PROJECTS.md
 */
final class ReportProjectSync
{
    /**
     * Primary project id for list filters (legacy column).
     * Derived from the first entry in the `projects` JSON array.
     *
     * @param  array<int, array{id?: int, name?: string, code?: string}>|null  $projects
     */
    public static function legacyProjectId(?array $projects): ?int
    {
        if ($projects === null || $projects === []) {
            return null;
        }

        $first = $projects[0] ?? null;

        return isset($first['id']) ? (int) $first['id'] : null;
    }

    /**
     * Merge legacy project_id into payload when projects is present.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function applyToPayload(array $data): array
    {
        if (array_key_exists('projects', $data)) {
            $data['project_id'] = self::legacyProjectId($data['projects']);
        }

        return $data;
    }
}
