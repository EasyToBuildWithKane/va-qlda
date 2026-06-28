<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SystemAccount;
use App\Models\WeeklyReport;
use App\Support\Enums\SprintStatus;
use App\Support\Enums\SystemRole;
use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskStatus;
use App\Support\Enums\WeeklyReportStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WeeklyReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Admin)->create();
    }

    private function viewer(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Viewer)->create();
    }

    private function projectWithSprint(): array
    {
        $project = Project::factory()->create();
        $sprint = $project->sprints()->create([
            'name' => 'Sprint 1',
            'status' => SprintStatus::Active,
            'start_date' => Carbon::today()->startOfWeek(),
            'end_date' => Carbon::today()->startOfWeek()->addWeeks(3),
            'sort_order' => 1,
        ]);

        $project->tasks()->create([
            'title' => 'Hoàn thiện module A',
            'sprint_id' => $sprint->id,
            'status' => TaskStatus::Done,
            'priority' => TaskPriority::High,
            'is_milestone' => true,
        ]);
        $project->tasks()->create([
            'title' => 'Tích hợp cổng thanh toán',
            'sprint_id' => $sprint->id,
            'status' => TaskStatus::Blocked,
            'priority' => TaskPriority::Urgent,
        ]);

        return [$project, $sprint];
    }

    public function test_admin_can_generate_weekly_report_with_sections_and_kpi(): void
    {
        [$project] = $this->projectWithSprint();

        $this->actingAs($this->admin(), 'system')
            ->post("/projects/{$project->id}/weekly-reports", ['week_number' => 1])
            ->assertRedirect();

        $report = WeeklyReport::query()->where('project_id', $project->id)->first();

        $this->assertNotNull($report);
        $this->assertSame(WeeklyReportStatus::Generated, $report->status);
        $this->assertNotNull($report->executive_summary);
        $this->assertCount(6, $report->sections);
        $this->assertSame(2, $report->kpi_snapshot['total_tasks']);
        $this->assertSame(1, $report->kpi_snapshot['blocked']);
    }

    public function test_regenerate_preserves_user_edited_section(): void
    {
        [$project] = $this->projectWithSprint();
        $admin = $this->admin();

        $this->actingAs($admin, 'system')
            ->post("/projects/{$project->id}/weekly-reports", ['week_number' => 1]);

        $report = WeeklyReport::query()->where('project_id', $project->id)->firstOrFail();

        $this->actingAs($admin, 'system')->put("/projects/{$project->id}/weekly-reports/{$report->id}", [
            'executive_summary' => 'Tóm tắt do người dùng viết.',
            'sections' => [
                ['section' => 'result', 'content' => '• Nội dung tự nhập của tôi.'],
            ],
        ])->assertRedirect();

        $report->refresh();
        $this->assertSame(WeeklyReportStatus::Edited, $report->status);

        $this->actingAs($admin, 'system')
            ->post("/projects/{$project->id}/weekly-reports/{$report->id}/regenerate")
            ->assertRedirect();

        $report->refresh()->load('sections');
        $result = $report->sections->firstWhere('section', \App\Support\Enums\WeeklyReportSection::Result);

        $this->assertTrue($result->is_edited);
        $this->assertSame('• Nội dung tự nhập của tôi.', $result->content);
        $this->assertSame('Tóm tắt do người dùng viết.', $report->executive_summary);
    }

    public function test_viewer_cannot_generate_weekly_report(): void
    {
        [$project] = $this->projectWithSprint();

        $this->actingAs($this->viewer(), 'system')
            ->post("/projects/{$project->id}/weekly-reports", ['week_number' => 1])
            ->assertForbidden();
    }

    public function test_show_page_exposes_weekly_overview(): void
    {
        [$project] = $this->projectWithSprint();

        $this->actingAs($this->admin(), 'system')
            ->get("/projects/{$project->id}?tab=weekly")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('weeklyReports.weeks')
                ->where('weeklyReports.sprint.name', 'Sprint 1')
            );
    }

    public function test_submit_then_approve_locks_report_and_records_version(): void
    {
        [$project] = $this->projectWithSprint();
        $admin = $this->admin();

        $this->actingAs($admin, 'system')->post("/projects/{$project->id}/weekly-reports", ['week_number' => 1]);
        $report = WeeklyReport::query()->where('project_id', $project->id)->firstOrFail();

        $this->actingAs($admin, 'system')->post("/projects/{$project->id}/weekly-reports/{$report->id}/submit")->assertRedirect();
        $this->assertSame(WeeklyReportStatus::Submitted, $report->refresh()->status);

        $this->actingAs($admin, 'system')->post("/projects/{$project->id}/weekly-reports/{$report->id}/approve")->assertRedirect();
        $report->refresh();
        $this->assertSame(WeeklyReportStatus::Approved, $report->status);
        $this->assertTrue($report->isLocked());
        $this->assertGreaterThanOrEqual(2, $report->versions()->count());
    }

    public function test_reject_requires_reason_and_returns_to_rejected(): void
    {
        [$project] = $this->projectWithSprint();
        $admin = $this->admin();

        $this->actingAs($admin, 'system')->post("/projects/{$project->id}/weekly-reports", ['week_number' => 1]);
        $report = WeeklyReport::query()->where('project_id', $project->id)->firstOrFail();
        $this->actingAs($admin, 'system')->post("/projects/{$project->id}/weekly-reports/{$report->id}/submit");

        $this->actingAs($admin, 'system')
            ->post("/projects/{$project->id}/weekly-reports/{$report->id}/reject", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin, 'system')
            ->post("/projects/{$project->id}/weekly-reports/{$report->id}/reject", ['reason' => 'Thiếu số liệu chi tiết'])
            ->assertRedirect();

        $report->refresh();
        $this->assertSame(WeeklyReportStatus::Rejected, $report->status);
        $this->assertSame('Thiếu số liệu chi tiết', $report->reject_reason);
    }

    public function test_export_pdf_and_docx_download(): void
    {
        [$project] = $this->projectWithSprint();
        $admin = $this->admin();

        $this->actingAs($admin, 'system')->post("/projects/{$project->id}/weekly-reports", ['week_number' => 1]);
        $report = WeeklyReport::query()->where('project_id', $project->id)->firstOrFail();

        $this->actingAs($admin, 'system')
            ->get("/projects/{$project->id}/weekly-reports/{$report->id}/export/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin, 'system')
            ->get("/projects/{$project->id}/weekly-reports/{$report->id}/export/docx")
            ->assertOk();
    }
}
