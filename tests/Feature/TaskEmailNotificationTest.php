<?php

namespace Tests\Feature;

use App\Mail\SprintTaskSummaryMail;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\SystemAccount;
use App\Support\Enums\SprintStatus;
use App\Support\Enums\SystemRole;
use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TaskEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('task_email.enabled', true);
    }

    private function admin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Admin)->create();
    }

    public function test_sprint_summary_queues_mail_for_pivot_only_assignees(): void
    {
        Mail::fake();

        $employee = Employee::factory()->create(['email' => 'dev@vaschools.edu.vn']);
        $project = Project::factory()->create();
        $sprint = Sprint::create([
            'project_id' => $project->id,
            'name' => 'Sprint 1',
            'status' => SprintStatus::Active,
            'sort_order' => 1,
        ]);

        $task = $project->tasks()->create([
            'title' => 'Pivot only',
            'sprint_id' => $sprint->id,
            'status' => TaskStatus::Todo->value,
            'priority' => TaskPriority::Medium->value,
            'assignee_id' => null,
        ]);
        $task->assignees()->sync([$employee->id]);

        $this->actingAs($this->admin(), 'system')
            ->post("/projects/{$project->id}/sprints/{$sprint->id}/email/summary")
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertQueued(SprintTaskSummaryMail::class, function (SprintTaskSummaryMail $mail) use ($employee) {
            return $mail->assignee->id === $employee->id && $mail->tasks->count() === 1;
        });
    }

    public function test_sprint_summary_zero_shows_warning_flash(): void
    {
        $project = Project::factory()->create();
        $sprint = Sprint::create([
            'project_id' => $project->id,
            'name' => 'Sprint 1',
            'status' => SprintStatus::Active,
            'sort_order' => 1,
        ]);

        $this->actingAs($this->admin(), 'system')
            ->post("/projects/{$project->id}/sprints/{$sprint->id}/email/summary")
            ->assertRedirect()
            ->assertSessionHas('warning');
    }
}
