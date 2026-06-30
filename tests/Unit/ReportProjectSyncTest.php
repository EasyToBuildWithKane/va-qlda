<?php

namespace Tests\Unit;

use App\Domain\DailyReport\Support\ReportProjectSync;
use PHPUnit\Framework\TestCase;

class ReportProjectSyncTest extends TestCase
{
    public function test_dedupe_projects_merges_same_project_id_and_tasks(): void
    {
        $input = [
            ['id' => 10, 'name' => 'Alpha', 'tasks' => [['id' => 1, 'title' => 'Task A']]],
            ['id' => 10, 'name' => 'Alpha', 'tasks' => [['id' => 1, 'title' => 'Task A'], ['id' => 2, 'title' => 'Task B']]],
        ];

        $out = ReportProjectSync::dedupeProjects($input);

        $this->assertCount(1, $out);
        $this->assertSame(10, $out[0]['id']);
        $this->assertCount(2, $out[0]['tasks']);
    }

    public function test_apply_to_payload_dedupes_before_legacy_project_id(): void
    {
        $data = ReportProjectSync::applyToPayload([
            'projects' => [
                ['id' => 5, 'name' => 'P', 'tasks' => []],
                ['id' => 5, 'name' => 'P', 'tasks' => [['id' => 9, 'title' => 'X']]],
            ],
        ]);

        $this->assertSame(5, $data['project_id']);
        $this->assertCount(1, $data['projects']);
        $this->assertCount(1, $data['projects'][0]['tasks']);
    }
}
