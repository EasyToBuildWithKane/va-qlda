<?php

namespace Tests\Feature;

use App\Models\AiAccount;
use App\Models\SystemAccount;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAccountStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccount(): AiAccount
    {
        return AiAccount::create([
            'tool_name' => 'Test AI',
            'group_function' => AiAccountGroupFunction::Dev,
            'email_registered' => 'ai@test.com',
            'purchase_date' => now()->subWeek(),
            'expiry_date' => now()->addMonth(),
            'cost_amount' => 500_000,
            'cost_unit' => AiAccountCostUnit::Monthly,
            'status' => AiAccountStatus::Active,
            'notify_before_days' => 14,
        ]);
    }

    public function test_admin_can_update_account_status(): void
    {
        $admin = SystemAccount::factory()->create([
            'role' => SystemRole::Admin->value,
        ]);
        $account = $this->makeAccount();

        $this->actingAs($admin, 'system');

        $this->patchJson(route('api.ai-accounts.update-status', ['aiAccount' => $account->id]), [
            'status' => AiAccountStatus::Expired->value,
            'sync_expiry_on_expire' => true,
        ])->assertOk();

        $account->refresh();
        $this->assertSame(AiAccountStatus::Expired, $account->status);
        $this->assertTrue($account->expiry_date->isToday());
    }

    public function test_member_without_update_cannot_change_status(): void
    {
        $member = SystemAccount::factory()->create([
            'role' => SystemRole::Member->value,
        ]);
        $account = $this->makeAccount();

        $this->actingAs($member, 'system');

        $this->patchJson(route('api.ai-accounts.update-status', ['aiAccount' => $account->id]), [
            'status' => AiAccountStatus::Cancelled->value,
        ])->assertForbidden();
    }
}
