<?php

namespace Tests\Feature;

use App\Models\AiPaymentRequest;
use App\Models\AiPurchaseProposal;
use App\Models\SystemAccount;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiPaymentRequestStatus;
use App\Support\Enums\AiPurchaseProposalStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAccountOrphanPurgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_orphan_account_purged_on_index_after_proposal_expired(): void
    {
        $member = SystemAccount::factory()->create();
        $this->actingAs($member, 'system');

        $this->postJson(route('api.ai-accounts.proposals.store'), [
            'subject_about' => 'Đăng ký BA tool',
            'send_to' => "Ban Giám đốc\nPhòng Công nghệ",
            'tool_name' => 'BA Tool',
            'group_function' => AiAccountGroupFunction::Ba->value,
            'license_type' => 'Team',
            'cost_amount' => 500_000,
            'cost_unit' => AiAccountCostUnit::Monthly->value,
            'quantity' => 1,
            'proposer_name' => 'Nguyễn Văn A',
            'proposer_position' => 'BA',
            'proposer_department' => 'Phòng BA',
            'proposal_content' => 'Cần công cụ BA cho dự án trong ít nhất sáu tháng tới.',
            'objectives' => 'Chuẩn hoá quy trình.',
            'staff_count' => 2,
            'purchase_type' => 'new',
        ])->assertCreated();

        $proposal = AiPurchaseProposal::firstOrFail();
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $this->actingAs($admin, 'system');

        $this->postJson(route('api.ai-accounts.proposals.approve', ['proposal' => $proposal->id]))->assertOk();

        AiPaymentRequest::create([
            'ai_purchase_proposal_id' => $proposal->id,
            'amount' => $proposal->cost_amount,
            'status' => AiPaymentRequestStatus::Approved,
            'created_by' => $admin->id,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->postJson(route('api.ai-accounts.store'), [
            'proposal_id' => $proposal->id,
            'email_registered' => 'ba@vaschools.edu.vn',
            'password' => 'secret-pass',
            'notify_before_days' => 14,
        ])->assertCreated();

        $proposal->refresh();
        $accountId = $proposal->ai_account_id;
        $this->assertNotNull($accountId);

        // Giả lập xoá TK nhưng phiếu đã hết hạn / gỡ liên kết (dữ liệu cũ trên production).
        $proposal->update([
            'ai_account_id' => null,
            'status' => AiPurchaseProposalStatus::Expired,
        ]);

        $index = $this->getJson(route('api.ai-accounts.index'))->assertOk()->json('data');
        $this->assertSame(0, $index['summary_cards']['total_accounts'] ?? -1);
        $this->assertSoftDeleted('ai_accounts', ['id' => $accountId]);

        $summary = $this->getJson(route('api.ai-accounts.summary'))->assertOk()->json('data');
        $this->assertSame(0, $summary['cards']['total_accounts']);
        $this->assertSame(0, $summary['cards']['monthly_cost_running']);
        $this->assertEmpty($summary['by_group']);
    }
}
