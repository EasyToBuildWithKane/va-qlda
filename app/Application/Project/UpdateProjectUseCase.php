<?php

namespace App\Application\Project;

use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\NotificationDispatcher;
use App\Support\ProjectActivityLogger;

class UpdateProjectUseCase
{
    /**
     * @param  array<string, mixed>  $data  Validated payload from UpdateProjectRequest
     */
    public function execute(Project $project, array $data, SystemAccount $actor): Project
    {
        $project->update($data);

        $changes = collect($project->getChanges())->except(['updated_at'])->all();
        if ($changes !== []) {
            $fresh = $project->fresh();
            ProjectActivityLogger::updated($fresh, $actor, $changes);
            NotificationDispatcher::projectUpdated($fresh, $actor, $changes);
        }

        return $project;
    }
}
