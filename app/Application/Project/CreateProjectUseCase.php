<?php

namespace App\Application\Project;

use App\Models\Project;
use App\Support\Options\DepartmentOptions;

class CreateProjectUseCase
{
    public function __construct(private readonly DepartmentOptions $departmentOptions) {}

    /**
     * @param  array<string, mixed>  $data  Validated payload from StoreProjectRequest
     */
    public function execute(array $data): Project
    {
        if (empty($data['department_id'])) {
            $data['department_id'] = $this->departmentOptions->defaultOwnerId();
        }

        if (empty($data['code'])) {
            $data['code'] = $this->generateCode();
        }

        return Project::create($data);
    }

    public function suggestCode(): string
    {
        return $this->generateCode();
    }

    private function generateCode(): string
    {
        $last = Project::orderByDesc('id')->value('id') ?? 0;

        return 'PRJ-'.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
    }
}
