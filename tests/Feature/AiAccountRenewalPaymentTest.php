<?php

namespace Tests\Feature;

use App\Models\AiAccount;
use App\Models\SystemAccount;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountRenewalPaymentStatus;
use App\Support\Enums\AiAccountStatus;
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
        return AiAccount::create([
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
    }
}
