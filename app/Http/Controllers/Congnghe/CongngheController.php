<?php

namespace App\Http\Controllers\Congnghe;

use App\Http\Controllers\Controller;
use App\Models\AiAccount;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OrgTeam;
use App\Models\OrgTeamMember;
use App\Models\Project;
use App\Models\Task;
use App\Support\Enums\ProjectStatus;
use App\Support\Enums\ProjectType;
use App\Support\Enums\TaskStatus;
use App\Support\OrgTeam\OrgTeamOverviewBuilder;
use App\Support\OrgTeamTreeBuilder;
use App\Support\PublicMediaUrl;
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
            'org' => [
                'overview' => OrgTeamOverviewBuilder::build(),
                'forest' => OrgTeamTreeBuilder::forest(),
                'people' => $this->orgPeople(),
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
                    ->with('manager:id,full_name,avatar_path,role_title')
                    ->orderByDesc('updated_at')
                    ->take(6)
                    ->get(['id', 'name', 'code', 'description', 'color', 'status', 'type', 'manager_id'])
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
     * Hệ sinh thái sản phẩm — toàn bộ dự án đã hoàn thành (nền tảng đã nghiệm thu,
     * đưa vào vận hành). Lấy đầy đủ, không giới hạn, sắp xếp theo lần cập nhật gần nhất.
     *
     * @return array<int, array<string, mixed>>
     */
    private function products(): array
    {
        return Project::where('status', ProjectStatus::Completed)
            ->with('manager:id,full_name,avatar_path,role_title')
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'code', 'description', 'color', 'status', 'type', 'manager_id'])
            ->map(fn (Project $p) => $this->projectCard($p))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function projectCard(Project $project): array
    {
        $manager = $project->manager;

        return [
            'id' => $project->id,
            'name' => $project->name,
            'code' => $project->code,
            'color' => $project->color,
            'description' => $project->description,
            'progress' => $project->progress(),
            'status' => $project->status->label(),
            'statusColor' => $project->status->color(),
            'manager' => $manager ? [
                'id' => $manager->id,
                'name' => $manager->full_name,
                'avatar' => PublicMediaUrl::fromPublicDisk($manager->avatar_path),
                'role_title' => $manager->role_title,
            ] : null,
        ];
    }

    /**
     * Danh bạ nhân sự xuất hiện trên sơ đồ tổ chức (id → hồ sơ ngắn). Dùng để
     * modal chi tiết trên landing tra cứu nhanh khi bấm vào một thẻ thành viên.
     *
     * @return array<int, array<string, mixed>>
     */
    private function orgPeople(): array
    {
        $leaderTeams = OrgTeam::query()
            ->whereNotNull('leader_id')
            ->get(['id', 'name', 'leader_id']);

        $memberRows = OrgTeamMember::query()
            ->whereNotNull('employee_id')
            ->with(['team:id,name', 'section:id,title'])
            ->get(['id', 'employee_id', 'org_team_id', 'section_id']);

        /** @var array<int, array{team: string|null, section: string|null, is_leader: bool}> $context */
        $context = [];
        foreach ($leaderTeams as $team) {
            $context[$team->leader_id] ??= [
                'team' => $team->name,
                'section' => null,
                'is_leader' => true,
            ];
        }
        foreach ($memberRows as $row) {
            if (! isset($context[$row->employee_id])) {
                $context[$row->employee_id] = [
                    'team' => $row->team?->name,
                    'section' => $row->section?->title,
                    'is_leader' => false,
                ];
            }
        }

        $ids = array_keys($context);
        if ($ids === []) {
            return [];
        }

        return Employee::query()
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(function (Employee $e) use ($context): array {
                $meta = is_array($e->meta) ? $e->meta : [];
                $ctx = $context[$e->id] ?? ['team' => null, 'section' => null, 'is_leader' => false];

                return [$e->id => [
                    'id' => $e->id,
                    'name' => $e->full_name,
                    'code' => $e->code,
                    'avatar' => PublicMediaUrl::fromPublicDisk($e->avatar_path),
                    'role_title' => $e->role_title ?: ($meta['position_name'] ?? null),
                    'email' => $e->email,
                    'phone' => $e->phone,
                    'bio' => $meta['bio'] ?? null,
                    'team' => $ctx['team'],
                    'section' => $ctx['section'],
                    'is_leader' => $ctx['is_leader'],
                    'is_active' => (bool) $e->is_active,
                ]];
            })
            ->all();
    }
}
