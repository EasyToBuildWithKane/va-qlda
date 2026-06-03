<?php

namespace Tests\Feature;

use App\Mail\AiAccountExpiryReminderMail;
use App\Models\AiAccount;
use App\Models\AiPurchaseProposal;
use App\Models\SystemAccount;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountStatus;
use App\Support\Enums\AiPurchaseProposalStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AiAccountTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): SystemAccount
    {
        $account = SystemAccount::factory()->create();

        $this->actingAs($account, 'system');

        return $account;
    }

    /** @return array<string, mixed> */
    private function proposalPayload(array $overrides = []): array
    {
        return array_merge([
            'subject_about' => 'Đăng ký sử dụng Cursor Pro',
            'send_to' => 'Phòng Công nghệ & Phòng Kế Toán',
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
        ], $overrides);
    }

    public function test_index_page_requires_auth(): void
    {
        $this->get(route('ai-accounts.index'))->assertRedirect(route('login'));
    }

    public function test_can_create_and_list_grouped(): void
    {
        $this->actingAsUser();

        $payload = [
            'tool_name' => 'GitHub Copilot',
            'license_type' => 'Business',
            'group_function' => AiAccountGroupFunction::Dev->value,
            'email_registered' => 'dev@example.com',
            'purchase_date' => now()->subMonths(2)->format('Y-m-d'),
            'expiry_date' => now()->addMonths(10)->format('Y-m-d'),
            'cost_amount' => 500_000,
            'cost_unit' => AiAccountCostUnit::Monthly->value,
            'notify_before_days' => 14,
        ];

        $this->postJson(route('api.ai-accounts.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('ai_accounts', [
            'tool_name' => 'GitHub Copilot',
            'status' => AiAccountStatus::Active->value,
        ]);

        $response = $this->getJson(route('api.ai-accounts.index'));
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['groups', 'banner', 'summary_cards']]);

        $groups = $response->json('data.groups');
        $this->assertNotEmpty($groups);
        $this->assertSame('DEV', $groups[0]['group']);
    }

    public function test_expiring_soon_status_on_near_expiry(): void
    {
        $this->actingAsUser();

        AiAccount::create([
            'tool_name' => 'ChatGPT',
            'license_type' => 'Team',
            'group_function' => AiAccountGroupFunction::Ba,
            'email_registered' => 'ba@example.com',
            'purchase_date' => now()->subYear(),
            'expiry_date' => now()->addDays(5),
            'cost_amount' => 1_200_000,
            'cost_unit' => AiAccountCostUnit::Monthly,
            'status' => AiAccountStatus::Active,
            'notify_before_days' => 14,
        ]);

        $response = $this->getJson(route('api.ai-accounts.index'));
        $response->assertOk();
        $account = collect($response->json('data.groups'))->flatMap(fn ($g) => $g['accounts'])->first();
        $this->assertSame('expiring_soon', $account['status']);
    }

    public function test_reminder_sends_email_to_admin_with_employee_email(): void
    {
        Mail::fake();
        config(['ai_accounts.reminder.send_email' => true]);

        $employee = \App\Models\Employee::factory()->create([
            'email' => 'admin-reminder@vaschools.test',
        ]);
        $admin = SystemAccount::factory()->role(\App\Support\Enums\SystemRole::Admin)->create([
            'employee_id' => $employee->id,
        ]);
        $this->actingAs($admin, 'system');

        AiAccount::create([
            'tool_name' => 'Copilot',
            'license_type' => 'Business',
            'group_function' => AiAccountGroupFunction::Dev,
            'email_registered' => 'dev@example.com',
            'purchase_date' => now()->subYear(),
            'expiry_date' => now()->addDays(3),
            'cost_amount' => 400_000,
            'cost_unit' => AiAccountCostUnit::Monthly,
            'status' => AiAccountStatus::Active,
            'notify_before_days' => 14,
        ]);

        $this->artisan('ai-accounts:send-reminders')->assertSuccessful();

        Mail::assertSent(AiAccountExpiryReminderMail::class, function (AiAccountExpiryReminderMail $mail) {
            return $mail->hasTo('admin-reminder@vaschools.test');
        });
    }

    public function test_soft_delete(): void
    {
        $this->actingAsUser();

        $model = AiAccount::create([
            'tool_name' => 'Temp',
            'license_type' => 'Pro',
            'group_function' => AiAccountGroupFunction::Other,
            'email_registered' => 'x@example.com',
            'purchase_date' => now()->subMonth(),
            'expiry_date' => now()->addMonth(),
            'cost_amount' => 100_000,
            'cost_unit' => AiAccountCostUnit::Monthly,
            'status' => AiAccountStatus::Active,
            'notify_before_days' => 14,
        ]);

        $this->deleteJson(route('api.ai-accounts.destroy', ['aiAccount' => $model->id]))
            ->assertOk();

        $this->assertSoftDeleted('ai_accounts', ['id' => $model->id]);
    }

    public function test_purchase_proposal_approve_and_reject(): void
    {
        $member = SystemAccount::factory()->create();
        $this->actingAs($member, 'system');

        $this->postJson(route('api.ai-accounts.proposals.store'), $this->proposalPayload())
            ->assertCreated()
            ->assertJsonPath('data.proposal.subject_about', 'Đăng ký sử dụng Cursor Pro');

        $proposal = AiPurchaseProposal::first();
        $this->assertSame(AiPurchaseProposalStatus::Pending, $proposal->status);

        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $this->actingAs($admin, 'system');

        $this->postJson(route('api.ai-accounts.proposals.approve', ['proposal' => $proposal->id]))
            ->assertOk();
        $this->assertSame(AiPurchaseProposalStatus::Approved, $proposal->fresh()->status);

        $this->actingAs($member, 'system');
        $this->postJson(route('api.ai-accounts.proposals.store'), $this->proposalPayload([
            'tool_name' => 'Rejected Tool',
            'subject_about' => 'Đăng ký Rejected Tool',
            'group_function' => AiAccountGroupFunction::Ba->value,
            'license_type' => 'Team',
            'cost_amount' => 500_000,
            'proposal_content' => 'Đề xuất thứ hai để kiểm tra từ chối có lý do đủ dài.',
        ]))->assertCreated();

        $pending = AiPurchaseProposal::query()->where('tool_name', 'Rejected Tool')->first();
        $this->actingAs($admin, 'system');

        $this->postJson(route('api.ai-accounts.proposals.reject', ['proposal' => $pending->id]), [
            'rejection_reason' => 'Chưa có ngân sách cho nhóm BA trong quý này.',
        ])->assertOk();

        $pending->refresh();
        $this->assertSame(AiPurchaseProposalStatus::Rejected, $pending->status);
        $this->assertNotEmpty($pending->rejection_reason);

        $summary = $this->getJson(route('api.ai-accounts.summary'))->assertOk()->json('data');
        $this->assertArrayHasKey('proposals', $summary);
        $this->assertSame(1, $summary['proposal_counts']['approved']);
    }

    public function test_purchase_proposal_export_pdf_and_docx(): void
    {
        $this->actingAsUser();

        $this->postJson(route('api.ai-accounts.proposals.store'), $this->proposalPayload())
            ->assertCreated();

        $proposal = AiPurchaseProposal::first();
        $this->assertNotNull($proposal);

        $this->get(route('api.ai-accounts.proposals.export.pdf', ['proposal' => $proposal->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->get(route('api.ai-accounts.proposals.export.docx', ['proposal' => $proposal->id]))
            ->assertOk()
            ->assertDownload();
    }
}
