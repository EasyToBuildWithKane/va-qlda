<?php

namespace Tests\Unit;

use App\Models\Blocker;
use App\Models\Feedback;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Support\WeeklyReport\HeuristicWeeklyReportGenerator;
use App\Support\WeeklyReport\WeeklyReportContext;
use App\Support\WeeklyReport\WeeklyReportFeedbackClassifier;
use App\Support\WeeklyReport\WeeklyReportKpiBuilder;
use App\Support\WeeklyReport\WeeklyReportNarrator;
use App\Support\WeeklyReport\WeeklyReportRiskAssessor;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WeeklyReportEngineTest extends TestCase
{
    private function context(): WeeklyReportContext
    {
        $mk = fn (array $a) => (new Task)->forceFill(array_merge([
            'project_id' => 1, 'sprint_id' => 1, 'parent_id' => null, 'is_milestone' => false,
            'status' => 'todo', 'priority' => 'medium', 'story_points' => 3, 'due_date' => null,
        ], $a));

        return new WeeklyReportContext(
            project: (new Project)->forceFill(['id' => 1, 'name' => 'Demo', 'code' => 'D']),
            sprint: (new Sprint)->forceFill(['id' => 1, 'name' => 'Sprint 12', 'status' => 'active']),
            weekNumber: 2,
            weekStart: Carbon::parse('2026-06-22'),
            weekEnd: Carbon::parse('2026-06-28'),
            tasks: collect([
                $mk(['id' => 1, 'title' => 'Module A', 'status' => 'done', 'is_milestone' => true, 'completed_at' => Carbon::parse('2026-06-24')]),
                $mk(['id' => 2, 'title' => 'Module B', 'status' => 'done', 'completed_at' => Carbon::parse('2026-06-26')]),
                $mk(['id' => 3, 'title' => 'Tích hợp', 'status' => 'blocked', 'priority' => 'urgent']),
                $mk(['id' => 4, 'title' => 'Tài liệu', 'status' => 'todo', 'due_date' => Carbon::parse('2026-06-25')]),
            ]),
            worklogs: collect(),
            activities: collect(),
            blockers: collect([
                (new Blocker)->forceFill(['id' => 1, 'title' => 'Chờ UAT', 'severity' => 'high', 'status' => 'open']),
            ]),
            feedbacks: collect([
                (new Feedback)->forceFill(['id' => 1, 'title' => 'Tốt', 'description' => '', 'category' => 'praise', 'status' => 'new', 'rating' => 5, 'priority' => 'low']),
                (new Feedback)->forceFill(['id' => 2, 'title' => 'Thêm Export', 'description' => '', 'category' => 'feature_request', 'status' => 'new', 'rating' => null, 'priority' => 'medium']),
            ]),
        );
    }

    public function test_kpi_builder_counts_correctly(): void
    {
        $kpi = (new WeeklyReportKpiBuilder)->build($this->context());

        $this->assertSame(4, $kpi['total_tasks']);
        $this->assertSame(2, $kpi['completed_tasks']);
        $this->assertSame(50, $kpi['sprint_progress']);
        $this->assertSame(1, $kpi['blocked']);
        $this->assertSame(1, $kpi['overdue']);
        $this->assertSame(2, $kpi['feedback']);
    }

    public function test_feedback_classifier_buckets(): void
    {
        $ctx = $this->context();
        $result = (new WeeklyReportFeedbackClassifier)->classify($ctx->feedbacks);

        $byKey = collect($result['breakdown'])->keyBy('key');
        $this->assertSame(1, $byKey['positive']['count']);
        $this->assertSame(1, $byKey['change_request']['count']);
        $this->assertSame(2, $result['total']);
    }

    public function test_risk_assessor_flags_high_blocker(): void
    {
        $ctx = $this->context();
        $kpi = (new WeeklyReportKpiBuilder)->build($ctx);
        $risk = (new WeeklyReportRiskAssessor)->assess($ctx, $kpi);

        $this->assertGreaterThanOrEqual(1, $risk['summary']['high']);
    }

    public function test_generator_produces_non_empty_narrative(): void
    {
        $generator = new HeuristicWeeklyReportGenerator(
            new WeeklyReportKpiBuilder,
            new WeeklyReportRiskAssessor,
            new WeeklyReportFeedbackClassifier,
            new WeeklyReportNarrator,
        );

        $report = $generator->generate($this->context());

        $this->assertNotEmpty($report->executiveSummary);
        $this->assertStringContainsString('%', $report->executiveSummary);
        $this->assertArrayHasKey('result', $report->sections);
        $this->assertStringContainsString('•', $report->sections['result']);
    }

    public function test_narrator_lists_task_titles_in_sections(): void
    {
        $ctx = $this->context();
        $kpi = (new WeeklyReportKpiBuilder)->build($ctx);
        $risk = (new WeeklyReportRiskAssessor)->assess($ctx, $kpi);
        $feedback = (new WeeklyReportFeedbackClassifier)->classify($ctx->feedbacks);
        $sections = (new WeeklyReportNarrator)->narrate($ctx, $kpi, $risk, $feedback)['sections'];

        $this->assertStringContainsString('Module A', $sections['result']);
        $this->assertStringContainsString('Module B', $sections['result']);
        $this->assertStringContainsString('Tích hợp', $sections['current']);
        $this->assertStringContainsString('Tài liệu', $sections['next']);
    }
}
