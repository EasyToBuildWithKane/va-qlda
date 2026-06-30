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
     * Merge duplicate project entries (same `id`) and duplicate tasks on save/read.
     *
     * @param  array<int, array<string, mixed>>|null  $projects
     * @return array<int, array<string, mixed>>|null
     */
    public static function dedupeProjects(?array $projects): ?array
    {
        if ($projects === null || $projects === []) {
            return $projects;
        }

        $merged = [];

        foreach ($projects as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $id = (int) ($entry['id'] ?? 0);

            if (! isset($merged[$id])) {
                $merged[$id] = $entry;

                continue;
            }

            $merged[$id] = self::mergeProjectEntries($merged[$id], $entry);
        }

        return array_values($merged);
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @return array<string, mixed>
     */
    private static function mergeProjectEntries(array $a, array $b): array
    {
        $tasksA = is_array($a['tasks'] ?? null) ? $a['tasks'] : [];
        $tasksB = is_array($b['tasks'] ?? null) ? $b['tasks'] : [];
        $a['tasks'] = self::dedupeTasks(array_merge($tasksA, $tasksB));

        if (empty($a['name']) && ! empty($b['name'])) {
            $a['name'] = $b['name'];
        }

        return $a;
    }

    /**
     * @param  array<int, mixed>  $tasks
     * @return array<int, array<string, mixed>>
     */
    private static function dedupeTasks(array $tasks): array
    {
        $seen = [];
        $out = [];

        foreach ($tasks as $task) {
            if (! is_array($task)) {
                continue;
            }

            $id = (int) ($task['id'] ?? 0);
            $titleKey = mb_strtolower(trim((string) ($task['title'] ?? '')));
            $key = $id > 0 ? 'id:'.$id : ($titleKey !== '' ? 'title:'.$titleKey : null);

            if ($key === null || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $out[] = $task;
        }

        return $out;
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
            $data['projects'] = self::dedupeProjects(is_array($data['projects']) ? $data['projects'] : null);
            $data['project_id'] = self::legacyProjectId($data['projects']);
        }

        return $data;
    }
}
