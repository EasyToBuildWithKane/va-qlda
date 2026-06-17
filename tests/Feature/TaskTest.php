<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Admin)->create();
    }

    private function lead(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Lead)->create();
    }

    private function member(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Member)->create();
    }

    private function viewer(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Viewer)->create();
    }

    private function taskPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Test Task',
            'status' => TaskStatus::Todo->value,
            'priority' => TaskPriority::Medium->value,
        ], $overrides);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_admin_can_create_task(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->admin(), 'system')
            ->post("/projects/{$project->id}/tasks", $this->taskPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => 'Test Task',
        ]);
    }

    public function test_lead_can_create_task(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->lead(), 'system')
            ->post("/projects/{$project->id}/tasks", $this->taskPayload(['title' => 'Lead Task']))
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', ['title' => 'Lead Task']);
    }

    public function test_member_cannot_create_task(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->member(), 'system')
            ->post("/projects/{$project->id}/tasks", $this->taskPayload())
            ->assertForbidden();
    }

    public function test_viewer_cannot_create_task(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->viewer(), 'system')
            ->post("/projects/{$project->id}/tasks", $this->taskPayload())
            ->assertForbidden();
    }

    public function test_task_requires_title(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->admin(), 'system')
            ->post("/projects/{$project->id}/tasks", [
                'status' => TaskStatus::Todo->value,
                'priority' => TaskPriority::Medium->value,
            ])
            ->assertSessionHasErrors('title');
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_admin_can_update_task(): void
    {
        $project = Project::factory()->create();
        $task = $project->tasks()->create($this->taskPayload(['title' => 'Old Title']));

        $this->actingAs($this->admin(), 'system')
            ->put("/projects/{$project->id}/tasks/{$task->id}", $this->taskPayload(['title' => 'New Title']))
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'New Title']);
    }

    public function test_admin_can_update_task_dependencies(): void
    {
        $project = Project::factory()->create();
        $predecessor = $project->tasks()->create($this->taskPayload(['title' => 'Predecessor']));
        $task = $project->tasks()->create($this->taskPayload(['title' => 'Successor']));

        $this->actingAs($this->admin(), 'system')
            ->put("/projects/{$project->id}/tasks/{$task->id}", $this->taskPayload([
                'title' => 'Successor',
                'dependencies' => [$predecessor->id],
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('task_dependencies', [
            'task_id' => $task->id,
            'depends_on_id' => $predecessor->id,
        ]);
    }

    public function test_admin_can_put_subtask_estimate_hours_only(): void
    {
        $project = Project::factory()->create();
        $parent = $project->tasks()->create($this->taskPayload([
            'title' => 'Parent',
            'start_date' => '2026-06-01',
            'due_date' => '2026-06-10',
        ]));
        $subtask = $project->tasks()->create(array_merge($this->taskPayload(['title' => 'Child']), [
            'parent_id' => $parent->id,
            'start_date' => $parent->start_date,
            'due_date' => $parent->due_date,
        ]));

        $this->actingAs($this->admin(), 'system')
            ->patch("/projects/{$project->id}/tasks/{$subtask->id}", [
                'estimate_hours' => 4.5,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $subtask->id,
            'estimate_hours' => 4.5,
        ]);
    }

    public function test_parent_assignee_syncs_to_subtasks(): void
    {
        $project = Project::factory()->create();
        $employeeA = Employee::factory()->create();
        $employeeB = Employee::factory()->create();
        $parent = $project->tasks()->create($this->taskPayload(['title' => 'Parent']));
        $subtask = $project->tasks()->create(array_merge($this->taskPayload(['title' => 'Child']), [
            'parent_id' => $parent->id,
        ]));

        $this->actingAs($this->admin(), 'system')
            ->put("/projects/{$project->id}/tasks/{$parent->id}", $this->taskPayload([
                'title' => 'Parent',
                'assignee_ids' => [$employeeA->id, $employeeB->id],
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $subtask->id,
            'assignee_id' => $employeeA->id,
        ]);
        $this->assertDatabaseHas('task_assignees', [
            'task_id' => $subtask->id,
            'employee_id' => $employeeA->id,
        ]);
        $this->assertDatabaseHas('task_assignees', [
            'task_id' => $subtask->id,
            'employee_id' => $employeeB->id,
        ]);
    }

    public function test_subtask_create_inherits_parent_assignees(): void
    {
        $project = Project::factory()->create();
        $employee = Employee::factory()->create();
        $parent = $project->tasks()->create($this->taskPayload([
            'title' => 'Parent',
            'assignee_id' => $employee->id,
        ]));
        $parent->assignees()->sync([$employee->id]);

        $this->actingAs($this->admin(), 'system')
            ->post("/projects/{$project->id}/tasks/{$parent->id}/subtasks", [
                'title' => 'Child via API',
            ])
            ->assertRedirect();

        $subtask = $project->tasks()->where('title', 'Child via API')->firstOrFail();
        $this->assertSame($employee->id, $subtask->assignee_id);
        $this->assertDatabaseHas('task_assignees', [
            'task_id' => $subtask->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_member_cannot_update_task(): void
    {
        $project = Project::factory()->create();
        $task = $project->tasks()->create($this->taskPayload());

        $this->actingAs($this->member(), 'system')
            ->put("/projects/{$project->id}/tasks/{$task->id}", $this->taskPayload(['title' => 'Hacked']))
            ->assertForbidden();
    }

    // ─── Status patch ─────────────────────────────────────────────────────────

    public function test_task_deep_link_redirects_to_project_show_with_query(): void
    {
        $project = Project::factory()->create();
        $task = $project->tasks()->create($this->taskPayload());

        $this->actingAs($this->admin(), 'system')
            ->get("/projects/{$project->id}/tasks/{$task->id}")
            ->assertRedirect("/projects/{$project->id}?".http_build_query(['tab' => 'sprints', 'task' => $task->id]));
    }

    public function test_admin_can_patch_task_status(): void
    {
        $project = Project::factory()->create();
        $task = $project->tasks()->create($this->taskPayload());

        $this->actingAs($this->admin(), 'system')
            ->patch("/projects/{$project->id}/tasks/{$task->id}", [
                'status' => TaskStatus::InProgress->value,
            ])
            ->assertRedirect();

        $this->actingAs($this->admin(), 'system')
            ->patch("/projects/{$project->id}/tasks/{$task->id}", [
                'status' => TaskStatus::Done->value,
                'actual_hours' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Done->value,
            'actual_hours' => '2.00',
        ]);
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_task(): void
    {
        $project = Project::factory()->create();
        $task = $project->tasks()->create($this->taskPayload());

        $this->actingAs($this->admin(), 'system')
            ->delete("/projects/{$project->id}/tasks/{$task->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_viewer_cannot_delete_task(): void
    {
        $project = Project::factory()->create();
        $task = $project->tasks()->create($this->taskPayload());

        $this->actingAs($this->viewer(), 'system')
            ->delete("/projects/{$project->id}/tasks/{$task->id}")
            ->assertForbidden();
    }

    // ─── Bulk & Import ────────────────────────────────────────────────────────

    public function test_admin_can_bulk_create_tasks(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->admin(), 'system')
            ->post("/projects/{$project->id}/tasks/bulk", [
                'defaults' => [
                    'status' => TaskStatus::Todo->value,
                    'priority' => TaskPriority::Medium->value,
                ],
                'rows' => [
                    ['title' => 'Bulk Task 1'],
                    ['title' => 'Bulk Task 2'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('tasks', ['project_id' => $project->id, 'title' => 'Bulk Task 1']);
        $this->assertDatabaseHas('tasks', ['project_id' => $project->id, 'title' => 'Bulk Task 2']);
    }

    public function test_admin_can_import_tasks_from_excel_payload(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->admin(), 'system')
            ->post("/projects/{$project->id}/tasks/import", [
                'rows' => [
                    [
                        'title' => 'Imported Task A',
                        'status' => TaskStatus::Todo->value,
                        'priority' => TaskPriority::High->value,
                        'progress' => 0,
                    ],
                    [
                        'title' => 'Imported Task B',
                        'status' => TaskStatus::InProgress->value,
                        'priority' => TaskPriority::Medium->value,
                        'progress' => 25,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => 'Imported Task A',
            'priority' => TaskPriority::High->value,
        ]);
        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => 'Imported Task B',
            'progress' => 33,
        ]);
    }

    public function test_imported_assignee_is_mirrored_into_assignees_pivot(): void
    {
        $project = Project::factory()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($this->admin(), 'system')
            ->post("/projects/{$project->id}/tasks/import", [
                'rows' => [
                    ['title' => 'Imported with assignee', 'assignee_id' => $employee->id],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $task = $project->tasks()->where('title', 'Imported with assignee')->firstOrFail();

        $this->assertSame($employee->id, $task->assignee_id);
        $this->assertDatabaseHas('task_assignees', [
            'task_id' => $task->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_import_rejects_due_date_before_start_date(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->admin(), 'system')
            ->post("/projects/{$project->id}/tasks/import", [
                'rows' => [
                    ['title' => 'Bad dates', 'start_date' => '2026-06-10', 'due_date' => '2026-06-01'],
                ],
            ])
            ->assertSessionHasErrors(['rows.0.due_date']);

        $this->assertDatabaseMissing('tasks', [
            'project_id' => $project->id,
            'title' => 'Bad dates',
        ]);
    }

    public function test_viewer_cannot_import_tasks(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->viewer(), 'system')
            ->post("/projects/{$project->id}/tasks/import", [
                'rows' => [['title' => 'Blocked Import']],
            ])
            ->assertForbidden();
    }

    // ─── Hoàn thành & khóa trạng thái ─────────────────────────────────────────

    public function test_complete_task_requires_actual_hours(): void
    {
        $project = Project::factory()->create();
        $task = $project->tasks()->create($this->taskPayload([
            'status' => TaskStatus::InProgress->value,
            'estimate_hours' => 4,
        ]));

        $this->actingAs($this->admin(), 'system')
            ->patch("/projects/{$project->id}/tasks/{$task->id}", [
                'status' => TaskStatus::Done->value,
            ])
            ->assertSessionHasErrors('actual_hours');
    }

    public function test_complete_task_stores_sla_and_activity(): void
    {
        $project = Project::factory()->create();
        $task = $project->tasks()->create($this->taskPayload([
            'status' => TaskStatus::InProgress->value,
            'estimate_hours' => 6,
            'work_started_at' => now()->subHours(2),
        ]));

        $this->actingAs($this->admin(), 'system')
            ->patch("/projects/{$project->id}/tasks/{$task->id}", [
                'status' => TaskStatus::Done->value,
                'actual_hours' => 5,
                'completion_note' => 'Xong đúng hạn',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame(TaskStatus::Done, $task->status);
        $this->assertSame('5.00', $task->actual_hours);
        $this->assertNotNull($task->completed_at);
        $this->assertSame('early', $task->hours_timing);
        $this->assertSame('met', $task->sla_result);

        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'event' => 'completed',
        ]);
    }

    public function test_member_cannot_reopen_done_task(): void
    {
        $project = Project::factory()->create();
        $task = $project->tasks()->create($this->taskPayload([
            'status' => TaskStatus::Done->value,
            'actual_hours' => 3,
            'completed_at' => now(),
            'sla_result' => 'met',
        ]));

        $this->actingAs($this->lead(), 'system')
            ->patch("/projects/{$project->id}/tasks/{$task->id}", [
                'status' => TaskStatus::InProgress->value,
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_admin_can_reopen_done_task(): void
    {
        $project = Project::factory()->create();
        $task = $project->tasks()->create($this->taskPayload([
            'status' => TaskStatus::Done->value,
            'actual_hours' => 3,
            'completed_at' => now(),
            'hours_timing' => 'on_plan',
            'sla_result' => 'met',
        ]));

        $this->actingAs($this->admin(), 'system')
            ->patch("/projects/{$project->id}/tasks/{$task->id}", [
                'status' => TaskStatus::InProgress->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame(TaskStatus::InProgress, $task->status);
        $this->assertNull($task->actual_hours);
        $this->assertNull($task->sla_result);
    }

    // ─── Guest ────────────────────────────────────────────────────────────────

    public function test_guest_cannot_create_task(): void
    {
        $project = Project::factory()->create();

        $this->post("/projects/{$project->id}/tasks", $this->taskPayload())
            ->assertRedirect('/tech/login');
    }
}
