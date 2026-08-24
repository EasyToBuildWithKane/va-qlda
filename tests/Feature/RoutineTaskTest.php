<?php

namespace Tests\Feature;

use App\Domain\DailyReport\Models\DailyReport;
use App\Domain\DailyReport\Support\ReportProjectSync;
use App\Domain\DailyReport\Support\ReportProjectTaskStatus;
use App\Domain\RoutineTask\Models\RoutineTask;
use App\Domain\RoutineTask\Models\RoutineTaskAttachment;
use App\Models\Employee;
use App\Models\OrgTeam;
use App\Models\OrgTeamMember;
use App\Models\SystemAccount;
use App\Support\Enums\ReportStatus;
use App\Support\Enums\SystemRole;
use App\Support\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoutineTaskTest extends TestCase
{
    use RefreshDatabase;

    private function accountFor(Employee $employee, SystemRole $role): SystemAccount
    {
        return SystemAccount::factory()->forEmployee($employee)->role($role)->create();
    }

    public function test_member_can_list_own_routine_tasks(): void
    {
        $employee = Employee::factory()->create();
        $account = $this->accountFor($employee, SystemRole::Member);

        RoutineTask::query()->create([
            'employee_id' => $employee->id,
            'title' => 'Họp daily',
            'status' => TaskStatus::Todo,
            'position' => 0,
        ]);

        $this->actingAs($account, 'system')
            ->get(route('routine-tasks.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('RoutineTask/Index')
                ->where('summary.total', 1)
                ->where('tasks.0.title', 'Họp daily')
                ->where('viewer.is_self', true));
    }

    public function test_member_can_create_update_toggle_and_delete(): void
    {
        $employee = Employee::factory()->create();
        $account = $this->accountFor($employee, SystemRole::Member);

        $this->actingAs($account, 'system')
            ->post(route('routine-tasks.store'), ['title' => 'Trả lời email'])
            ->assertRedirect();

        $task = RoutineTask::query()->forEmployee($employee->id)->first();
        $this->assertNotNull($task);
        $this->assertSame('Trả lời email', $task->title);
        $this->assertSame(TaskStatus::Todo, $task->status);

        $this->actingAs($account, 'system')
            ->put(route('routine-tasks.update', $task), ['title' => 'Email nội bộ'])
            ->assertRedirect();

        $this->assertSame('Email nội bộ', $task->fresh()->title);

        $this->actingAs($account, 'system')
            ->post(route('routine-tasks.toggle-status', $task))
            ->assertRedirect();
        $this->assertSame(TaskStatus::InProgress, $task->fresh()->status);

        $this->actingAs($account, 'system')
            ->post(route('routine-tasks.toggle-status', $task))
            ->assertRedirect();
        $this->assertSame(TaskStatus::Done, $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);

        $this->actingAs($account, 'system')
            ->post(route('routine-tasks.toggle-status', $task))
            ->assertRedirect();
        $this->assertSame(TaskStatus::Todo, $task->fresh()->status);
        $this->assertNull($task->fresh()->completed_at);

        $this->actingAs($account, 'system')
            ->delete(route('routine-tasks.destroy', $task))
            ->assertRedirect();

        $this->assertDatabaseMissing('routine_tasks', ['id' => $task->id]);
    }

    public function test_member_cannot_mutate_another_employees_task(): void
    {
        $owner = Employee::factory()->create();
        $other = Employee::factory()->create();
        $account = $this->accountFor($other, SystemRole::Member);

        $task = RoutineTask::query()->create([
            'employee_id' => $owner->id,
            'title' => 'Việc của người khác',
            'status' => TaskStatus::Todo,
            'position' => 0,
        ]);

        $this->actingAs($account, 'system')
            ->put(route('routine-tasks.update', $task), ['title' => 'Hack'])
            ->assertForbidden();

        $this->actingAs($account, 'system')
            ->delete(route('routine-tasks.destroy', $task))
            ->assertForbidden();
    }

    public function test_member_cannot_view_another_employees_list(): void
    {
        $owner = Employee::factory()->create();
        $other = Employee::factory()->create();
        $account = $this->accountFor($other, SystemRole::Member);

        RoutineTask::query()->create([
            'employee_id' => $owner->id,
            'title' => 'Bí mật',
            'status' => TaskStatus::Todo,
            'position' => 0,
        ]);

        $this->actingAs($account, 'system')
            ->get(route('routine-tasks.index', ['employee' => $owner->id]))
            ->assertForbidden();
    }

    public function test_lead_can_view_another_employees_list(): void
    {
        $owner = Employee::factory()->create();
        $lead = Employee::factory()->create();
        $account = $this->accountFor($lead, SystemRole::TeamLeader);

        RoutineTask::query()->create([
            'employee_id' => $owner->id,
            'title' => 'Việc của member',
            'status' => TaskStatus::InProgress,
            'position' => 0,
        ]);

        $this->actingAs($account, 'system')
            ->get(route('routine-tasks.index', ['employee' => $owner->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('viewer.is_self', false)
                ->where('tasks.0.title', 'Việc của member')
                ->where('summary.total', 1));
    }

    public function test_team_leader_can_view_direct_report_list(): void
    {
        $leader = Employee::factory()->create();
        $member = Employee::factory()->create();
        $account = $this->accountFor($leader, SystemRole::Member);

        $team = OrgTeam::create([
            'name' => 'Nhóm A',
            'level' => 1,
            'leader_id' => $leader->id,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        OrgTeamMember::create([
            'org_team_id' => $team->id,
            'employee_id' => $member->id,
            'sort_order' => 0,
        ]);

        RoutineTask::query()->create([
            'employee_id' => $member->id,
            'title' => 'Báo cáo cho lead',
            'status' => TaskStatus::Todo,
            'position' => 0,
        ]);

        $this->actingAs($account, 'system')
            ->get(route('routine-tasks.index', ['employee' => $member->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('viewer.is_self', false)
                ->where('tasks.0.title', 'Báo cáo cho lead'));
    }

    public function test_store_computes_actual_hours_from_start_end(): void
    {
        $employee = Employee::factory()->create();
        $account = $this->accountFor($employee, SystemRole::Member);

        $this->actingAs($account, 'system')
            ->post(route('routine-tasks.store'), [
                'title' => 'Họp standup',
                'work_date' => '2026-08-13',
                'start_time' => '09:00',
                'end_time' => '10:30',
                'estimate_hours' => 2,
                'blockers' => 'Chờ tài liệu',
                'progress_percent' => 40,
            ])
            ->assertRedirect();

        $task = RoutineTask::query()->forEmployee($employee->id)->first();
        $this->assertNotNull($task);
        $this->assertSame('2026-08-13', $task->work_date?->toDateString());
        $this->assertSame('09:00', $task->started_at?->format('H:i'));
        $this->assertSame('10:30', $task->ended_at?->format('H:i'));
        $this->assertEquals(2.0, (float) $task->estimate_hours);
        $this->assertEquals(1.5, (float) $task->actual_hours);
        $this->assertSame(40, (int) $task->progress_percent);
        $this->assertSame('Chờ tài liệu', $task->blockers);
    }

    public function test_store_accepts_attachment(): void
    {
        Storage::fake('public');
        $employee = Employee::factory()->create();
        $account = $this->accountFor($employee, SystemRole::Member);
        $file = UploadedFile::fake()->create('bien-ban.pdf', 80, 'application/pdf');

        $this->actingAs($account, 'system')
            ->post(route('routine-tasks.store'), [
                'title' => 'Họp tuần',
                'files' => [$file],
            ])
            ->assertRedirect();

        $task = RoutineTask::query()->forEmployee($employee->id)->first();
        $this->assertNotNull($task);
        $attachment = RoutineTaskAttachment::query()->where('routine_task_id', $task->id)->first();
        $this->assertNotNull($attachment);
        $this->assertSame('bien-ban.pdf', $attachment->original_name);
        Storage::disk('public')->assertExists($attachment->path);

        $this->actingAs($account, 'system')
            ->get(route('routine-tasks.attachments.file', [
                'routineTask' => $task->id,
                'attachment' => $attachment->id,
            ]))
            ->assertOk();
    }

    public function test_reorder_updates_positions(): void
    {
        $employee = Employee::factory()->create();
        $account = $this->accountFor($employee, SystemRole::Member);

        $a = RoutineTask::query()->create([
            'employee_id' => $employee->id,
            'title' => 'A',
            'status' => TaskStatus::Todo,
            'position' => 0,
        ]);
        $b = RoutineTask::query()->create([
            'employee_id' => $employee->id,
            'title' => 'B',
            'status' => TaskStatus::Todo,
            'position' => 1,
        ]);

        $this->actingAs($account, 'system')
            ->post(route('routine-tasks.reorder'), ['ids' => [$b->id, $a->id]])
            ->assertRedirect();

        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
    }

    public function test_daily_report_save_syncs_routine_tasks_and_replaces_local_key(): void
    {
        $employee = Employee::factory()->create();
        $account = $this->accountFor($employee, SystemRole::Member);

        $this->actingAs($account, 'system')
            ->post(route('daily-reports.store'), [
                'date' => now()->toDateString(),
                'title' => 'Báo cáo có việc thường xuyên',
                'projects' => [
                    [
                        'id' => ReportProjectSync::ROUTINE_PROJECT_ID,
                        'name' => 'Công việc thường xuyên',
                        'tasks' => [
                            [
                                'id' => 0,
                                'title' => 'Họp standup',
                                'status' => 'todo',
                                '_localKey' => 'routine-local-1',
                            ],
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $report = DailyReport::query()->where('employee_id', $employee->id)->first();
        $this->assertNotNull($report);

        $routine = RoutineTask::query()->forEmployee($employee->id)->where('title', 'Họp standup')->first();
        $this->assertNotNull($routine);

        $report->refresh();
        $this->assertSame(ReportProjectSync::ROUTINE_PROJECT_ID, $report->projects[0]['id']);
        $this->assertSame($routine->id, $report->projects[0]['tasks'][0]['id']);
        $this->assertSame('routine-local-1', $report->projects[0]['tasks'][0]['_localKey']);
    }

    public function test_repeated_daily_report_save_does_not_duplicate_routine_task(): void
    {
        $employee = Employee::factory()->create();
        $account = $this->accountFor($employee, SystemRole::Member);

        $payload = [
            'projects' => [
                [
                    'id' => ReportProjectSync::ROUTINE_PROJECT_ID,
                    'name' => 'Công việc thường xuyên',
                    'tasks' => [
                        [
                            'id' => 0,
                            'title' => 'Email nội bộ',
                            'status' => 'todo',
                            '_localKey' => 'routine-dup',
                        ],
                    ],
                ],
            ],
        ];

        $report = DailyReport::factory()->create([
            'employee_id' => $employee->id,
            'projects' => $payload['projects'],
        ]);

        $this->actingAs($account, 'system')
            ->put(route('daily-reports.update', $report), $payload)
            ->assertRedirect();

        $this->actingAs($account, 'system')
            ->put(route('daily-reports.update', $report), $payload)
            ->assertRedirect();

        $this->assertSame(
            1,
            RoutineTask::query()->forEmployee($employee->id)->where('title', 'Email nội bộ')->count(),
        );
    }

    public function test_freeze_into_report_reads_routine_task_status(): void
    {
        $employee = Employee::factory()->create();

        $routine = RoutineTask::query()->create([
            'employee_id' => $employee->id,
            'title' => 'Vận hành',
            'status' => TaskStatus::Done,
            'position' => 0,
            'completed_at' => now(),
        ]);

        $report = DailyReport::factory()->create([
            'employee_id' => $employee->id,
            'status' => ReportStatus::Draft,
            'projects' => [
                [
                    'id' => ReportProjectSync::ROUTINE_PROJECT_ID,
                    'name' => 'Công việc thường xuyên',
                    'tasks' => [
                        [
                            'id' => $routine->id,
                            'title' => 'Vận hành',
                            'status' => 'todo',
                        ],
                    ],
                ],
            ],
        ]);

        ReportProjectTaskStatus::freezeIntoReport($report->fresh());

        $this->assertSame('done', $report->fresh()->projects[0]['tasks'][0]['status']);
    }

    public function test_toggle_mirrors_status_into_today_draft_report(): void
    {
        $employee = Employee::factory()->create();
        $account = $this->accountFor($employee, SystemRole::Member);

        $routine = RoutineTask::query()->create([
            'employee_id' => $employee->id,
            'title' => 'Standup',
            'status' => TaskStatus::Todo,
            'position' => 0,
        ]);

        $report = DailyReport::factory()->create([
            'employee_id' => $employee->id,
            'date' => now()->toDateString(),
            'status' => ReportStatus::Draft,
            'projects' => [
                [
                    'id' => ReportProjectSync::ROUTINE_PROJECT_ID,
                    'name' => 'Công việc thường xuyên',
                    'tasks' => [
                        ['id' => $routine->id, 'title' => 'Standup', 'status' => 'todo'],
                    ],
                ],
            ],
        ]);

        $this->actingAs($account, 'system')
            ->post(route('routine-tasks.toggle-status', $routine))
            ->assertRedirect();

        $this->assertSame('in_progress', $report->fresh()->projects[0]['tasks'][0]['status']);
    }
}
