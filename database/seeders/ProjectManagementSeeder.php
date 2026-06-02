<?php

namespace Database\Seeders;

use App\Models\Blocker;
use App\Models\Bug;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Feedback;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Support\Enums\ProjectStatus;
use App\Support\Enums\RateType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo data for the project-management module: enriches a few existing projects
 * with dates/budget/manager, adds members + rates, sprints, scheduled tasks
 * (so the Gantt renders), worklogs (so cost rolls up), blockers, bugs and
 * feedback with comments. Runs after ProjectSeeder (projects 1–8 already exist).
 */
class ProjectManagementSeeder extends Seeder
{
    public function run(): void
    {
        $team = $this->ensureTeam();
        $base = Carbon::now()->startOfMonth();

        // --- Departments (phòng ban) ---------------------------------------
        $departments = $this->ensureDepartments($team);

        // --- Enrich projects 1 & 2 as the showcase projects -----------------
        $portal = Project::find(1);
        $qlda = Project::find(2);
        if (! $portal || ! $qlda) {
            return; // ProjectSeeder hasn't run; nothing to enrich.
        }

        // Assign every catalog project to a department.
        $deptByProject = [
            1 => 'PB-PT', 2 => 'PB-PT', 3 => 'PB-TK', 4 => 'PB-PT',
            5 => 'PB-PT', 6 => 'PB-VH', 7 => 'PB-RD', 8 => 'PB-VH',
        ];
        foreach ($deptByProject as $id => $code) {
            Project::whereKey($id)->update(['department_id' => $departments[$code]->id]);
        }

        // Spread the 8 catalog projects across the 3 lifecycle types so the
        // portfolio Kanban has cards in every column out of the box.
        $typeByProject = [
            1 => 'deployment', 2 => 'rnd', 3 => 'deployment', 4 => 'rnd',
            5 => 'rnd', 6 => 'operation', 7 => 'rnd', 8 => 'operation',
        ];
        foreach ($typeByProject as $id => $type) {
            Project::whereKey($id)->update(['type' => $type]);
        }

        $portal->update([
            'description' => 'Cổng thông tin tổng hợp cho học sinh, phụ huynh và giáo viên VAschools.',
            'status' => ProjectStatus::Active->value,
            'type' => 'deployment',
            'department_id' => $departments['PB-PT']->id,
            'start_date' => (clone $base)->subDays(10),
            'due_date' => (clone $base)->addMonths(2),
            'budget' => 120_000_000,
            'manager_id' => $team['lead']->id,
        ]);
        $qlda->update([
            'description' => 'Ứng dụng quản lý dự án nội bộ của Phòng Công nghệ.',
            'status' => ProjectStatus::Active->value,
            'type' => 'rnd',
            'department_id' => $departments['PB-PT']->id,
            'start_date' => (clone $base)->subDays(5),
            'due_date' => (clone $base)->addMonths(3),
            'budget' => 90_000_000,
            'manager_id' => $team['admin']->id,
        ]);

        // --- Members + rates ------------------------------------------------
        $this->addMember($portal, $team['member'], 'developer', RateType::Hourly, 80_000);
        $this->addMember($portal, $team['backend'], 'developer', RateType::Hourly, 75_000);
        $this->addMember($portal, $team['frontend'], 'developer', RateType::Monthly, 18_000_000);
        $this->addMember($portal, $team['qa'], 'qa', RateType::Hourly, 60_000);
        $this->addMember($portal, $team['lead'], 'pm', RateType::Monthly, 30_000_000);

        $this->addMember($qlda, $team['member'], 'developer', RateType::Hourly, 80_000);
        $this->addMember($qlda, $team['designer'], 'designer', RateType::Hourly, 65_000);
        $this->addMember($qlda, $team['admin'], 'pm', RateType::Monthly, 35_000_000);

        // --- Sprints --------------------------------------------------------
        $portalSprint1 = Sprint::create(['project_id' => $portal->id, 'name' => 'Sprint 1 — Nền tảng', 'goal' => 'Khung dự án & xác thực', 'status' => 'completed', 'start_date' => (clone $base)->subDays(10), 'end_date' => (clone $base)->addDays(4), 'sort_order' => 1]);
        $portalSprint2 = Sprint::create(['project_id' => $portal->id, 'name' => 'Sprint 2 — Tính năng', 'goal' => 'Trang chủ & thông báo', 'status' => 'active', 'start_date' => (clone $base)->addDays(5), 'end_date' => (clone $base)->addDays(18), 'sort_order' => 2]);
        $qldaSprint1 = Sprint::create(['project_id' => $qlda->id, 'name' => 'Sprint 1 — MVP', 'goal' => 'Quản lý dự án cơ bản', 'status' => 'active', 'start_date' => (clone $base)->subDays(5), 'end_date' => (clone $base)->addDays(10), 'sort_order' => 1]);

        // --- Tasks (scheduled for the Gantt) --------------------------------
        $t1 = $this->task($portal, $portalSprint1, 'Thiết lập kiến trúc & CI', $team['backend'], $team['lead'], (clone $base)->subDays(10), (clone $base)->subDays(6), 'done', 'high', 100, 16);
        $t2 = $this->task($portal, $portalSprint1, 'Xác thực & phân quyền', $team['backend'], $team['lead'], (clone $base)->subDays(6), (clone $base)->subDays(1), 'done', 'high', 100, 20);
        $t3 = $this->task($portal, $portalSprint2, 'Trang chủ cổng thông tin', $team['frontend'], $team['lead'], (clone $base)->addDays(5), (clone $base)->addDays(12), 'in_progress', 'high', 45, 24);
        $t4 = $this->task($portal, $portalSprint2, 'Module thông báo', $team['member'], $team['lead'], (clone $base)->addDays(8), (clone $base)->addDays(16), 'todo', 'medium', 0, 18);
        $t5 = $this->task($portal, $portalSprint2, 'Kiểm thử & QA vòng 1', $team['qa'], $team['lead'], (clone $base)->addDays(13), (clone $base)->addDays(18), 'todo', 'medium', 0, 12);

        $t2->dependencies()->attach($t1->id);
        $t3->dependencies()->attach($t2->id);
        $t5->dependencies()->attach([$t3->id, $t4->id]);

        $q1 = $this->task($qlda, $qldaSprint1, 'Mô hình dữ liệu dự án', $team['member'], $team['admin'], (clone $base)->subDays(5), (clone $base)->subDays(1), 'done', 'high', 100, 14);
        $q2 = $this->task($qlda, $qldaSprint1, 'Biểu đồ Gantt & timeline', $team['member'], $team['admin'], (clone $base)->addDays(0), (clone $base)->addDays(6), 'in_progress', 'urgent', 60, 20);
        $q3 = $this->task($qlda, $qldaSprint1, 'Giao diện bảng Kanban', $team['designer'], $team['admin'], (clone $base)->addDays(2), (clone $base)->addDays(9), 'in_progress', 'medium', 30, 16);
        $q2->dependencies()->attach($q1->id);
        $q3->dependencies()->attach($q1->id);

        // --- Worklogs (drive labour cost) -----------------------------------
        $this->logWork($t1, $team['backend'], 16, (clone $base)->subDays(8));
        $this->logWork($t2, $team['backend'], 20, (clone $base)->subDays(3));
        $this->logWork($t3, $team['frontend'], 10, (clone $base)->addDays(6));
        $this->logWork($t3, $team['member'], 4, (clone $base)->addDays(7));
        $this->logWork($q1, $team['member'], 14, (clone $base)->subDays(2));
        $this->logWork($q2, $team['member'], 12, (clone $base)->addDays(2));
        $this->logWork($q3, $team['designer'], 6, (clone $base)->addDays(3));

        // --- Blockers -------------------------------------------------------
        Blocker::create(['project_id' => $portal->id, 'task_id' => $t3->id, 'title' => 'Thiếu API dữ liệu tin tức', 'description' => 'Chưa có endpoint cung cấp tin tức cho trang chủ.', 'severity' => 'high', 'status' => 'open', 'raised_by_id' => $team['frontend']->id, 'owner_id' => $team['backend']->id, 'raised_at' => Carbon::now()->subDays(2)]);
        Blocker::create(['project_id' => $qlda->id, 'title' => 'Hiệu năng truy vấn timeline', 'description' => 'Truy vấn Gantt chậm khi nhiều công việc.', 'severity' => 'medium', 'status' => 'in_progress', 'raised_by_id' => $team['member']->id, 'owner_id' => $team['admin']->id, 'raised_at' => Carbon::now()->subDays(1)]);

        // --- Bugs (internal + external reporters) ---------------------------
        $bug1 = Bug::create(['project_id' => $portal->id, 'task_id' => $t3->id, 'title' => 'Nút đăng nhập không phản hồi trên Safari', 'description' => 'Bấm đăng nhập không có gì xảy ra.', 'steps_to_reproduce' => "1. Mở Safari\n2. Bấm Đăng nhập", 'expected' => 'Chuyển tới trang chủ', 'actual' => 'Không phản hồi', 'environment' => 'Safari 17 / macOS', 'severity' => 'major', 'priority' => 'high', 'status' => 'in_progress', 'reporter_employee_id' => $team['qa']->id, 'assignee_id' => $team['frontend']->id]);
        $bug2 = Bug::create(['project_id' => $portal->id, 'title' => 'Sai chính tả ở trang liên hệ', 'description' => 'Lỗi chính tả "Liên hêj".', 'severity' => 'trivial', 'priority' => 'low', 'status' => 'open', 'reporter_name' => 'Phụ huynh Nguyễn', 'reporter_email' => 'parent@example.com', 'assignee_id' => $team['member']->id]);
        Bug::create(['project_id' => $qlda->id, 'title' => 'Tổng chi phí tính sai khi xoá worklog', 'description' => 'Chi phí không cập nhật sau khi xoá.', 'severity' => 'critical', 'priority' => 'urgent', 'status' => 'open', 'reporter_employee_id' => $team['admin']->id, 'assignee_id' => $team['member']->id]);

        $bug1->comments()->create(['employee_id' => $team['frontend']->id, 'body' => 'Đã tái hiện được, đang kiểm tra sự kiện click bị chặn.']);
        $bug1->comments()->create(['employee_id' => $team['qa']->id, 'body' => 'Cảm ơn, mình sẽ test lại sau khi có bản vá.']);

        // --- Feedback (người dùng ↔ người sửa) ------------------------------
        $fb1 = Feedback::create(['project_id' => $portal->id, 'category' => 'feature_request', 'title' => 'Thêm đăng nhập bằng Google', 'description' => 'Mong có đăng nhập nhanh bằng tài khoản Google.', 'rating' => 4, 'priority' => 'medium', 'status' => 'under_review', 'reporter_name' => 'Cô Lan (Giáo viên)', 'reporter_email' => 'lan@example.com', 'assignee_id' => $team['lead']->id]);
        $fb2 = Feedback::create(['project_id' => $portal->id, 'category' => 'complaint', 'title' => 'Trang tải chậm vào giờ cao điểm', 'description' => 'Buổi sáng truy cập rất chậm.', 'rating' => 2, 'priority' => 'high', 'status' => 'in_progress', 'reporter_name' => 'Phụ huynh Trần', 'reporter_email' => 'tran@example.com', 'assignee_id' => $team['backend']->id]);
        Feedback::create(['project_id' => $qlda->id, 'category' => 'praise', 'title' => 'Giao diện Gantt rất trực quan', 'description' => 'Rất thích biểu đồ tiến độ mới.', 'rating' => 5, 'priority' => 'low', 'status' => 'resolved', 'reporter_employee_id' => $team['admin']->id, 'assignee_id' => $team['member']->id, 'resolved_at' => Carbon::now()->subDay()]);

        $fb1->comments()->create(['employee_id' => $team['lead']->id, 'body' => 'Cảm ơn góp ý, đã đưa vào kế hoạch sprint sau.']);
        $fb2->comments()->create(['employee_id' => $team['backend']->id, 'body' => 'Đang tối ưu cache, dự kiến cải thiện trong tuần này.']);
    }

    /**
     * @return array<string, Employee>
     */
    private function ensureTeam(): array
    {
        $extra = [
            'backend' => ['code' => 'EMP-005', 'name' => 'Đỗ Văn Backend', 'title' => 'Backend Developer'],
            'frontend' => ['code' => 'EMP-006', 'name' => 'Vũ Thị Frontend', 'title' => 'Frontend Developer'],
            'qa' => ['code' => 'EMP-007', 'name' => 'Bùi Văn Kiểm', 'title' => 'QA Engineer'],
            'designer' => ['code' => 'EMP-008', 'name' => 'Hồ Thị Thiết Kế', 'title' => 'UI/UX Designer'],
        ];

        $team = [
            'admin' => Employee::where('code', 'EMP-001')->first(),
            'lead' => Employee::where('code', 'EMP-002')->first(),
            'member' => Employee::where('code', 'EMP-003')->first(),
        ];

        foreach ($extra as $key => $p) {
            $team[$key] = Employee::firstOrCreate(
                ['code' => $p['code']],
                ['full_name' => $p['name'], 'role_title' => $p['title'], 'join_date' => now()->subMonths(4), 'skills' => ['php', 'vue'], 'is_active' => true],
            );
        }

        return $team;
    }

    /**
     * @param  array<string, Employee>  $team
     * @return array<string, Department>
     */
    private function ensureDepartments(array $team): array
    {
        $defs = [
            'PB-RD' => ['name' => 'Phòng Nghiên cứu (R&D)', 'color' => 'violet', 'manager' => 'admin', 'sort' => 1],
            'PB-PT' => ['name' => 'Phòng Phát triển', 'color' => 'sky', 'manager' => 'lead', 'sort' => 2],
            'PB-TK' => ['name' => 'Phòng Triển khai', 'color' => 'amber', 'manager' => 'lead', 'sort' => 3],
            'PB-VH' => ['name' => 'Phòng Vận hành', 'color' => 'emerald', 'manager' => 'backend', 'sort' => 4],
        ];

        $departments = [];
        foreach ($defs as $code => $d) {
            $departments[$code] = Department::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $d['name'],
                    'color' => $d['color'],
                    'manager_id' => $team[$d['manager']]->id ?? null,
                    'sort_order' => $d['sort'],
                    'is_active' => true,
                ],
            );
        }

        return $departments;
    }

    private function addMember(Project $project, Employee $employee, string $role, RateType $type, float $rate): void
    {
        $project->members()->syncWithoutDetaching([
            $employee->id => [
                'role' => $role,
                'rate_type' => $type->value,
                'rate' => $rate,
                'allocation' => 100,
                'joined_at' => now()->subMonths(2)->toDateString(),
                'is_active' => true,
            ],
        ]);
    }

    private function task(Project $project, Sprint $sprint, string $title, Employee $assignee, Employee $reporter, Carbon $start, Carbon $due, string $status, string $priority, int $progress, float $estimate): Task
    {
        return Task::create([
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
            'title' => $title,
            'status' => $status,
            'priority' => $priority,
            'assignee_id' => $assignee->id,
            'reporter_id' => $reporter->id,
            'start_date' => $start->toDateString(),
            'due_date' => $due->toDateString(),
            'estimate_hours' => $estimate,
            'progress' => $progress,
            'order_column' => 0,
        ]);
    }

    private function logWork(Task $task, Employee $employee, float $hours, Carbon $date): void
    {
        $rate = $this->effectiveHourlyRate($task->project_id, $employee->id);

        $task->worklogs()->create([
            'employee_id' => $employee->id,
            'date' => $date->toDateString(),
            'hours' => $hours,
            'rate_snapshot' => $rate,
            'cost' => $rate !== null ? round($rate * $hours, 2) : null,
        ]);
    }

    private function effectiveHourlyRate(int $projectId, int $employeeId): ?float
    {
        $pivot = Project::find($projectId)?->members()->where('employee_id', $employeeId)->first()?->pivot;
        if ($pivot === null || $pivot->rate === null) {
            return null;
        }

        $rate = (float) $pivot->rate;

        return $pivot->rate_type === RateType::Monthly->value
            ? round($rate / Project::MONTHLY_HOURS, 2)
            : $rate;
    }
}
