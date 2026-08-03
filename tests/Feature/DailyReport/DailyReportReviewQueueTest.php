<?php

namespace Tests\Feature\DailyReport;

use App\Domain\DailyReport\Models\DailyReport;
use App\Models\DailyReport\DailyReportScoringConfig;
use App\Models\Employee;
use App\Models\SystemAccount;
use App\Support\DailyReportCalendar;
use App\Support\Enums\ReportStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DailyReportReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    private function lead(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Lead)->create();
    }

    public function test_review_filters_queue_today_on_server(): void
    {
        $lead = $this->lead();
        $today = DailyReportCalendar::today();

        DailyReport::factory()->submitted()->create([
            'date' => $today,
            'title' => 'Today report',
        ]);
        DailyReport::factory()->submitted()->create([
            'date' => now()->subDays(3)->toDateString(),
            'title' => 'Old report',
            'is_late' => true,
        ]);

        $this->actingAs($lead, 'system')
            ->get('/daily-reports/review?queue=today')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DailyReport/Review')
                ->where('filters.queue', 'today')
                ->has('reports.data', 1)
                ->where('queueTotals.reports', 2)
                ->where('queueTotals.today', 1)
                ->where('queueTotals.late', 1)
            );
    }

    public function test_review_search_filters_by_employee_name(): void
    {
        $lead = $this->lead();
        $emp = Employee::factory()->create(['full_name' => 'Nguyen Van Alpha']);
        DailyReport::factory()->submitted()->create([
            'employee_id' => $emp->id,
            'title' => 'Alpha day',
        ]);
        DailyReport::factory()->submitted()->create([
            'title' => 'Other day',
        ]);

        $this->actingAs($lead, 'system')
            ->get('/daily-reports/review?q=Alpha')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('reports.data', 1)
                ->where('reports.data.0.title', 'Alpha day')
            );
    }

    public function test_score_uses_department_weights_and_stores_snapshot(): void
    {
        Http::fake();
        config(['telegram.enabled' => false]);

        DailyReportScoringConfig::query()->create([
            'department_code' => 'CNTT',
            'department_name' => 'Công nghệ',
            'weights' => [
                'task_completion' => 1.0,
                'skill_score' => 0.01,
                'attitude_score' => 0.01,
                'expertise_score' => 0.01,
            ],
            'kaizen_bonus_max' => 0,
            'status' => DailyReportScoringConfig::STATUS_ACTIVE,
        ]);

        $employee = Employee::factory()->create([
            'meta' => ['department_code' => 'CNTT', 'department_name' => 'Công nghệ'],
        ]);
        $report = DailyReport::factory()->submitted()->create([
            'employee_id' => $employee->id,
        ]);
        $lead = $this->lead();

        $this->actingAs($lead, 'system')
            ->post("/daily-reports/{$report->id}/score", [
                'task_completion' => 10,
                'skill_score' => 0,
                'attitude_score' => 0,
                'kaizen_score' => 0,
                'expertise_score' => 0,
            ])
            ->assertRedirect();

        $score = $report->fresh()->score;
        $this->assertNotNull($score);
        // Almost all weight on task_completion → ~10
        $this->assertEqualsWithDelta(9.71, (float) $score->total_score, 0.05);
        $this->assertSame('department', $score->scoring_snapshot['source'] ?? null);
        $this->assertSame('CNTT', $score->scoring_snapshot['department_code'] ?? null);
    }

    public function test_bulk_score_reviews_multiple_reports(): void
    {
        Http::fake();
        config(['telegram.enabled' => false]);

        $lead = $this->lead();
        $a = DailyReport::factory()->submitted()->create();
        $b = DailyReport::factory()->submitted()->create();

        $this->actingAs($lead, 'system')
            ->post('/daily-reports/review/bulk-score', [
                'ids' => [$a->id, $b->id],
                'task_completion' => 8,
                'skill_score' => 8,
                'attitude_score' => 8,
                'kaizen_score' => 5,
                'expertise_score' => 8,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(ReportStatus::Reviewed, $a->fresh()->status);
        $this->assertSame(ReportStatus::Reviewed, $b->fresh()->status);
    }

    public function test_bulk_reject_returns_reports_to_draft(): void
    {
        Http::fake();
        config(['telegram.enabled' => false]);

        $lead = $this->lead();
        $a = DailyReport::factory()->submitted()->create();
        $b = DailyReport::factory()->submitted()->create();

        $this->actingAs($lead, 'system')
            ->post('/daily-reports/review/bulk-reject', [
                'ids' => [$a->id, $b->id],
                'notes' => 'Thiếu số liệu kết quả, vui lòng bổ sung.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(ReportStatus::Draft, $a->fresh()->status);
        $this->assertSame(ReportStatus::Draft, $b->fresh()->status);
    }
}
