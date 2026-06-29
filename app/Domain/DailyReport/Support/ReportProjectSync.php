<?php

namespace App\Domain\DailyReport\Support;

/**
 * Source of truth for daily report ↔ project linkage.
 *
 * @see docs/DAILY_REPORT_PROJECTS.md
 */
final class ReportProjectSync
{
    /** Sentinel in `projects` JSON for routine work — not a real project row. */
    public const ROUTINE_PROJECT_ID = -1;

    public static function isLinkableProjectId(int $id): bool
    {
        return $id > 0;
    }

    /**
     * Primary project id for list filters (legacy column).
     * First real project in `projects` JSON (skips routine sentinel).
     *
     * @param  array<int, array{id?: int, name?: string, code?: string}>|null  $projects
     */
    public static function legacyProjectId(?array $projects): ?int
    {
        if ($projects === null || $projects === []) {
            return null;
        }

        foreach ($projects as $entry) {
            $id = isset($entry['id']) ? (int) $entry['id'] : 0;
            if (self::isLinkableProjectId($id)) {
                return $id;
            }
        }

        return null;
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
