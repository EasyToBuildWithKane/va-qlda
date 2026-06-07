<?php

namespace App\Application\Project;

use App\Models\Project;

class ProjectShowDataLoader
{
    public function load(Project $project): Project
    {
        $project->load([
            'manager',
            'department',
            'members',
            'sprints' => fn ($q) => $q->withCount('tasks'),
            'epics',
            'tasks' => fn ($q) => $q->with([
                'assignee',
                'assignees',
                'reporter',
                'reviewer',
                'epic',
                'parent:id,title',
                'sprint:id,project_id,name',
                'dependencies:id,title,status',
                'dependents:id,title,status,progress',
                'subtasks' => fn ($s) => $s->with('assignee'),
                'watchers',
                'attachments' => fn ($a) => $a->with('uploadedBy'),
                'activities' => fn ($a) => $a->with('employee')->limit(100),
                'worklogs' => fn ($w) => $w->with('employee')->latest('date'),
                'comments' => fn ($c) => $c->whereNull('parent_id')->with(['author', 'replies.author'])->latest(),
            ])->orderBy('order_column'),
            'blockers' => fn ($q) => $q->with([
                'raisedBy',
                'owner',
                'comments' => fn ($c) => $c->with('author')->latest(),
                'attachments' => fn ($a) => $a->with('uploadedBy')->latest(),
                'activities' => fn ($a) => $a->with('employee')->latest(),
            ])->latest(),
            'feedbacks' => fn ($q) => $q->with(['assignee', 'reporter'])
                ->withCount('comments')
                ->latest(),
            'attachments' => fn ($q) => $q->with([
                'uploadedBy',
                'updatedBy',
                'activities' => fn ($a) => $a->with('employee')->latest(),
            ])->latest(),
        ]);

        return $project;
    }
}
