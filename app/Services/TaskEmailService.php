<?php

namespace App\Services;

use App\Mail\DailyTaskSummaryMail;
use App\Mail\SprintTaskSummaryMail;
use App\Mail\TaskAssignmentMail;
use App\Models\EmailTemplate;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Support\Enums\TaskStatus;
use App\Support\TaskSubtaskInheritance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class TaskEmailService
{
    public function isEnabled(): bool
    {
        return (bool) config('task_email.enabled');
    }

    public function notifyOnAssignEnabled(): bool
    {
        return $this->isEnabled() && (bool) config('task_email.notify_on_assign');
    }

    public function queueTaskAssigned(Task $task, Employee $assignee): void
    {
        if (! $this->notifyOnAssignEnabled()) {
            return;
        }

        $email = $this->resolveEmployeeEmail($assignee);
        if ($email === null) {
            return;
        }

        $template = EmailTemplate::findByKey(EmailTemplate::KEY_TASK_ASSIGNED);
        if ($template !== null && ! $template->is_active) {
            return;
        }

        Mail::to($email)->queue(new TaskAssignmentMail($task->loadMissing(['project', 'sprint', 'assignee']), $assignee));
    }

    /**
     * @return int Number of queued messages
     */
    public function queueDailySummaries(Project $project, ?int $sprintId = null): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $template = EmailTemplate::findByKey(EmailTemplate::KEY_DAILY_SUMMARY);
        if ($template !== null && ! $template->is_active) {
            return 0;
        }

        $tasks = $this->dailyTasksQuery($project, $sprintId)->get();

        return $this->queueGroupedSummaries($project, $tasks, fn (Employee $employee, Collection $group) => new DailyTaskSummaryMail(
            $project,
            $employee,
            $group,
        ));
    }

    /**
     * @return int Number of queued messages
     */
    public function queueSprintSummaries(Project $project, Sprint $sprint): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $template = EmailTemplate::findByKey(EmailTemplate::KEY_SPRINT_SUMMARY);
        if ($template !== null && ! $template->is_active) {
            return 0;
        }

        $tasks = $this->summarizableTasksQuery($project->id, $sprint->id)->get();

        return $this->queueGroupedSummaries($project, $tasks, fn (Employee $employee, Collection $group) => new SprintTaskSummaryMail(
            $project,
            $sprint,
            $employee,
            $group,
        ));
    }

    /**
     * @param  Collection<int, Task>  $tasks
     */
    public function buildTasksTableHtml(Collection $tasks): string
    {
        if ($tasks->isEmpty()) {
            return '<p><em>Không có công việc.</em></p>';
        }

        $rows = '';
        foreach ($tasks as $task) {
            $status = $task->status instanceof TaskStatus
                ? $task->status->label()
                : (string) $task->status;
            $due = $task->due_date?->format('d/m/Y') ?? '—';
            $rows .= '<tr>'
                .'<td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">'.e($task->title).'</td>'
                .'<td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">'.e($status).'</td>'
                .'<td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">'.e($due).'</td>'
                .'</tr>';
        }

        return '<table style="width:100%;border:1px solid #e2e8f0;border-collapse:collapse;border-radius:8px;">'
            .'<thead><tr style="background:#FDF2F6;">'
            .'<th style="padding:8px 10px;text-align:left;color:#9A0036;font-size:13px;">Công việc</th>'
            .'<th style="padding:8px 10px;text-align:left;color:#9A0036;font-size:13px;">Trạng thái</th>'
            .'<th style="padding:8px 10px;text-align:left;color:#9A0036;font-size:13px;">Hạn</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    public function resolveEmployeeEmail(Employee $employee): ?string
    {
        if (! $employee->is_active) {
            return null;
        }

        $email = trim((string) $employee->email);

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * @param  callable(Employee, Collection<int, Task>): \Illuminate\Mail\Mailable  $mailableFactory
     */
    private function queueGroupedSummaries(Project $project, Collection $tasks, callable $mailableFactory): int
    {
        unset($project);

        /** @var array<int, Collection<int, Task>> $groups */
        $groups = [];

        foreach ($tasks as $task) {
            foreach (TaskSubtaskInheritance::assigneeIds($task) as $employeeId) {
                $groups[$employeeId] = ($groups[$employeeId] ?? collect())->push($task);
            }
        }

        $queued = 0;
        foreach ($groups as $employeeId => $group) {
            $assignee = Employee::query()->find($employeeId);
            if (! $assignee instanceof Employee) {
                continue;
            }
            $email = $this->resolveEmployeeEmail($assignee);
            if ($email === null) {
                continue;
            }

            $uniqueTasks = $group->unique('id')->values();
            Mail::to($email)->queue($mailableFactory($assignee, $uniqueTasks));
            $queued++;
        }

        return $queued;
    }

    private function dailyTasksQuery(Project $project, ?int $sprintId)
    {
        $today = now()->toDateString();

        $query = $this->summarizableTasksQuery($project->id, $sprintId)
            ->where(function ($q) use ($today) {
                $q->whereDate('due_date', $today)
                    ->orWhereDate('updated_at', $today);
            });

        return $query;
    }

    private function summarizableTasksQuery(int $projectId, ?int $sprintId)
    {
        $query = Task::query()
            ->where('project_id', $projectId)
            ->whereNull('parent_id')
            ->where(function ($q) {
                $q->whereNotNull('assignee_id')
                    ->orWhereHas('assignees');
            })
            ->with(['assignee', 'assignees', 'sprint'])
            ->orderBy('order_column');

        if ($sprintId !== null) {
            $query->where('sprint_id', $sprintId);
        }

        return $query;
    }
}
