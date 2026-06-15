<?php

namespace App\Support;

use App\Models\Blocker;
use App\Models\BlockerActivity;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectAttachment;
use App\Models\ProjectAttachmentActivity;
use App\Models\Task;
use App\Models\TaskActivity;
use Illuminate\Support\Collection;

/**
 * Gộp lịch sử hoạt động server-side cho dashboard dự án.
 */
class ProjectActivityFeedBuilder
{
    private const EVENT_TYPE_MAP = [
        'created' => 'task_created',
        'updated' => 'task_updated',
        'status_changed' => 'task_status_changed',
        'worklog' => 'worklog',
        'worklog_removed' => 'worklog',
        'comment' => 'comment',
        'attachment' => 'document',
        'attachment_removed' => 'document',
        'deleted' => 'task_deleted',
        'subtask' => 'task_created',
        'assignees' => 'task_updated',
        'dependencies' => 'task_updated',
        'watcher' => 'task_updated',
        'comment_updated' => 'comment',
        'comment_deleted' => 'comment',
    ];

    private const BLOCKER_TYPE_MAP = [
        'created' => 'issue_opened',
        'updated' => 'issue_updated',
        'status_changed' => 'issue_updated',
        'comment' => 'comment',
        'attachment' => 'document',
        'attachment_removed' => 'document',
        'deleted' => 'issue_closed',
        'bulk_updated' => 'issue_updated',
        'comment_updated' => 'comment',
        'comment_deleted' => 'comment',
    ];

    private const PROJECT_TYPE_MAP = [
        'created' => 'project_created',
        'updated' => 'project_updated',
        'archived' => 'project_updated',
        'duplicated' => 'project_created',
        'member_added' => 'member',
        'member_updated' => 'member',
        'member_removed' => 'member',
        'sprint_created' => 'sprint_started',
        'sprint_updated' => 'sprint_started',
        'sprint_deleted' => 'sprint_started',
        'task_deleted' => 'task_deleted',
        'blocker_deleted' => 'issue_closed',
        'deleted' => 'project_updated',
    ];

    /**
     * @return array<int, array{id: string, type: string, message: string, at: string}>
     */
    public function forProject(Project $project, int $limit = 80): array
    {
        return $this->build($project, $limit);
    }

    /**
     * System-wide feed: the latest activity across every project, used by the
     * org dashboard. Scoped by recency only (no project filter).
     *
     * @return array<int, array{id: string, type: string, message: string, at: string}>
     */
    public function forSystem(int $limit = 60): array
    {
        return $this->build(null, $limit);
    }

    /**
     * @return array<int, array{id: string, type: string, message: string, at: string}>
     */
    private function build(?Project $project, int $limit): array
    {
        $events = collect()
            ->merge($this->projectActivities($project))
            ->merge($this->taskActivities($project))
            ->merge($this->blockerActivities($project))
            ->merge($this->attachmentActivities($project));

        return $events
            ->sortByDesc(fn (array $e) => $e['at'])
            ->take($limit)
            ->values()
            ->all();
    }

    private function projectActivities(?Project $project): Collection
    {
        $system = $project === null;

        return ProjectActivity::query()
            ->when($project, fn ($q) => $q->where('project_id', $project->id))
            ->with(['employee', ...($system ? ['project:id,name'] : [])])
            ->latest()
            ->limit($system ? 30 : 40)
            ->get()
            ->map(fn (ProjectActivity $a) => $this->row(
                'project_'.$a->id,
                self::PROJECT_TYPE_MAP[$a->event] ?? 'project_updated',
                $this->withActor(
                    $a->employee?->full_name,
                    $this->withScope($system ? $a->project?->name : null, $a->description),
                ),
                $a->created_at,
            ));
    }

    private function taskActivities(?Project $project): Collection
    {
        $system = $project === null;

        $query = TaskActivity::query()
            ->with(['employee', 'task:id,title,project_id', ...($system ? ['task.project:id,name'] : [])])
            ->latest()
            ->limit($system ? 40 : 60);

        if (! $system) {
            $taskIds = Task::query()->where('project_id', $project->id)->pluck('id');
            if ($taskIds->isEmpty()) {
                return collect();
            }
            $query->whereIn('task_id', $taskIds);
        }

        return $query->get()->map(function (TaskActivity $a) use ($system) {
            $prefix = $a->task?->title ? "[{$a->task->title}] " : '';
            $scope = $system ? $a->task?->project?->name : null;

            return $this->row(
                'task_'.$a->id,
                self::EVENT_TYPE_MAP[$a->event] ?? 'task_updated',
                $this->withActor($a->employee?->full_name, $this->withScope($scope, $prefix.$a->description)),
                $a->created_at,
            );
        });
    }

    private function blockerActivities(?Project $project): Collection
    {
        $system = $project === null;

        $query = BlockerActivity::query()
            ->with(['employee', ...($system ? ['blocker:id,project_id', 'blocker.project:id,name'] : [])])
            ->latest()
            ->limit($system ? 25 : 40);

        if (! $system) {
            $blockerIds = Blocker::query()->where('project_id', $project->id)->pluck('id');
            if ($blockerIds->isEmpty()) {
                return collect();
            }
            $query->whereIn('blocker_id', $blockerIds);
        }

        return $query->get()->map(fn (BlockerActivity $a) => $this->row(
            'blocker_'.$a->id,
            self::BLOCKER_TYPE_MAP[$a->event] ?? 'issue_updated',
            $this->withActor(
                $a->employee?->full_name,
                $this->withScope($system ? $a->blocker?->project?->name : null, $a->description),
            ),
            $a->created_at,
        ));
    }

    private function attachmentActivities(?Project $project): Collection
    {
        $system = $project === null;

        $query = ProjectAttachmentActivity::query()
            ->with('employee')
            ->latest()
            ->limit($system ? 15 : 30);

        if (! $system) {
            $attachmentIds = ProjectAttachment::query()->where('project_id', $project->id)->pluck('id');
            if ($attachmentIds->isEmpty()) {
                return collect();
            }
            $query->whereIn('project_attachment_id', $attachmentIds);
        }

        return $query->get()->map(fn (ProjectAttachmentActivity $a) => $this->row(
            'pdoc_'.$a->id,
            'document',
            $this->withActor($a->employee?->full_name, $a->description),
            $a->created_at,
        ));
    }

    private function withScope(?string $projectName, string $description): string
    {
        if ($projectName) {
            return "({$projectName}) {$description}";
        }

        return $description;
    }

    private function withActor(?string $name, string $description): string
    {
        if ($name) {
            return "{$name}: {$description}";
        }

        return $description;
    }

    /**
     * @return array{id: string, type: string, message: string, at: string}
     */
    private function row(string $id, string $type, string $message, mixed $at): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'message' => $message,
            'at' => $at instanceof \Illuminate\Support\Carbon
                ? $at->toIso8601String()
                : ($at instanceof \DateTimeInterface ? $at->format(\DateTimeInterface::ATOM) : (string) $at),
        ];
    }
}
