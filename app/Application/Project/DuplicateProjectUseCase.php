<?php

namespace App\Application\Project;

use App\Models\Project;
use App\Support\Enums\ProjectStatus;

class DuplicateProjectUseCase
{
    public function execute(Project $project): Project
    {
        $copy = $project->replicate(['code']);
        $copy->code = $this->generateCode();
        $copy->name = $project->name.' (bản sao)';
        $copy->status = ProjectStatus::Planning;
        $copy->save();

        return $copy;
    }

    private function generateCode(): string
    {
        $last = Project::orderByDesc('id')->value('id') ?? 0;

        return 'PRJ-'.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
    }
}
