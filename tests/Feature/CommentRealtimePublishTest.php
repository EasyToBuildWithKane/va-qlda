<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class CommentRealtimePublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_comment_publishes_to_redis_when_realtime_enabled(): void
    {
        config([
            'realtime.enabled' => true,
            'realtime.redis_channel' => 'va-qlda:realtime',
        ]);

        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();
        $task = $project->tasks()->create([
            'title' => 'Task',
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Medium,
        ]);

        Redis::shouldReceive('publish')
            ->once()
            ->withArgs(function (string $channel, string $message) use ($task) {
                if ($channel !== 'va-qlda:realtime') {
                    return false;
                }
                $payload = json_decode($message, true);
                $room = 'comments:task:'.$task->id;

                return is_array($payload)
                    && ($payload['event'] ?? '') === 'comment.created'
                    && ($payload['room'] ?? '') === $room
                    && isset($payload['data']['comment']['body'])
                    && $payload['data']['comment']['body'] === 'Ping realtime test';
            })
            ->andReturn(1);

        $this->actingAs($admin, 'system')
            ->post('/comments', [
                'commentable_type' => 'task',
                'commentable_id' => $task->id,
                'body' => 'Ping realtime test',
            ])
            ->assertRedirect();
    }

    public function test_task_comment_does_not_publish_when_realtime_disabled(): void
    {
        config(['realtime.enabled' => false]);

        Redis::shouldReceive('publish')->never();

        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();
        $task = $project->tasks()->create([
            'title' => 'Task',
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Medium,
        ]);

        $this->actingAs($admin, 'system')
            ->post('/comments', [
                'commentable_type' => 'task',
                'commentable_id' => $task->id,
                'body' => 'No publish',
            ])
            ->assertRedirect();
    }
}
