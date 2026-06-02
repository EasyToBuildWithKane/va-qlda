<?php

namespace App\Application\Project;

use App\Models\Project;
use App\Support\Enums\ProjectStatus;

class ArchiveProjectUseCase
{
    public function execute(Project $project): Project
    {
        $project->update([
            'status' => ProjectStatus::Completed,
            'is_active' => false,
        ]);

        return $project;
    }
}
