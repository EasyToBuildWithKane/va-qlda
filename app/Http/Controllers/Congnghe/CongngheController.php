<?php

namespace App\Http\Controllers\Congnghe;

use App\Http\Controllers\Controller;
use App\Models\AiAccount;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\Task;
use App\Support\Enums\ProjectAttachmentCategory;
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
        $forest = OrgTeamTreeBuilder::congngheForest();

        return Inertia::render('Congnghe/Index', [
            'metrics' => $this->metrics($forest),
            'phases' => $this->projectPhases(),
            'products' => $this->products(),
            'org' => [
                'overview' => OrgTeamOverviewBuilder::buildFromForest($forest),
                'forest' => $forest,
                'people' => $this->orgPeopleFromForest($forest),
            ],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $forest
     * @return array<string, int>
     */
    private function metrics(array $forest): array
    {
        $orgOverview = OrgTeamOverviewBuilder::buildFromForest($forest);

        return [
            'projects' => Project::count(),
            'activeProjects' => Project::where('status', ProjectStatus::Active)->count(),
            'completedProjects' => Project::where('status', ProjectStatus::Completed)->count(),
            'orgPeople' => (int) ($orgOverview['people_total'] ?? 0),
            'members' => (int) ($orgOverview['active_people'] ?? 0),
            'departments' => Department::active()->count(),
            'orgTeams' => (int) ($orgOverview['teams_total'] ?? 0),
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
                $items = $this->projectQuery()
                    ->where('type', $type)
                    ->orderByDesc('updated_at')
                    ->take(6)
                    ->get()
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
        return $this->projectQuery()
            ->where('status', ProjectStatus::Completed)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Project $p) => $this->projectCard($p))
            ->values()
            ->all();
    }

    private function projectQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Project::query()->with([
            'manager:id,full_name,avatar_path,role_title',
            'attachments' => function ($query): void {
                $query
                    ->where('is_image', true)
                    ->whereIn('category', [
                        ProjectAttachmentCategory::Showcase,
                        ProjectAttachmentCategory::Images,
                    ])
                    ->orderByDesc('updated_at')
                    ->limit(24);
            },
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function projectCard(Project $project): array
    {
        $manager = $project->manager;
        $user = request()->user();

        return [
            'id' => $project->id,
            'name' => $project->name,
            'code' => $project->code,
            'color' => $project->color,
            'description' => $project->description,
            'progress' => $project->progress(),
            'status' => $project->status->label(),
            'statusColor' => $project->status->color(),
            'type' => $project->type->value,
            'type_label' => $project->type->label(),
            'type_color' => $project->type->color(),
            'images' => $this->projectImages($project),
            'can_upload_images' => $user ? $user->can('contribute', $project) : false,
            'manager' => $manager ? [
                'id' => $manager->id,
                'name' => $manager->full_name,
                'avatar' => PublicMediaUrl::fromPublicDisk($manager->avatar_path),
                'role_title' => $manager->role_title,
            ] : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function projectImages(Project $project): array
    {
        if (! $project->relationLoaded('attachments')) {
            return [];
        }

        $attachments = $project->attachments;
        $showcase = $attachments->filter(
            fn (ProjectAttachment $a) => $a->category === ProjectAttachmentCategory::Showcase,
        );
        $pool = $showcase->isNotEmpty()
            ? $showcase
            : $attachments->filter(
                fn (ProjectAttachment $a) => $a->category === ProjectAttachmentCategory::Images,
            );

        return $pool
            ->map(function (ProjectAttachment $a): array {
                $url = $a->url();

                return [
                    'id' => $a->id,
                    'url' => $url,
                    'caption' => $a->notes ?: $a->original_name,
                    'available' => $a->fileExists() && $url !== null,
                ];
            })
            ->filter(fn (array $row) => $row['available'])
            ->values()
            ->all();
    }

    /**
     * Danh bạ nhân sự trên sơ đồ (chỉ người xuất hiện trong cây đã lọc).
     *
     * @param  array<int, array<string, mixed>>  $forest
     * @return array<int, array<string, mixed>>
     */
    private function orgPeopleFromForest(array $forest): array
    {
        /** @var array<int, array{team: string|null, section: string|null, is_leader: bool}> $context */
        $context = [];

        $walk = function (array $node) use (&$walk, &$context): void {
            $teamName = $node['name'] ?? null;
            $leader = is_array($node['leader'] ?? null) ? $node['leader'] : null;
            if (! empty($leader['id'])) {
                $id = (int) $leader['id'];
                $context[$id] ??= [
                    'team' => $teamName,
                    'section' => null,
                    'is_leader' => true,
                ];
            }

            foreach ($node['members'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $emp = is_array($row['employee'] ?? null) ? $row['employee'] : null;
                if (empty($emp['id'])) {
                    continue;
                }
                $id = (int) $emp['id'];
                if (isset($context[$id])) {
                    continue;
                }
                $section = is_array($row['section'] ?? null) ? $row['section'] : null;
                $context[$id] = [
                    'team' => $teamName,
                    'section' => $section['title'] ?? null,
                    'is_leader' => false,
                ];
            }

            foreach ($node['children'] ?? [] as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };

        foreach ($forest as $root) {
            if (is_array($root)) {
                $walk($root);
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
