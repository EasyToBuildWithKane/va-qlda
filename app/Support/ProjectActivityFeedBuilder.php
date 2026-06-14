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
        $events = collect();

        $events = $events->merge($this->projectActivities($project));
        $events = $events->merge($this->taskActivities($project));
        $events = $events->merge($this->blockerActivities($project));
        $events = $events->merge($this->attachmentActivities($project));

        return $events
            ->sortByDesc(fn (array $e) => $e['at'])
            ->take($limit)
            ->values()
            ->all();
    }

    private function projectActivities(Project $project): Collection
    {
        return ProjectActivity::query()
            ->where('project_id', $project->id)
            ->with('employee')
            ->latest()
            ->limit(40)
            ->get()
            ->map(fn (ProjectActivity $a) => $this->row(
                'project_'.$a->id,
                self::PROJECT_TYPE_MAP[$a->event] ?? 'project_updated',
                $this->withActor($a->employee?->full_name, $a->description),
                $a->created_at,
            ));
    }

    private function taskActivities(Project $project): Collection
    {
        $taskIds = Task::query()->where('project_id', $project->id)->pluck('id');
        if ($taskIds->isEmpty()) {
            return collect();
        }

        return TaskActivity::query()
            ->whereIn('task_id', $taskIds)
            ->with(['employee', 'task:id,title'])
            ->latest()
            ->limit(60)
            ->get()
            ->map(function (TaskActivity $a) {
                $prefix = $a->task?->title ? "[{$a->task->title}] " : '';

                return $this->row(
                    'task_'.$a->id,
                    self::EVENT_TYPE_MAP[$a->event] ?? 'task_updated',
                    $this->withActor($a->employee?->full_name, $prefix.$a->description),
                    $a->created_at,
                );
            });
    }

    private function blockerActivities(Project $project): Collection
    {
        $blockerIds = Blocker::query()->where('project_id', $project->id)->pluck('id');
        if ($blockerIds->isEmpty()) {
            return collect();
        }

        return BlockerActivity::query()
            ->whereIn('blocker_id', $blockerIds)
            ->with('employee')
            ->latest()
            ->limit(40)
            ->get()
            ->map(fn (BlockerActivity $a) => $this->row(
                'blocker_'.$a->id,
                self::BLOCKER_TYPE_MAP[$a->event] ?? 'issue_updated',
                $this->withActor($a->employee?->full_name, $a->description),
                $a->created_at,
            ));
    }

    private function attachmentActivities(Project $project): Collection
    {
        $attachmentIds = ProjectAttachment::query()->where('project_id', $project->id)->pluck('id');
        if ($attachmentIds->isEmpty()) {
            return collect();
        }

        return ProjectAttachmentActivity::query()
            ->whereIn('project_attachment_id', $attachmentIds)
            ->with('employee')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (ProjectAttachmentActivity $a) => $this->row(
                'pdoc_'.$a->id,
                'document',
                $this->withActor($a->employee?->full_name, $a->description),
                $a->created_at,
            ));
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
