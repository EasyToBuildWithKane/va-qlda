<?php

namespace App\Application\Project;

use App\Models\Project;
use App\Models\Task;
use App\Models\Worklog;
use App\Support\Enums\RateType;

class LogWorkUseCase
{
    /**
     * Record hours against a task and freeze the labour cost.
     *
     * The effective hourly rate is resolved from the employee's row on the
     * task's project (project_member). A monthly rate is converted to an
     * hourly figure via {@see Project::MONTHLY_HOURS}. Both the rate and the
     * computed cost are snapshotted onto the worklog so later rate edits never
     * rewrite historical cost.
     *
     * @param  array{employee_id:int, date:string, hours:float|string, note?:string|null}  $data
     */
    public function execute(Task $task, array $data): Worklog
    {
        $rate = $this->effectiveHourlyRate($task->project_id, (int) $data['employee_id']);
        $hours = (float) $data['hours'];

        return $task->worklogs()->create([
            'employee_id' => $data['employee_id'],
            'date' => $data['date'],
            'hours' => $hours,
            'note' => $data['note'] ?? null,
            'rate_snapshot' => $rate,
            'cost' => $rate !== null ? round($rate * $hours, 2) : null,
        ]);
    }

    /**
     * Resolve the member's hourly rate for a project, normalising monthly rates.
     */
    private function effectiveHourlyRate(int $projectId, int $employeeId): ?float
    {
        $member = Project::find($projectId)
            ?->members()
            ->where('employee_id', $employeeId)
            ->first();

        $pivot = $member?->getRelationValue('pivot');

        if ($pivot === null || $pivot->rate === null) {
            return null;
        }

        $rate = (float) $pivot->rate;

        return $pivot->rate_type === RateType::Monthly->value
            ? round($rate / Project::monthlyHours(), 2)
            : $rate;
    }
}
