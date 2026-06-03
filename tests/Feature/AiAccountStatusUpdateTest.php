<?php

namespace Tests\Feature;

use App\Models\AiAccount;
use App\Models\AiPurchaseProposal;
use App\Models\SystemAccount;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountStatus;
use App\Support\Enums\AiPurchaseProposalStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAccountStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_proposal_creator_can_update_account_status(): void
    {
        $creator = SystemAccount::factory()->create();
        $account = AiAccount::create([
            'tool_name' => 'Test AI',
            'license_type' => 'Pro',
            'group_function' => AiAccountGroupFunction::Dev,
            'email_registered' => 'ai@test.com',
            'purchase_date' => now()->subWeek(),
            'expiry_date' => now()->addMonth(),
            'cost_amount' => 500_000,
            'cost_unit' => AiAccountCostUnit::Monthly,
            'status' => AiAccountStatus::Active,
            'notify_before_days' => 14,
        ]);

        AiPurchaseProposal::create([
            'tool_name' => 'Test AI',
            'group_function' => AiAccountGroupFunction::Dev,
            'license_type' => 'Pro',
            'cost_amount' => 500_000,
            'cost_unit' => AiAccountCostUnit::Monthly,
            'status' => AiPurchaseProposalStatus::Approved,
            'created_by' => $creator->id,
            'ai_account_id' => $account->id,
            'proposer_name' => 'Người tạo',
            'justification' => 'Đề xuất test cập nhật trạng thái tài khoản AI.',
        ]);

        $this->actingAs($creator, 'system');

        $this->patchJson(route('api.ai-accounts.update-status', ['aiAccount' => $account->id]), [
            'status' => AiAccountStatus::Expired->value,
            'sync_expiry_on_expire' => true,
        ])->assertOk();

        $account->refresh();
        $this->assertSame(AiAccountStatus::Expired, $account->status);
        $this->assertNotNull($account->status_locked_at);
        $this->assertTrue($account->expiry_date->isToday());
    }

    public function test_other_member_cannot_update_account_status(): void
    {
        $creator = SystemAccount::factory()->create();
        $other = SystemAccount::factory()->create();
        $account = AiAccount::create([
            'tool_name' => 'Locked',
            'license_type' => 'Pro',
            'group_function' => AiAccountGroupFunction::Dev,
            'email_registered' => 'x@test.com',
            'purchase_date' => now(),
            'expiry_date' => now()->addMonth(),
            'cost_amount' => 100_000,
            'cost_unit' => AiAccountCostUnit::Monthly,
            'status' => AiAccountStatus::Active,
            'notify_before_days' => 14,
        ]);

        AiPurchaseProposal::create([
            'tool_name' => 'Locked',
            'group_function' => AiAccountGroupFunction::Dev,
            'license_type' => 'Pro',
            'cost_amount' => 100_000,
            'cost_unit' => AiAccountCostUnit::Monthly,
            'status' => AiPurchaseProposalStatus::Approved,
            'created_by' => $creator->id,
            'ai_account_id' => $account->id,
            'proposer_name' => 'A',
            'justification' => 'Đề xuất test quyền cập nhật trạng thái.',
        ]);

        $this->actingAs($other, 'system');

        $this->patchJson(route('api.ai-accounts.update-status', ['aiAccount' => $account->id]), [
            'status' => AiAccountStatus::Expired->value,
        ])->assertForbidden();
    }

    public function test_admin_can_update_account_status(): void
    {
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $account = AiAccount::create([
            'tool_name' => 'Admin Test',
            'license_type' => 'Pro',
            'group_function' => AiAccountGroupFunction::Dev,
            'email_registered' => 'a@test.com',
            'purchase_date' => now(),
            'expiry_date' => now()->addDays(20),
            'cost_amount' => 200_000,
            'cost_unit' => AiAccountCostUnit::Monthly,
            'status' => AiAccountStatus::Active,
            'notify_before_days' => 14,
        ]);

        $this->actingAs($admin, 'system');

        $this->patchJson(route('api.ai-accounts.update-status', ['aiAccount' => $account->id]), [
            'status' => AiAccountStatus::ExpiringSoon->value,
        ])->assertOk();

        $account->refresh();
        $this->assertSame(AiAccountStatus::ExpiringSoon, $account->status);
    }
}
