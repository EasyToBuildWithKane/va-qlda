<?php

namespace Tests\Feature;

use App\Application\DailyReport\SubmitDailyReportUseCase;
use App\Domain\DailyReport\Models\DailyReport;
use App\Events\TaskStatusChanged;
use App\Listeners\SyncTaskStatusToDaily;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Models\Task;
use App\Support\Enums\ReportStatus;
use App\Support\Enums\SystemRole;
use App\Support\Enums\TaskSource;
use App\Support\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DailyReportTaskSyncTest extends TestCase
{
    use RefreshDatabase;

    private function attachMemberToProject(SystemAccount $member, Project $project): void
    {
        $project->members()->attach($member->employee_id);
    }

    public function test_spawned_task_from_daily_report_creates_task_row(): void
    {
        $member = SystemAccount::factory()->role(SystemRole::Member)->create();
        $project = Project::factory()->create();
        $this->attachMemberToProject($member, $project);

        $this->actingAs($member, 'system')
            ->post(route('daily-reports.store'), [
                'date' => now()->toDateString(),
                'title' => 'Báo cáo có task phát sinh',
                'projects' => [
                    [
                        'id' => $project->id,
                        'name' => $project->name,
                        'tasks' => [
                            ['id' => 0, 'title' => 'Việc phát sinh trong ngày', '_localKey' => 'spawn-test-1'],
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $report = DailyReport::query()->where('employee_id', $member->employee_id)->first();
        $this->assertNotNull($report);

        $task = Task::query()->where('daily_report_id', $report->id)->first();
        $this->assertNotNull($task);
        $this->assertSame(TaskSource::Daily, $task->source);
        $this->assertSame('Việc phát sinh trong ngày', $task->title);
        $this->assertSame($project->id, $task->project_id);

        $report->refresh();
        $linkedId = $report->projects[0]['tasks'][0]['id'];
        $this->assertSame($task->id, $linkedId);
        $this->assertSame('spawn-test-1', $report->projects[0]['tasks'][0]['_localKey']);
    }

    public function test_repeated_save_with_placeholder_id_does_not_duplicate_spawned_task(): void
    {
        $member = SystemAccount::factory()->role(SystemRole::Member)->create();
        $project = Project::factory()->create();
        $this->attachMemberToProject($member, $project);

        $report = DailyReport::factory()->create([
            'employee_id' => $member->employee_id,
            'projects' => [
                [
                    'id' => $project->id,
                    'name' => $project->name,
                    'tasks' => [['id' => 0, 'title' => 'Việc lặp lại', '_localKey' => 'spawn-dup-key']],
                ],
            ],
        ]);

        $payload = [
            'projects' => [
                [
                    'id' => $project->id,
                    'name' => $project->name,
                    'tasks' => [['id' => 0, 'title' => 'Việc lặp lại', '_localKey' => 'spawn-dup-key']],
                ],
            ],
        ];

        $this->actingAs($member, 'system')
            ->put(route('daily-reports.update', $report), $payload)
            ->assertRedirect();

        $this->actingAs($member, 'system')
            ->put(route('daily-reports.update', $report), $payload)
            ->assertRedirect();

        $this->assertSame(
            1,
            Task::query()
                ->where('daily_report_id', $report->id)
                ->where('title', 'Việc lặp lại')
                ->count(),
        );
    }

    public function test_spawn_rejected_when_member_not_on_project(): void
    {
        $member = SystemAccount::factory()->role(SystemRole::Member)->create();
        $project = Project::factory()->create();

        $this->actingAs($member, 'system')
            ->from('/daily-reports/today')
            ->post(route('daily-reports.store'), [
                'date' => now()->toDateString(),
                'title' => 'Không thuộc dự án',
                'projects' => [
                    [
                        'id' => $project->id,
                        'name' => $project->name,
                        'tasks' => [['id' => 0, 'title' => 'Việc lạ']],
                    ],
                ],
            ])
            ->assertRedirect('/daily-reports/today')
            ->assertSessionHas('error');

        $this->assertSame(0, Task::query()->where('title', 'Việc lạ')->count());
    }

    public function test_submit_freezes_task_status_in_projects_json(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-08 09:00:00'));

        $member = SystemAccount::factory()->role(SystemRole::Member)->create();
        $project = Project::factory()->create();

        $task = Task::query()->create([
            'project_id' => $project->id,
            'title' => 'Live task',
            'status' => TaskStatus::Done,
            'assignee_id' => $member->employee_id,
            'reporter_id' => $member->employee_id,
            'order_column' => 1,
            'progress' => 100,
            'source' => TaskSource::Sprint,
        ]);

        $report = DailyReport::factory()->create([
            'employee_id' => $member->employee_id,
            'goals_today' => 'G',
            'progress_update' => 'P',
            'plan_tomorrow' => 'T',
            'projects' => [
                [
                    'id' => $project->id,
                    'name' => $project->name,
                    'tasks' => [['id' => $task->id, 'title' => $task->title, 'status' => 'todo']],
                ],
            ],
        ]);

        app(SubmitDailyReportUseCase::class)->execute($report);

        $report->refresh();
        $this->assertSame('done', $report->projects[0]['tasks'][0]['status']);
    }

    public function test_done_task_appends_snapshot_on_assignee_daily_report(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-12 15:00:00'));

        $member = SystemAccount::factory()->role(SystemRole::Member)->create();
        $project = Project::factory()->create();
        $report = DailyReport::factory()->create([
            'employee_id' => $member->employee_id,
            'date' => '2026-06-12',
            'status' => ReportStatus::Draft,
        ]);

        $task = Task::query()->create([
            'project_id' => $project->id,
            'title' => 'Sprint task',
            'status' => TaskStatus::InProgress,
            'assignee_id' => $member->employee_id,
            'reporter_id' => $member->employee_id,
            'order_column' => 1,
            'progress' => 50,
            'source' => TaskSource::Sprint,
        ]);

        $task->update(['status' => TaskStatus::Done, 'progress' => 100]);

        (new SyncTaskStatusToDaily)->handle(new TaskStatusChanged($task->fresh()));

        $report->refresh();
        $this->assertIsArray($report->task_status_snapshot);
        $this->assertSame($task->id, $report->task_status_snapshot[0]['task_id']);
        $this->assertSame('done', $report->task_status_snapshot[0]['status']);
        $this->assertArrayNotHasKey('synced_after_submit', $report->task_status_snapshot[0]);
    }

    public function test_submitted_report_marks_snapshot_as_internal_for_reviewers(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-12 16:00:00'));

        $member = SystemAccount::factory()->role(SystemRole::Member)->create();
        $lead = SystemAccount::factory()->role(SystemRole::Lead)->create();
        $project = Project::factory()->create();

        $report = DailyReport::factory()->submitted()->create([
            'employee_id' => $member->employee_id,
            'date' => '2026-06-12',
            'projects' => [
                ['id' => $project->id, 'name' => $project->name, 'tasks' => [['id' => 1, 'title' => 'T', 'status' => 'in_progress']]],
            ],
        ]);

        $task = Task::query()->create([
            'project_id' => $project->id,
            'title' => 'Sprint task',
            'status' => TaskStatus::Done,
            'assignee_id' => $member->employee_id,
            'reporter_id' => $member->employee_id,
            'order_column' => 1,
            'progress' => 100,
            'source' => TaskSource::Sprint,
        ]);

        (new SyncTaskStatusToDaily)->handle(new TaskStatusChanged($task));

        $report->refresh();
        $this->assertTrue($report->task_status_snapshot[0]['synced_after_submit'] ?? false);

        $payload = $this->actingAs($lead, 'system')
            ->get(route('daily-reports.show', $report))
            ->viewData('page')['props']['report'];

        $this->assertSame([], $payload['task_status_snapshot']);

        $ownerPayload = $this->actingAs($member, 'system')
            ->get(route('daily-reports.show', $report))
            ->viewData('page')['props']['report'];

        $this->assertTrue($ownerPayload['task_status_snapshot'][0]['synced_after_submit'] ?? false);
    }
}
