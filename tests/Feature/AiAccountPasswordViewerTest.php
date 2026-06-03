<?php

namespace Tests\Feature;

use App\Models\AiAccount;
use App\Models\AiAccountPasswordViewer;
use App\Models\SystemAccount;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAccountPasswordViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_grant_and_revoke_password_viewer_per_account(): void
    {
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $member = SystemAccount::factory()->create();
        $account = $this->makeAccount('Tool A');

        $this->actingAs($admin, 'system');

        $this->postJson(route('api.ai-accounts.password-viewers.store'), [
            'ai_account_id' => $account->id,
            'system_account_id' => $member->id,
        ])->assertCreated();

        $this->assertDatabaseHas('ai_account_password_viewers', [
            'ai_account_id' => $account->id,
            'system_account_id' => $member->id,
        ]);

        $row = AiAccountPasswordViewer::query()
            ->where('ai_account_id', $account->id)
            ->where('system_account_id', $member->id)
            ->first();
        $this->assertNotNull($row);

        $this->deleteJson(route('api.ai-accounts.password-viewers.destroy', ['passwordViewer' => $row->id]))
            ->assertOk();

        $this->assertDatabaseMissing('ai_account_password_viewers', ['id' => $row->id]);
    }

    public function test_granted_member_sees_password_only_for_that_account(): void
    {
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $member = SystemAccount::factory()->create();

        $accountA = $this->makeAccount('Tool A', 'pass-a');
        $accountB = $this->makeAccount('Tool B', 'pass-b');

        AiAccountPasswordViewer::create([
            'ai_account_id' => $accountA->id,
            'system_account_id' => $member->id,
            'granted_by' => $admin->id,
        ]);

        $this->actingAs($member, 'system');

        $groups = $this->getJson(route('api.ai-accounts.index'))
            ->assertOk()
            ->json('data.groups');

        $rows = collect($groups)->flatMap(fn ($g) => $g['accounts'])->keyBy('tool_name');

        $this->assertTrue($rows['Tool A']['can_view_password']);
        $this->assertSame('pass-a', $rows['Tool A']['password']);

        $this->assertFalse($rows['Tool B']['can_view_password']);
        $this->assertNull($rows['Tool B']['password']);
    }

    public function test_member_without_grant_cannot_see_password(): void
    {
        $member = SystemAccount::factory()->create();

        $this->makeAccount('Hidden', 'hidden');

        $this->actingAs($member, 'system');

        $account = $this->getJson(route('api.ai-accounts.index'))
            ->assertOk()
            ->json('data.groups.0.accounts.0');

        $this->assertFalse($account['can_view_password']);
        $this->assertFalse($account['has_password']);
        $this->assertNull($account['password']);
    }

    public function test_index_requires_ai_account_id(): void
    {
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $this->actingAs($admin, 'system');

        $this->getJson(route('api.ai-accounts.password-viewers.index'))
            ->assertUnprocessable();
    }

    private function makeAccount(string $toolName, ?string $password = null): AiAccount
    {
        return AiAccount::create([
            'tool_name' => $toolName,
            'license_type' => 'Pro',
            'group_function' => AiAccountGroupFunction::Dev,
            'email_registered' => strtolower(str_replace(' ', '', $toolName)).'@test.com',
            'login_password' => $password,
            'purchase_date' => now(),
            'expiry_date' => now()->addMonth(),
            'cost_amount' => 100_000,
            'cost_unit' => AiAccountCostUnit::Monthly,
            'status' => AiAccountStatus::Active,
            'notify_before_days' => 14,
        ]);
    }
}
