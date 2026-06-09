<?php

namespace Tests\Feature;

use App\Models\Blocker;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\Enums\BlockerSeverity;
use App\Support\Enums\BlockerStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealtimeThreadTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_thread_token_disabled_by_default(): void
    {
        config(['realtime.enabled' => false]);
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();
        $blocker = Blocker::create([
            'project_id' => $project->id,
            'title' => 'Test',
            'severity' => BlockerSeverity::Medium,
            'status' => BlockerStatus::Open,
            'raised_by_id' => $admin->employee_id,
            'raised_at' => now(),
        ]);

        $this->actingAs($admin, 'system')
            ->getJson('/realtime/thread-token?type=blocker&id='.$blocker->id)
            ->assertNotFound();
    }

    public function test_thread_token_returns_signed_token_when_enabled(): void
    {
        config(['realtime.enabled' => true]);
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();
        $blocker = Blocker::create([
            'project_id' => $project->id,
            'title' => 'Test',
            'severity' => BlockerSeverity::Medium,
            'status' => BlockerStatus::Open,
            'raised_by_id' => $admin->employee_id,
            'raised_at' => now(),
        ]);

        $this->actingAs($admin, 'system')
            ->getJson('/realtime/thread-token?type=blocker&id='.$blocker->id)
            ->assertOk()
            ->assertJsonStructure(['token', 'room'])
            ->assertJsonPath('room', 'comments:blocker:'.$blocker->id);
    }
}
