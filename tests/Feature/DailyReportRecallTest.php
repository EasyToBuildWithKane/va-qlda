<?php

namespace Tests\Feature;

use App\Application\DailyReport\RecallDailyReportUseCase;
use App\Domain\DailyReport\Exceptions\DailyReportException;
use App\Domain\DailyReport\Models\DailyReport;
use App\Models\SystemAccount;
use App\Support\Enums\ReportStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DailyReportRecallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Monday — stable "today" for the same-day recall window.
        Carbon::setTestNow(Carbon::parse('2024-01-08 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function member(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Member)->create();
    }

    public function test_owner_recalls_todays_submitted_report_back_to_draft(): void
    {
        $member = $this->member();
        $report = DailyReport::factory()->submitted()->create([
            'employee_id' => $member->employee_id,
            'is_late' => true,
            'task_status_snapshot' => [['task_id' => 1, 'status' => 'done']],
        ]);

        $this->actingAs($member, 'system')
            ->post(route('daily-reports.recall', $report), ['reason' => 'Bổ sung kết quả còn thiếu'])
            ->assertRedirect(route('daily-reports.today'));

        $report->refresh();
        $this->assertSame(ReportStatus::Draft, $report->status);
        $this->assertNull($report->submitted_at);
        $this->assertFalse($report->is_late);
        $this->assertNull($report->task_status_snapshot);
        $this->assertSame(1, $report->recall_count);
        $this->assertNotNull($report->recalled_at);
    }

    public function test_recall_records_reason_in_activity_and_security_audit_log(): void
    {
        $member = $this->member();
        $report = DailyReport::factory()->submitted()->create(['employee_id' => $member->employee_id]);

        $this->actingAs($member, 'system')
            ->post(route('daily-reports.recall', $report), ['reason' => 'Sai số liệu'])
            ->assertRedirect(route('daily-reports.today'));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'daily_report',
            'event' => 'recalled',
            'subject_id' => $report->id,
        ]);
        $this->assertDatabaseHas('security_audit_logs', [
            'action' => 'daily_report.recalled',
            'subject_type' => 'daily_report',
            'subject_id' => $report->id,
        ]);

        $timeline = \App\Support\DailyReportTimeline::for($report->refresh());
        $recalled = collect($timeline)->firstWhere('event', 'recalled');
        $this->assertNotNull($recalled);
        $this->assertSame('Sai số liệu', $recalled['reason']);
    }

    public function test_recall_reason_is_optional(): void
    {
        $member = $this->member();
        $report = DailyReport::factory()->submitted()->create(['employee_id' => $member->employee_id]);

        $this->actingAs($member, 'system')
            ->post(route('daily-reports.recall', $report))
            ->assertRedirect(route('daily-reports.today'));

        $this->assertSame(ReportStatus::Draft, $report->refresh()->status);
        $this->assertSame(1, $report->recall_count);
    }

    public function test_recall_is_forbidden_for_a_previous_day_report(): void
    {
        $member = $this->member();
        $report = DailyReport::factory()->submitted()->create([
            'employee_id' => $member->employee_id,
            'date' => '2024-01-05', // last Friday — outside the recall window
        ]);

        $this->actingAs($member, 'system')
            ->post(route('daily-reports.recall', $report))
            ->assertForbidden();

        $this->assertSame(ReportStatus::Submitted, $report->refresh()->status);
    }

    public function test_cannot_recall_a_draft_report(): void
    {
        $member = $this->member();
        $report = DailyReport::factory()->create(['employee_id' => $member->employee_id]);

        $this->actingAs($member, 'system')
            ->post(route('daily-reports.recall', $report))
            ->assertForbidden();
    }

    public function test_another_member_cannot_recall_someone_elses_report(): void
    {
        $owner = $this->member();
        $intruder = $this->member();
        $report = DailyReport::factory()->submitted()->create(['employee_id' => $owner->employee_id]);

        $this->actingAs($intruder, 'system')
            ->post(route('daily-reports.recall', $report))
            ->assertForbidden();

        $this->assertSame(ReportStatus::Submitted, $report->refresh()->status);
    }

    public function test_use_case_rejects_a_report_from_a_previous_day(): void
    {
        $report = DailyReport::factory()->submitted()->create(['date' => '2024-01-05']);

        $this->expectException(DailyReportException::class);

        app(RecallDailyReportUseCase::class)->execute($report);
    }

    public function test_use_case_rejects_a_non_submitted_report(): void
    {
        $report = DailyReport::factory()->reviewed()->create();

        $this->expectException(DailyReportException::class);

        app(RecallDailyReportUseCase::class)->execute($report);
    }
}
