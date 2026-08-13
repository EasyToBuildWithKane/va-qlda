<?php

namespace App\Application\RoutineTask;

use App\Domain\RoutineTask\Support\RoutineTaskSchedule;
use App\Support\Enums\TaskStatus;
use Illuminate\Support\Carbon;

trait MapsRoutineTaskFields
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mapWritableFields(array $data, ?TaskStatus $currentStatus = null, ?int $currentProgress = null): array
    {
        $payload = [];

        if (array_key_exists('title', $data)) {
            $payload['title'] = trim((string) $data['title']);
        }

        if (array_key_exists('description', $data)) {
            $description = $data['description'];
            $payload['description'] = $description === null || $description === ''
                ? null
                : trim((string) $description);
        }

        if (array_key_exists('blockers', $data)) {
            $blockers = $data['blockers'];
            $payload['blockers'] = $blockers === null || $blockers === ''
                ? null
                : trim((string) $blockers);
        }

        if (array_key_exists('risks', $data)) {
            $risks = $data['risks'];
            $payload['risks'] = $risks === null || $risks === ''
                ? null
                : trim((string) $risks);
        }

        if (array_key_exists('position', $data) && $data['position'] !== null) {
            $payload['position'] = max(0, (int) $data['position']);
        }

        $status = $currentStatus;
        if (array_key_exists('status', $data) && $data['status'] !== null && $data['status'] !== '') {
            $parsed = TaskStatus::tryFrom((string) $data['status']);
            if ($parsed !== null) {
                $status = $parsed;
                $payload['status'] = $parsed;
            }
        }

        $hasSchedule = array_key_exists('work_date', $data)
            || array_key_exists('start_time', $data)
            || array_key_exists('end_time', $data);

        if ($hasSchedule) {
            $workDate = RoutineTaskSchedule::normalizeDate(
                array_key_exists('work_date', $data) ? ($data['work_date'] ?? null) : null,
            );
            $startTime = array_key_exists('start_time', $data) ? ($data['start_time'] ?? null) : null;
            $endTime = array_key_exists('end_time', $data) ? ($data['end_time'] ?? null) : null;

            $started = RoutineTaskSchedule::startedAt($workDate, is_string($startTime) ? $startTime : null);
            $ended = RoutineTaskSchedule::endedAt(
                $workDate,
                is_string($startTime) ? $startTime : null,
                is_string($endTime) ? $endTime : null,
            );

            $payload['work_date'] = $workDate ?? $started?->toDateString();
            $payload['started_at'] = $started;
            $payload['ended_at'] = $ended;
        }

        $started = $payload['started_at'] ?? null;
        $ended = $payload['ended_at'] ?? null;

        if (array_key_exists('estimate_hours', $data)) {
            $estimate = $data['estimate_hours'];
            $payload['estimate_hours'] = $estimate === null || $estimate === ''
                ? null
                : round((float) $estimate, 2);
        }

        if (array_key_exists('actual_hours', $data) || $hasSchedule) {
            $explicit = array_key_exists('actual_hours', $data) ? $data['actual_hours'] : null;
            $payload['actual_hours'] = RoutineTaskSchedule::actualHours(
                $started instanceof Carbon ? $started : null,
                $ended instanceof Carbon ? $ended : null,
                $explicit,
            );
        }

        if (array_key_exists('progress_percent', $data) || array_key_exists('status', $data)) {
            $payload['progress_percent'] = RoutineTaskSchedule::progressFor(
                $status,
                array_key_exists('progress_percent', $data) ? $data['progress_percent'] : null,
                $currentProgress,
            );
        }

        if (array_key_exists('status', $data) && isset($payload['status'])) {
            $payload['completed_at'] = $payload['status'] === TaskStatus::Done
                ? now()
                : null;
        }

        return $payload;
    }
}
