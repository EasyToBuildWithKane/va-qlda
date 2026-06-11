<?php

namespace Tests\Feature;

use App\Models\AiAccount;
use App\Models\AiPurchaseProposal;
use App\Models\SystemAccount;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountRenewalPaymentStatus;
use App\Support\Enums\AiAccountStatus;
use App\Support\Enums\AiPurchaseProposalStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AiAccountRenewalPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_can_mark_renewal_paid(): void
    {
        $lead = SystemAccount::factory()->role(SystemRole::Lead)->create();
        $account = $this->makeExpiredAccount();

        $this->actingAs($lead, 'system')
            ->patchJson(route('api.ai-accounts.update-renewal-payment', ['aiAccount' => $account->id]), [
                'renewal_payment_status' => AiAccountRenewalPaymentStatus::Paid->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.account.renewal_payment_status', 'paid');

        $account->refresh();
        $this->assertSame(AiAccountRenewalPaymentStatus::Paid, $account->renewal_payment_status);
        $this->assertNotNull($account->renewal_paid_at);
    }

    public function test_unpaid_expired_account_gets_payment_reminder(): void
    {
        Mail::fake();
        config(['ai_accounts.reminder.send_email' => true]);

        SystemAccount::factory()->role(SystemRole::Admin)->create();
        $this->makeExpiredAccount();

        $sent = app(\App\Services\AiAccount\AiAccountReminderService::class)->sendUnpaidRenewalReminders();

        $this->assertSame(1, $sent);
        Mail::assertSent(\App\Mail\AiAccountRenewalUnpaidReminderMail::class);
    }

    public function test_summary_includes_running_cost_and_renewal_payment_counts(): void
    {
        $this->actingAs(SystemAccount::factory()->role(SystemRole::Lead)->create(), 'system');

        $this->makeExpiredAccount();
        $expiring = AiAccount::create([
            'tool_name' => 'ChatGPT',
            'license_type' => 'Plus',
            'group_function' => AiAccountGroupFunction::Ba,
            'email_registered' => 'ba@test.com',
            'purchase_date' => now()->subMonths(6),
            'expiry_date' => now()->addDays(10),
            'cost_amount' => 300_000,
            'cost_unit' => AiAccountCostUnit::Monthly,
            'status' => AiAccountStatus::ExpiringSoon,
            'renewal_payment_status' => AiAccountRenewalPaymentStatus::Paid,
            'renewal_paid_at' => now(),
            'notify_before_days' => 14,
        ]);
        $this->attachApprovedProposal($expiring);

        $cards = $this->getJson(route('api.ai-accounts.index'))
            ->assertOk()
            ->json('data.summary_cards');

        $this->assertSame(2, $cards['total_accounts']);
        $this->assertSame(800_000, $cards['monthly_cost_running']);
        $this->assertSame(2, $cards['renewal_due_count']);
        $this->assertSame(1, $cards['renewal_unpaid_count']);
        $this->assertSame(1, $cards['renewal_paid_count']);
        $this->assertSame(500_000, $cards['monthly_cost_unpaid_renewal']);
    }

    public function test_expired_without_approved_proposal_skips_payment_reminder(): void
    {
        Mail::fake();

        SystemAccount::factory()->role(SystemRole::Admin)->create();
        AiAccount::create([
            'tool_name' => 'Orphan Tool',
            'license_type' => 'Pro',
            'group_function' => AiAccountGroupFunction::Dev,
            'email_registered' => 'orphan@test.com',
            'purchase_date' => now()->subYear(),
            'expiry_date' => now()->subDays(2),
            'cost_amount' => 900_000,
            'cost_unit' => AiAccountCostUnit::Monthly,
            'status' => AiAccountStatus::Expired,
            'renewal_payment_status' => AiAccountRenewalPaymentStatus::Unpaid,
            'notify_before_days' => 14,
        ]);

        $sent = app(\App\Services\AiAccount\AiAccountReminderService::class)->sendUnpaidRenewalReminders();

        $this->assertSame(0, $sent);
        Mail::assertNothingSent();
    }

    public function test_paid_expired_account_skips_payment_reminder(): void
    {
        Mail::fake();

        SystemAccount::factory()->role(SystemRole::Admin)->create();
        $account = $this->makeExpiredAccount();
        $account->update([
            'renewal_payment_status' => AiAccountRenewalPaymentStatus::Paid,
            'renewal_paid_at' => now(),
        ]);

        $sent = app(\App\Services\AiAccount\AiAccountReminderService::class)->sendUnpaidRenewalReminders();

        $this->assertSame(0, $sent);
        Mail::assertNothingSent();
    }

    private function makeExpiredAccount(): AiAccount
    {
        $account = AiAccount::create([
            'tool_name' => 'Cursor Pro',
            'license_type' => 'Pro',
            'group_function' => AiAccountGroupFunction::Dev,
            'email_registered' => 'dev@test.com',
            'purchase_date' => now()->subYear(),
            'expiry_date' => now()->subDays(3),
            'cost_amount' => 500_000,
            'cost_unit' => AiAccountCostUnit::Monthly,
            'status' => AiAccountStatus::Expired,
            'renewal_payment_status' => AiAccountRenewalPaymentStatus::Unpaid,
            'notify_before_days' => 14,
        ]);

        $this->attachApprovedProposal($account);

        return $account;
    }

    private function attachApprovedProposal(AiAccount $account, ?int $costAmount = null): AiPurchaseProposal
    {
        $creator = SystemAccount::factory()->create();

        $proposal = AiPurchaseProposal::create([
            'tool_name' => $account->tool_name,
            'group_function' => $account->group_function,
            'license_type' => $account->license_type,
            'cost_amount' => $costAmount ?? $account->cost_amount,
            'cost_unit' => $account->cost_unit,
            'status' => AiPurchaseProposalStatus::Approved,
            'created_by' => $creator->id,
            'ai_account_id' => $account->id,
            'proposer_name' => 'Test',
            'justification' => 'Phiếu test chi phí từ đề xuất đã duyệt.',
        ]);
        $account->update(['ai_purchase_proposal_id' => $proposal->id]);

        return $proposal;
    }
}
