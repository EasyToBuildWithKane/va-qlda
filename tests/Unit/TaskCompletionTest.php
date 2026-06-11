<?php

namespace Tests\Unit;

use App\Models\Task;
use App\Support\Enums\TaskHoursTiming;
use App\Support\Enums\TaskSlaResult;
use App\Support\Enums\TaskStatus;
use App\Support\TaskCompletion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_hours_timing_early_and_over(): void
    {
        $this->assertSame(TaskHoursTiming::Early, TaskCompletion::resolveHoursTiming(10.0, 8.0));
        $this->assertSame(TaskHoursTiming::OnPlan, TaskCompletion::resolveHoursTiming(10.0, 10.0));
        $this->assertSame(TaskHoursTiming::OverPlan, TaskCompletion::resolveHoursTiming(10.0, 12.0));
    }

    public function test_resolve_sla_exceeded_when_actual_over_estimate(): void
    {
        $task = new Task([
            'status' => TaskStatus::InProgress,
            'estimate_hours' => 5,
            'work_started_at' => now()->subHours(2),
        ]);

        $result = TaskCompletion::resolveSlaResult($task, 6.0, now());

        $this->assertSame(TaskSlaResult::Exceeded, $result);
    }

    public function test_resolve_sla_met_when_within_estimate(): void
    {
        $task = new Task([
            'status' => TaskStatus::InProgress,
            'estimate_hours' => 8,
            'work_started_at' => now()->subHours(1),
        ]);

        $result = TaskCompletion::resolveSlaResult($task, 4.0, now());

        $this->assertSame(TaskSlaResult::Met, $result);
    }
}
