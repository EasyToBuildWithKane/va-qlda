<?php

namespace Tests\Feature;

use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function member(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Member)->create();
    }

    // ─── Access ─────────────────────────────────────────────────────────────

    public function test_guest_is_redirected_from_onboarding(): void
    {
        $this->get('/onboarding')->assertRedirect('/login');
    }

    public function test_index_returns_progress_payload(): void
    {
        $this->actingAs($this->member(), 'system')
            ->getJson('/onboarding')
            ->assertOk()
            ->assertJsonStructure([
                'role',
                'tours' => ['system_overview' => ['status', 'current_step', 'total_steps']],
                'completed_tours',
                'total_tours',
                'context' => ['hasProject', 'hasSprint', 'hasTask', 'hasTeamMembers'],
            ]);
    }

    // ─── Progress ───────────────────────────────────────────────────────────

    public function test_progress_records_step(): void
    {
        $account = $this->member();

        $this->actingAs($account, 'system')
            ->postJson('/onboarding/progress', [
                'tour_key' => 'system_overview',
                'current_step' => 3,
                'total_steps' => 7,
            ])
            ->assertNoContent();

        $this->assertDatabaseHas('onboarding_progress', [
            'system_account_id' => $account->id,
            'tour_key' => 'system_overview',
            'current_step' => 3,
            'total_steps' => 7,
            'status' => 'in_progress',
        ]);
    }

    public function test_progress_rejects_unknown_tour_key(): void
    {
        $this->actingAs($this->member(), 'system')
            ->postJson('/onboarding/progress', ['tour_key' => 'not_a_tour'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tour_key');
    }

    public function test_progress_is_idempotent_per_tour(): void
    {
        $account = $this->member();

        $this->actingAs($account, 'system')
            ->postJson('/onboarding/progress', ['tour_key' => 'role_member', 'current_step' => 1, 'total_steps' => 3]);
        $this->actingAs($account, 'system')
            ->postJson('/onboarding/progress', ['tour_key' => 'role_member', 'current_step' => 2, 'total_steps' => 3]);

        $this->assertDatabaseCount('onboarding_progress', 1);
        $this->assertDatabaseHas('onboarding_progress', [
            'system_account_id' => $account->id,
            'tour_key' => 'role_member',
            'current_step' => 2,
        ]);
    }

    // ─── Complete / skip / reset ────────────────────────────────────────────

    public function test_complete_marks_tour(): void
    {
        $account = $this->member();

        $this->actingAs($account, 'system')
            ->postJson('/onboarding/complete', ['tour_key' => 'system_overview'])
            ->assertNoContent();

        $this->assertDatabaseHas('onboarding_progress', [
            'system_account_id' => $account->id,
            'tour_key' => 'system_overview',
            'status' => 'completed',
        ]);
        $row = \App\Models\OnboardingProgress::where('system_account_id', $account->id)->first();
        $this->assertNotNull($row->completed_at);
    }

    public function test_skip_marks_tour_skipped(): void
    {
        $account = $this->member();

        $this->actingAs($account, 'system')
            ->postJson('/onboarding/skip', ['tour_key' => 'system_overview'])
            ->assertNoContent();

        $this->assertDatabaseHas('onboarding_progress', [
            'system_account_id' => $account->id,
            'tour_key' => 'system_overview',
            'status' => 'skipped',
        ]);
    }

    public function test_reset_removes_progress(): void
    {
        $account = $this->member();

        $this->actingAs($account, 'system')
            ->postJson('/onboarding/complete', ['tour_key' => 'system_overview']);
        $this->actingAs($account, 'system')
            ->postJson('/onboarding/reset', ['tour_key' => 'system_overview'])
            ->assertNoContent();

        $this->assertDatabaseMissing('onboarding_progress', [
            'system_account_id' => $account->id,
            'tour_key' => 'system_overview',
        ]);
    }

    public function test_non_ajax_post_redirects_back(): void
    {
        $this->actingAs($this->member(), 'system')
            ->from('/dashboard')
            ->post('/onboarding/skip', ['tour_key' => 'system_overview'])
            ->assertRedirect('/dashboard');
    }
}
