<?php

namespace Tests\Feature;

use App\Domain\DailyReport\Models\DailyReport;
use App\Domain\DailyReport\Models\DailyReportScore;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\Enums\ReportStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DailyReportTest extends TestCase
{
    use RefreshDatabase;

    private function member(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Member)->create();
    }

    private function lead(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Lead)->create();
    }

    public function test_projects_json_syncs_legacy_project_id(): void
    {
        $member = $this->member();
        $primary = Project::factory()->create(['name' => 'Primary', 'code' => 'PRJ-A']);
        $secondary = Project::factory()->create(['name' => 'Secondary', 'code' => 'PRJ-B']);

        $this->actingAs($member, 'system')
            ->post(route('daily-reports.store'), [
                'date' => now()->toDateString(),
                'title' => 'Sync test',
                'projects' => [
                    ['id' => $primary->id, 'name' => $primary->name, 'code' => $primary->code],
                    ['id' => $secondary->id, 'name' => $secondary->name, 'code' => $secondary->code],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('daily_reports', [
            'employee_id' => $member->employee_id,
            'project_id' => $primary->id,
        ]);
    }

    public function test_member_creates_a_draft(): void
    {
        $account = $this->member();

        $this->actingAs($account, 'system')
            ->post('/daily-reports', [
                'title' => 'My day',
                'date' => now()->toDateString(),
                'goals_today' => 'Ship the feature',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('daily_reports', [
            'employee_id' => $account->employee_id,
            'title' => 'My day',
            'status' => ReportStatus::Draft->value,
        ]);
    }

    public function test_only_one_report_per_employee_per_day(): void
    {
        $account = $this->member();
        DailyReport::factory()->create([
            'employee_id' => $account->employee_id,
            'date' => now()->toDateString(),
        ]);

        $this->actingAs($account, 'system')
            ->from('/daily-reports/today')
            ->post('/daily-reports', ['title' => 'dup', 'date' => now()->toDateString()])
            ->assertRedirect('/daily-reports/today')
            ->assertSessionHasErrors('date');

        $this->assertSame(1, DailyReport::where('employee_id', $account->employee_id)->count());
    }

    public function test_submit_on_a_working_day_marks_submitted(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-08 09:00:00')); // Monday, before cutoff
        $account = $this->member();
        $report = DailyReport::factory()->create(['employee_id' => $account->employee_id]);

        $this->actingAs($account, 'system')
            ->post("/daily-reports/{$report->id}/submit")
            ->assertRedirect();

        $report->refresh();
        $this->assertSame(ReportStatus::Submitted, $report->status);
        $this->assertFalse($report->is_late);

        Carbon::setTestNow();
    }

    public function test_submission_after_cutoff_is_flagged_late(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-08 19:00:00')); // Monday, after 18:00
        $account = $this->member();
        $report = DailyReport::factory()->create(['employee_id' => $account->employee_id]);

        $this->actingAs($account, 'system')->post("/daily-reports/{$report->id}/submit");

        $this->assertTrue($report->refresh()->is_late);

        Carbon::setTestNow();
    }

    public function test_cannot_submit_on_weekend(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-07 10:00:00')); // Sunday
        $account = $this->member();
        $report = DailyReport::factory()->create(['employee_id' => $account->employee_id]);

        $this->actingAs($account, 'system')
            ->post("/daily-reports/{$report->id}/submit")
            ->assertSessionHas('error');

        $this->assertSame(ReportStatus::Draft, $report->refresh()->status);

        Carbon::setTestNow();
    }

    public function test_lead_scores_a_submitted_report_and_locks_it(): void
    {
        Http::fake();
        config(['telegram.enabled' => false]);

        $lead = $this->lead();
        $report = DailyReport::factory()->submitted()->create();

        $this->actingAs($lead, 'system')
            ->post("/daily-reports/{$report->id}/score", [
                'task_completion' => 7,
                'skill_score' => 7,
                'attitude_score' => 7,
                'kaizen_score' => 7,
                'expertise_score' => 7,
            ])
            ->assertRedirect();

        $report->refresh();
        $this->assertSame(ReportStatus::Reviewed, $report->status);
        $this->assertDatabaseHas('daily_report_scores', [
            'report_id' => $report->id,
            'grade' => 'A',
            'reviewer_id' => $lead->employee_id,
        ]);

        Http::assertNothingSent();
    }

    public function test_scoring_notifies_telegram_when_configured(): void
    {
        config([
            'telegram.enabled' => true,
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_id' => '-100999',
            'telegram.daily_report_review' => true,
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $lead = $this->lead();
        $report = DailyReport::factory()->submitted()->create([
            'title' => 'Báo cáo test Telegram',
            'goals_today' => '<p>Hoàn thành API login</p>',
            'progress_update' => '<ul><li>Done 80%</li></ul>',
            'plan_tomorrow' => '<p>Viết test E2E</p>',
        ]);

        $this->actingAs($lead, 'system')
            ->post("/daily-reports/{$report->id}/score", [
                'task_completion' => 7,
                'skill_score' => 7,
                'attitude_score' => 7,
                'kaizen_score' => 7,
                'expertise_score' => 7,
                'notes' => 'Ổn định tiến độ.',
            ])
            ->assertRedirect();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org/bottest-bot-token/sendMessage')
                && str_contains($request['text'], 'Báo cáo đã duyệt')
                && str_contains($request['text'], 'Kết quả duyệt')
                && str_contains($request['text'], 'Mục tiêu hôm nay')
                && str_contains($request['text'], 'Hoàn thành API login')
                && str_contains($request['text'], 'Báo cáo test Telegram');
        });
    }

    public function test_member_cannot_score(): void
    {
        $member = $this->member();
        $report = DailyReport::factory()->submitted()->create();

        $this->actingAs($member, 'system')
            ->post("/daily-reports/{$report->id}/score", [
                'task_completion' => 7,
                'skill_score' => 7,
                'attitude_score' => 7,
                'kaizen_score' => 7,
                'expertise_score' => 7,
            ])
            ->assertForbidden();
    }

    public function test_reviewed_report_cannot_be_edited(): void
    {
        $member = $this->member();
        $report = DailyReport::factory()->reviewed()->create(['employee_id' => $member->employee_id]);

        $this->actingAs($member, 'system')
            ->put("/daily-reports/{$report->id}", ['title' => 'changed'])
            ->assertForbidden();
    }

    public function test_lead_can_reject_back_to_draft(): void
    {
        Http::fake();
        config(['telegram.enabled' => false]);

        $lead = $this->lead();
        $report = DailyReport::factory()->submitted()->create();

        $this->actingAs($lead, 'system')
            ->post("/daily-reports/{$report->id}/reject", ['notes' => 'Please add the impact section.'])
            ->assertRedirect();

        $report->refresh();
        $this->assertSame(ReportStatus::Draft, $report->status);
        $this->assertSame('Please add the impact section.', $report->review_notes);

        Http::assertNothingSent();
    }

    public function test_reject_notifies_telegram_when_configured(): void
    {
        config([
            'telegram.enabled' => true,
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_id' => '-100999',
            'telegram.daily_report_review' => true,
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $lead = $this->lead();
        $report = DailyReport::factory()->submitted()->create([
            'title' => 'Báo cáo bị trả',
            'plan_tomorrow' => '<p>Bổ sung phần impact</p>',
        ]);

        $this->actingAs($lead, 'system')
            ->post("/daily-reports/{$report->id}/reject", [
                'notes' => 'Thiếu mục ảnh hưởng dự án.',
            ])
            ->assertRedirect();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org/bottest-bot-token/sendMessage')
                && str_contains($request['text'], 'Báo cáo bị trả lại')
                && str_contains($request['text'], 'Thông tin trả lại')
                && str_contains($request['text'], 'Thiếu mục ảnh hưởng dự án')
                && str_contains($request['text'], 'Báo cáo bị trả');
        });
    }

    public function test_show_includes_resolved_score_for_reviewed_report(): void
    {
        $member = $this->member();
        $report = DailyReport::factory()->reviewed()->create(['employee_id' => $member->employee_id]);
        DailyReportScore::factory()->create([
            'report_id' => $report->id,
            'reviewer_id' => $this->lead()->employee_id,
            'notes' => 'Làm tốt phần tiến độ.',
        ]);

        $this->actingAs($member, 'system')
            ->get(route('daily-reports.show', $report))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DailyReport/Show')
                ->where('report.score.grade', 'A')
                ->where('report.score.notes', 'Làm tốt phần tiến độ.')
                ->where('report.has_feedback', true));
    }

    public function test_member_can_delete_own_draft(): void
    {
        $member = $this->member();
        $report = DailyReport::factory()->create(['employee_id' => $member->employee_id]);

        $this->actingAs($member, 'system')
            ->delete(route('daily-reports.destroy', $report))
            ->assertRedirect(route('daily-reports.index'));

        $this->assertDatabaseMissing('daily_reports', ['id' => $report->id]);
    }

    public function test_history_index_includes_summary_for_member(): void
    {
        $member = $this->member();
        DailyReport::factory()->reviewed()->create(['employee_id' => $member->employee_id]);

        $this->actingAs($member, 'system')
            ->get(route('daily-reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DailyReport/History')
                ->has('summary')
                ->where('summary.reviewed', 1)
                ->where('canReview', false));
    }

    public function test_member_cannot_delete_submitted_report(): void
    {
        $member = $this->member();
        $report = DailyReport::factory()->submitted()->create(['employee_id' => $member->employee_id]);

        $this->actingAs($member, 'system')
            ->delete(route('daily-reports.destroy', $report))
            ->assertForbidden();

        $this->assertDatabaseHas('daily_reports', ['id' => $report->id]);
    }
}
