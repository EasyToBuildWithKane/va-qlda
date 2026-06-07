<?php

namespace Tests\Feature;

use App\Models\AiAccount;
use App\Models\AiPaymentRequest;
use App\Models\AiPurchaseProposal;
use App\Models\SystemAccount;
use App\Services\AiAccount\AiAccountCountableProposalCost;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountStatus;
use App\Support\Enums\AiPaymentRequestStatus;
use App\Support\Enums\AiPurchaseProposalStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAccountSoftDeleteVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_deleted_account_hidden_from_index_and_budget(): void
    {
        $member = SystemAccount::factory()->create();
        $this->actingAs($member, 'system');

        $this->postJson(route('api.ai-accounts.proposals.store'), [
            'subject_about' => 'Đăng ký sử dụng Cursor Pro',
            'send_to' => "Ban Giám đốc\nPhòng Công nghệ & Phòng Kế Toán",
            'tool_name' => 'Cursor Pro',
            'group_function' => AiAccountGroupFunction::Dev->value,
            'license_type' => 'Pro',
            'cost_amount' => 1_000_000,
            'cost_unit' => AiAccountCostUnit::Monthly->value,
            'quantity' => 1,
            'proposer_name' => 'Nguyễn Văn A',
            'proposer_position' => 'Developer',
            'proposer_department' => 'Phòng Công nghệ',
            'proposal_content' => 'Team cần IDE AI cho dự án QLDA trong 6 tháng tới với đủ tính năng pair programming.',
            'objectives' => "Tăng tốc phát triển.\nGiảm thời gian review code.",
            'staff_count' => 5,
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
            'email_registered' => 'cursor@vaschools.edu.vn',
            'password' => 'secret-pass',
            'notify_before_days' => 14,
        ])->assertCreated();

        $proposal->refresh();
        $accountId = $proposal->ai_account_id;
        $this->assertNotNull($accountId);

        $beforeSummary = $this->getJson(route('api.ai-accounts.summary'))->assertOk()->json('data');
        $this->assertSame(1, $beforeSummary['cards']['total_accounts']);
        $this->assertGreaterThanOrEqual(1_000_000, $beforeSummary['cards']['monthly_cost_running']);

        $this->deleteJson(route('api.ai-accounts.destroy', ['aiAccount' => $accountId]))->assertOk();
        $this->assertSoftDeleted('ai_accounts', ['id' => $accountId]);

        $index = $this->getJson(route('api.ai-accounts.index'))->assertOk()->json('data');
        $allAccounts = collect($index['groups'] ?? [])->flatMap(fn ($g) => $g['accounts'] ?? []);
        $this->assertEmpty($allAccounts->firstWhere('id', $accountId));

        $summary = $this->getJson(route('api.ai-accounts.summary'))->assertOk()->json('data');
        $this->assertSame(0, $summary['cards']['total_accounts']);
        $this->assertSame(0, $summary['cards']['monthly_cost_running']);

        $costService = app(AiAccountCountableProposalCost::class);
        $this->assertSame(0, $costService->totalMonthly());
    }
}
