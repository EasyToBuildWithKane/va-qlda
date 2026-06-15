<?php

namespace App\Http\Controllers\Congnghe;

use App\Http\Controllers\Controller;
use App\Models\AiAccount;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OrgTeam;
use App\Models\Project;
use App\Models\Task;
use App\Support\Enums\ProjectStatus;
use App\Support\Enums\ProjectType;
use App\Support\Enums\TaskStatus;
use App\Support\OrgTeam\OrgTeamOverviewBuilder;
use App\Support\OrgTeamTreeBuilder;
use App\Support\PublicMediaUrl;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trang giới thiệu Phòng Công Nghệ (/congnghe) — landing nội bộ cho mọi nhân sự.
 *
 * Tất cả số liệu/đội ngũ/dự án đều lấy thật từ DB. Phần "định danh" (sứ mệnh,
 * tầm nhìn, giá trị, công nghệ, lộ trình) là nội dung tĩnh đặt ở frontend vì
 * không có nguồn dữ liệu — controller chỉ cung cấp dữ liệu có thể truy vấn.
 */
class CongngheController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Congnghe/Index', [
            'metrics' => $this->metrics(),
            'phases' => $this->projectPhases(),
            'products' => $this->products(),
            'team' => $this->team(),
            'org' => [
                'overview' => OrgTeamOverviewBuilder::build(),
                'forest' => OrgTeamTreeBuilder::forest(),
            ],
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function metrics(): array
    {
        return [
            'projects' => Project::count(),
            'activeProjects' => Project::where('status', ProjectStatus::Active)->count(),
            'completedProjects' => Project::where('status', ProjectStatus::Completed)->count(),
            'members' => Employee::where('is_active', true)->count(),
            'departments' => Department::active()->count(),
            'orgTeams' => OrgTeam::count(),
            'tasks' => Task::count(),
            'doneTasks' => Task::where('status', TaskStatus::Done)->count(),
            'aiAccounts' => AiAccount::count(),
        ];
    }

    /**
     * Dự án nhóm theo vòng đời (R&D → Triển khai → Vận hành).
     *
     * @return array<int, array<string, mixed>>
     */
    private function projectPhases(): array
    {
        return collect(ProjectType::cases())
            ->map(function (ProjectType $type): array {
                $items = Project::where('type', $type)
                    ->orderByDesc('updated_at')
                    ->take(6)
                    ->get(['id', 'name', 'code', 'color', 'status', 'type'])
                    ->map(fn (Project $p) => $this->projectCard($p))
                    ->values();

                return [
                    'value' => $type->value,
                    'label' => $type->label(),
                    'color' => $type->color(),
                    'total' => Project::where('type', $type)->count(),
                    'items' => $items,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Sản phẩm đang vận hành (pha "Vận hành cải tiến"); nếu chưa có thì lấy dự án
     * đang triển khai để khối sản phẩm không rỗng.
     *
     * @return array<int, array<string, mixed>>
     */
    private function products(): array
    {
        $query = Project::where('type', ProjectType::Operation);

        if ($query->clone()->count() === 0) {
            $query = Project::where('status', ProjectStatus::Active);
        }

        return $query
            ->orderByDesc('updated_at')
            ->take(8)
            ->get(['id', 'name', 'code', 'color', 'status', 'type'])
            ->map(fn (Project $p) => $this->projectCard($p))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function projectCard(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'code' => $project->code,
            'color' => $project->color,
            'progress' => $project->progress(),
            'status' => $project->status->label(),
            'statusColor' => $project->status->color(),
        ];
    }

    /**
     * Đội ngũ — nhân sự đang hoạt động, người vào sớm hơn xếp trước.
     *
     * @return array<int, array<string, mixed>>
     */
    private function team(): array
    {
        return Employee::where('is_active', true)
            ->orderByRaw('join_date is null, join_date asc')
            ->orderBy('full_name')
            ->take(18)
            ->get(['id', 'full_name', 'role_title', 'avatar_path', 'join_date'])
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => $e->full_name,
                'role' => $e->role_title,
                'avatar' => PublicMediaUrl::fromPublicDisk($e->avatar_path),
                'initials' => Str::of($e->full_name)
                    ->explode(' ')
                    ->filter()
                    ->take(-2)
                    ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
                    ->implode(''),
            ])
            ->values()
            ->all();
    }
}
