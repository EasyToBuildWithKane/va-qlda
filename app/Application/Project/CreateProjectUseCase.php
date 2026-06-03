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

        // Luôn cấp mã mới lúc lưu — tránh mã gợi ý trên form bị lỗi thời (tab mở lâu / trùng).
        $data['code'] = $this->allocateUniqueCode();

        return Project::create($data);
    }

    public function suggestCode(): string
    {
        return $this->allocateUniqueCode();
    }

    private function allocateUniqueCode(): string
    {
        $lastId = (int) (Project::orderByDesc('id')->value('id') ?? 0);

        for ($offset = 1; $offset <= 500; $offset++) {
            $code = 'PRJ-'.str_pad((string) ($lastId + $offset), 3, '0', STR_PAD_LEFT);
            if (! Project::query()->where('code', $code)->exists()) {
                return $code;
            }
        }

        return 'PRJ-'.str_pad((string) (time() % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
