<?php

namespace Tests\Unit;

use App\Models\Blocker;
use App\Models\Employee;
use App\Models\Feedback;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\Worklog;
use App\Support\WeeklyReport\Contracts\WeeklyReportGenerator;
use App\Support\WeeklyReport\HeuristicWeeklyReportGenerator;
use App\Support\WeeklyReport\WeeklyReportContext;
use App\Support\WeeklyReport\WeeklyReportFeedbackClassifier;
use App\Support\WeeklyReport\WeeklyReportKpiBuilder;
use App\Support\WeeklyReport\WeeklyReportNarrator;
use App\Support\WeeklyReport\WeeklyReportRiskAssessor;
use App\Support\WeeklyReport\WeeklyReportTaskFacts;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeeklyReportEngineTest extends TestCase
{
    private function context(): WeeklyReportContext
    {
        $mk = fn (array $a) => (new Task)->forceFill(array_merge([
            'project_id' => 1, 'sprint_id' => 1, 'parent_id' => null, 'is_milestone' => false,
            'status' => 'todo', 'priority' => 'medium', 'story_points' => 3, 'due_date' => null,
        ], $a));

        $assigneeA = (new Employee)->forceFill(['id' => 10, 'full_name' => 'Nguyễn Văn A']);
        $assigneeB = (new Employee)->forceFill(['id' => 11, 'full_name' => 'Trần Thị B']);
        $moduleA = $mk(['id' => 1, 'title' => 'Module A', 'status' => 'done', 'is_milestone' => true, 'completed_at' => Carbon::parse('2026-06-24'), 'description' => '<p>Bàn giao module A cho UAT.</p>', 'completion_note' => 'Đã demo với phòng Đào tạo.']);
        $moduleA->setRelation('assignee', $assigneeA);
        $moduleA->setRelation('assignees', collect([$assigneeA, $assigneeB]));

        $worklog = (new Worklog)->forceFill(['id' => 1, 'task_id' => 1, 'employee_id' => 10, 'hours' => 4]);
        $worklog->setRelation('employee', $assigneeA);
        $worklog->setRelation('task', $moduleA);

        return new WeeklyReportContext(
            project: (new Project)->forceFill(['id' => 1, 'name' => 'Demo', 'code' => 'D']),
            sprint: (new Sprint)->forceFill(['id' => 1, 'name' => 'Sprint 12', 'status' => 'active']),
            weekNumber: 2,
            weekStart: Carbon::parse('2026-06-22'),
            weekEnd: Carbon::parse('2026-06-28'),
            tasks: collect([
                $moduleA,
                $mk(['id' => 2, 'title' => 'Module B', 'status' => 'done', 'completed_at' => Carbon::parse('2026-06-26')]),
                $mk(['id' => 3, 'title' => 'Tích hợp', 'status' => 'blocked', 'priority' => 'urgent']),
                $mk(['id' => 4, 'title' => 'Tài liệu', 'status' => 'todo', 'due_date' => Carbon::parse('2026-06-25')]),
            ]),
            worklogs: collect([$worklog]),
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
        $this->assertSame(2, $kpi['week_completed']);
        $this->assertEquals(6.0, $kpi['week_story_points']);
        $this->assertSame(50, $kpi['sprint_progress']);
        $this->assertSame(1, $kpi['blocked']);
        $this->assertSame(1, $kpi['overdue']);
        $this->assertSame(2, $kpi['feedback']);
    }

    public function test_task_digest_and_outcomes_read_task_content(): void
    {
        $ctx = $this->context();
        $digest = WeeklyReportTaskFacts::digest($ctx);
        $outcomes = WeeklyReportTaskFacts::heuristicOutcomes($ctx);

        $done = collect($digest['completed_this_week'])->keyBy('title');
        $this->assertArrayHasKey('Module A', $done->all());
        $this->assertStringContainsString('Bàn giao module A', $done['Module A']['description']);
        $this->assertStringContainsString('phòng Đào tạo', $done['Module A']['completion_note']);
        $this->assertContains('Nguyễn Văn A', $done['Module A']['members']);
        $this->assertContains('Trần Thị B', $done['Module A']['members']);
        $this->assertSame('Nguyễn Văn A', $digest['contributors'][0]['name']);

        $byTitle = collect($outcomes)->firstWhere('title', 'Module A');
        $this->assertNotNull($byTitle);
        $this->assertStringContainsString('phòng Đào tạo', $byTitle['value']);
        $this->assertContains('Nguyễn Văn A', $byTitle['members']);
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
        $this->assertNotEmpty($report->meta['outcomes']);
        $titles = collect($report->meta['outcomes'])->pluck('title')->all();
        $this->assertContains('Module A', $titles);
        $this->assertContains('Module B', $titles);
    }

    public function test_narrator_lists_task_titles_in_sections(): void
    {
        $ctx = $this->context();
        $kpi = (new WeeklyReportKpiBuilder)->build($ctx);
        $risk = (new WeeklyReportRiskAssessor)->assess($ctx, $kpi);
        $feedback = (new WeeklyReportFeedbackClassifier)->classify($ctx->feedbacks);
        $narrative = (new WeeklyReportNarrator)->narrate($ctx, $kpi, $risk, $feedback);
        $sections = $narrative['sections'];

        $this->assertStringContainsString('Module A', $sections['result']);
        $this->assertStringContainsString('Nguyễn Văn A', $sections['result']);
        $this->assertStringContainsString('Module B', $sections['result']);
        $this->assertStringContainsString('ngày 24/06', $sections['result']);
        $this->assertStringContainsString('Thành viên tham gia', $narrative['executive']);
        $this->assertStringContainsString('Tích hợp', $sections['current']);
        $this->assertStringContainsString('Vướng mắc:', $sections['current']);
        $this->assertStringContainsString('Tài liệu', $sections['next']);
        $this->assertStringContainsString('Thêm Export', $sections['feedback']);
        $this->assertStringContainsString('22/06', $narrative['executive']);
        $this->assertStringContainsString('Trong tuần hoàn thành', $narrative['executive']);
        $this->assertStringContainsString('Chờ UAT', $narrative['insight']);
    }

    public function test_narrator_result_excludes_done_tasks_outside_week_window(): void
    {
        $mk = fn (array $a) => (new Task)->forceFill(array_merge([
            'project_id' => 1, 'sprint_id' => 1, 'parent_id' => null, 'is_milestone' => false,
            'status' => 'done', 'priority' => 'medium', 'story_points' => 3, 'due_date' => null,
        ], $a));

        $ctx = new WeeklyReportContext(
            project: (new Project)->forceFill(['id' => 1, 'name' => 'Demo', 'code' => 'D']),
            sprint: (new Sprint)->forceFill(['id' => 1, 'name' => 'Sprint 12', 'status' => 'active']),
            weekNumber: 2,
            weekStart: Carbon::parse('2026-06-22'),
            weekEnd: Carbon::parse('2026-06-28'),
            tasks: collect([
                $mk(['id' => 1, 'title' => 'Trong tuần', 'completed_at' => Carbon::parse('2026-06-24')]),
                $mk(['id' => 2, 'title' => 'Tuần trước', 'completed_at' => Carbon::parse('2026-06-10')]),
                $mk(['id' => 3, 'title' => 'Done cũ không mốc', 'completed_at' => null, 'updated_at' => Carbon::parse('2026-05-01')]),
            ]),
            worklogs: collect(),
            activities: collect(),
            blockers: collect(),
            feedbacks: collect(),
        );

        $kpi = (new WeeklyReportKpiBuilder)->build($ctx);
        $risk = (new WeeklyReportRiskAssessor)->assess($ctx, $kpi);
        $feedback = (new WeeklyReportFeedbackClassifier)->classify($ctx->feedbacks);
        $result = (new WeeklyReportNarrator)->narrate($ctx, $kpi, $risk, $feedback)['sections']['result'];

        $this->assertStringContainsString('Trong tuần', $result);
        $this->assertStringNotContainsString('Tuần trước', $result);
        $this->assertStringNotContainsString('Done cũ không mốc', $result);
        $this->assertStringNotContainsString('Đã hoàn thành (Sprint)', $result);
    }

    public function test_llm_generator_rewrites_narrative_when_configured(): void
    {
        config([
            'weekly_report.llm.enabled' => true,
            'weekly_report.llm.provider' => 'openai',
            'weekly_report.llm.api_key' => 'sk-test',
            'weekly_report.llm.model' => 'gpt-4o-mini',
            'weekly_report.llm.base_url' => '',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'executive' => 'Tóm tắt do AI viết.',
                            'insight' => 'Nhận định do AI viết.',
                            'outcomes' => [
                                ['title' => 'Module A', 'value' => 'Đã demo với phòng Đào tạo.'],
                            ],
                            'sections' => [
                                'result' => 'Kết quả AI',
                                'current' => 'Hiện tại AI',
                                'next' => 'Kế hoạch AI',
                                'risk' => 'Rủi ro AI',
                                'feedback' => 'Phản hồi AI',
                                'activity' => 'Hoạt động AI',
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ], 200),
        ]);

        $report = app(WeeklyReportGenerator::class)->generate($this->context());

        $this->assertSame('Tóm tắt do AI viết.', $report->executiveSummary);
        $this->assertSame('Nhận định do AI viết.', $report->aiSummary);
        $this->assertSame('Kết quả AI', $report->sections['result']);
        $this->assertSame('llm', $report->meta['engine']);
        $this->assertSame('openai', $report->meta['llm_provider']);
        $this->assertSame(4, $report->kpi['total_tasks']);
        $this->assertSame(2, $report->kpi['week_completed']);
        $this->assertSame('Module A', $report->meta['outcomes'][0]['title']);
        Http::assertSent(function ($request) {
            $payload = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

            return str_contains($request->url(), '/v1/chat/completions')
                && str_contains($payload, 'Module A')
                && str_contains($payload, 'completed_this_week')
                && str_contains($payload, 'Nguyễn Văn A')
                && str_contains($payload, 'contributors');
        });
    }

    public function test_nvidia_provider_posts_to_nim_chat_completions_not_double_v1(): void
    {
        config([
            'weekly_report.llm.enabled' => true,
            'weekly_report.llm.provider' => 'nvidia',
            'weekly_report.llm.api_key' => 'nvapi-test',
            'weekly_report.llm.model' => 'nvidia/nemotron-3.5-lightning-30b-a3b',
            'weekly_report.llm.base_url' => 'https://integrate.api.nvidia.com/v1',
        ]);

        Http::fake([
            'integrate.api.nvidia.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'executive' => 'Tóm tắt NVIDIA.',
                            'insight' => 'Nhận định NVIDIA.',
                            'sections' => [
                                'result' => 'Kết quả',
                                'current' => 'Hiện tại',
                                'next' => 'Kế hoạch',
                                'risk' => 'Rủi ro',
                                'feedback' => 'Phản hồi',
                                'activity' => 'Hoạt động',
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ], 200),
        ]);

        $report = app(WeeklyReportGenerator::class)->generate($this->context());

        $this->assertSame('Tóm tắt NVIDIA.', $report->executiveSummary);
        $this->assertSame('nvidia', $report->meta['llm_provider']);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://integrate.api.nvidia.com/v1/chat/completions'
                && ! str_contains($request->url(), '/v1/v1/');
        });
    }

    public function test_llm_generator_falls_back_when_api_fails(): void
    {
        config([
            'weekly_report.llm.enabled' => true,
            'weekly_report.llm.provider' => 'openai',
            'weekly_report.llm.api_key' => 'sk-test',
            'weekly_report.llm.model' => 'gpt-4o-mini',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response(['error' => 'unavailable'], 503),
        ]);

        $report = app(WeeklyReportGenerator::class)->generate($this->context());

        $this->assertNotEmpty($report->executiveSummary);
        $this->assertSame('heuristic_fallback', $report->meta['engine']);
        $this->assertTrue($report->meta['llm_error']);
        $this->assertStringContainsString('%', $report->executiveSummary);
    }

    public function test_llm_generator_skips_http_when_not_configured(): void
    {
        Http::fake();

        $report = app(WeeklyReportGenerator::class)->generate($this->context());

        $this->assertSame('heuristic', $report->meta['engine']);
        $this->assertNotEmpty($report->executiveSummary);
        Http::assertNothingSent();
    }
}
