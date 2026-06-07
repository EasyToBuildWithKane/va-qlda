<?php

namespace Tests\Feature;

use App\Mail\AiAccountExpiryReminderMail;
use App\Models\AiAccount;
use App\Models\AiPaymentRequest;
use App\Models\AiPurchaseProposal;
use App\Models\SystemAccount;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountStatus;
use App\Support\Enums\AiPaymentRequestStatus;
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
            'registration_email' => 'proposer@vaschools.edu.vn',
        ], $overrides);
    }

    public function test_index_page_requires_auth(): void
    {
        $this->get(route('ai-accounts.index'))->assertRedirect(route('login'));
    }

    public function test_cost_by_group_page_renders_for_authenticated_user(): void
    {
        $this->actingAsUser();

        $this->get(route('ai-accounts.cost-by-group'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AiAccount/CostByGroup')
                ->has('options.group_function')
            );
    }

    public function test_cost_report_page_renders_for_authenticated_user(): void
    {
        $this->actingAsUser();

        $this->get(route('ai-accounts.cost-report'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AiAccount/CostReport')
                ->has('options.proposal_type')
                ->has('form_lookups.employees')
                ->has('form_lookups.tools')
                ->has('form_lookups.account_templates')
            );
    }

    public function test_employee_search_for_proposal_picker(): void
    {
        $this->actingAsUser();

        $employee = \App\Models\Employee::factory()->create([
            'full_name' => 'Bùi Quang Toàn',
            'email' => 'toan.bui@hcm.vaschools.edu.vn',
            'code' => 'NV-TOAN',
        ]);

        $byQuery = $this->getJson(route('api.ai-accounts.employees.search', ['q' => 'Quang Toàn']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data.employees');

        $this->assertNotEmpty($byQuery);
        $this->assertSame('Bùi Quang Toàn', $byQuery[0]['name']);

        $byId = $this->getJson(route('api.ai-accounts.employees.search', ['id' => $employee->id]))
            ->assertOk()
            ->json('data.employees');

        $this->assertCount(1, $byId);
        $this->assertSame($employee->id, $byId[0]['id']);

        $folded = $this->getJson(route('api.ai-accounts.employees.search', ['q' => 'bui quang toan']))
            ->assertOk()
            ->json('data.employees');

        $this->assertNotEmpty($folded);
    }

    public function test_can_create_and_list_grouped(): void
    {
        $user = $this->actingAsUser();

        $this->postJson(route('api.ai-accounts.proposals.store'), $this->proposalPayload([
            'tool_name' => 'GitHub Copilot',
            'subject_about' => 'Đăng ký GitHub Copilot',
            'registration_email' => 'dev@example.com',
        ]))->assertCreated();

        $proposal = AiPurchaseProposal::query()->where('tool_name', 'GitHub Copilot')->first();
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

        $this->actingAs($user, 'system');
        $this->postJson(route('api.ai-accounts.store'), [
            'proposal_id' => $proposal->id,
            'email_registered' => 'dev@example.com',
            'notify_before_days' => 14,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('ai_accounts', [
            'tool_name' => 'GitHub Copilot',
            'status' => AiAccountStatus::Active->value,
        ]);

        $response = $this->getJson(route('api.ai-accounts.index'));
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['groups', 'banner', 'summary_cards', 'proposal_counts', 'awaiting_account_count'],
            ]);

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
        $proposal->refresh();
        $this->assertSame(AiPurchaseProposalStatus::Approved, $proposal->status);
        $this->assertNull($proposal->ai_account_id);

        AiPaymentRequest::create([
            'ai_purchase_proposal_id' => $proposal->id,
            'amount' => $proposal->cost_amount,
            'status' => AiPaymentRequestStatus::Approved,
            'created_by' => $admin->id,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $awaiting = $this->getJson(route('api.ai-accounts.proposals.awaiting-account'))
            ->assertOk()
            ->json('data.proposals');
        $this->assertNotEmpty(collect($awaiting)->firstWhere('id', $proposal->id));

        $summary = $this->getJson(route('api.ai-accounts.summary'))->assertOk()->json('data');
        $devRow = collect($summary['by_group'])->firstWhere('group', 'DEV');
        $this->assertNotNull($devRow);
        $this->assertGreaterThanOrEqual(1_000_000, $devRow['cost_monthly'] ?? 0);

        $this->postJson(route('api.ai-accounts.store'), [
            'proposal_id' => $proposal->id,
            'email_registered' => 'cursor@vaschools.edu.vn',
            'password' => 'secret-pass',
            'notify_before_days' => 14,
        ])->assertCreated();

        $proposal->refresh();
        $this->assertNotNull($proposal->ai_account_id);
        $this->assertDatabaseHas('ai_accounts', [
            'id' => $proposal->ai_account_id,
            'tool_name' => 'Cursor Pro',
            'email_registered' => 'cursor@vaschools.edu.vn',
        ]);

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

    public function test_purchase_proposal_destroy(): void
    {
        $member = SystemAccount::factory()->create();
        $this->actingAs($member, 'system');

        $this->postJson(route('api.ai-accounts.proposals.store'), $this->proposalPayload([
            'tool_name' => 'To Delete',
            'subject_about' => 'Đăng ký To Delete',
        ]))->assertCreated();

        $proposal = AiPurchaseProposal::query()->where('tool_name', 'To Delete')->first();
        $this->assertNotNull($proposal);

        $this->deleteJson(route('api.ai-accounts.proposals.destroy', ['proposal' => $proposal->id]))
            ->assertForbidden();

        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $this->actingAs($admin, 'system');

        $this->deleteJson(route('api.ai-accounts.proposals.destroy', ['proposal' => $proposal->id]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('ai_purchase_proposals', ['id' => $proposal->id]);
    }

    public function test_purchase_proposal_index_filters_by_group_and_dates(): void
    {
        $this->actingAsUser();

        $this->postJson(route('api.ai-accounts.proposals.store'), $this->proposalPayload([
            'tool_name' => 'Filter Tool A',
            'subject_about' => 'Đăng ký Filter Tool A',
            'group_function' => AiAccountGroupFunction::Dev->value,
        ]))->assertCreated();

        $this->postJson(route('api.ai-accounts.proposals.store'), $this->proposalPayload([
            'tool_name' => 'Filter Tool B',
            'subject_about' => 'Đăng ký Filter Tool B',
            'group_function' => AiAccountGroupFunction::Ba->value,
            'license_type' => 'Team',
            'cost_amount' => 800_000,
        ]))->assertCreated();

        $res = $this->getJson(route('api.ai-accounts.proposals.index', [
            'group_function' => AiAccountGroupFunction::Ba->value,
            'tool_name' => 'Filter Tool B',
        ]))->assertOk()->json('data');

        $this->assertCount(1, $res['proposals']);
        $this->assertSame('Filter Tool B', $res['proposals'][0]['tool_name']);
        $this->assertSame(2, $res['counts']['total']);
        $this->assertSame(1, $res['filtered_counts']['total']);
    }

    public function test_purchase_proposal_update_when_pending(): void
    {
        $member = SystemAccount::factory()->create();
        $this->actingAs($member, 'system');

        $this->postJson(route('api.ai-accounts.proposals.store'), $this->proposalPayload([
            'tool_name' => 'Edit Me Tool',
            'subject_about' => 'Đăng ký Edit Me Tool',
        ]))->assertCreated();

        $proposal = AiPurchaseProposal::query()->where('tool_name', 'Edit Me Tool')->first();
        $this->assertNotNull($proposal);

        $this->putJson(route('api.ai-accounts.proposals.update', ['proposal' => $proposal->id]), $this->proposalPayload([
            'tool_name' => 'Edited Tool',
            'subject_about' => 'Đăng ký Edited Tool',
            'cost_amount' => 2_000_000,
        ]))
            ->assertOk()
            ->assertJsonPath('data.proposal.tool_name', 'Edited Tool');

        $this->assertSame('Edited Tool', $proposal->fresh()->tool_name);
    }

    public function test_purchase_proposal_export_pdf_and_payment_request(): void
    {
        $this->actingAsUser();

        $this->postJson(route('api.ai-accounts.proposals.store'), $this->proposalPayload([
            'purchase_type' => 'renewal',
            'cost_amount' => 550_000,
            'planned_use_date' => '2026-08-06',
        ]))
            ->assertCreated();

        $proposal = AiPurchaseProposal::first();
        $this->assertNotNull($proposal);

        $this->get(route('api.ai-accounts.proposals.export.pdf', ['proposal' => $proposal->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->get(route('api.ai-accounts.proposals.export.payment-request.pdf', ['proposal' => $proposal->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_summary_cost_counts_only_approved_proposals(): void
    {
        $this->actingAsUser();

        AiAccount::create([
            'tool_name' => 'No Proposal Tool',
            'license_type' => 'Pro',
            'group_function' => AiAccountGroupFunction::Dev,
            'email_registered' => 'orphan@example.com',
            'purchase_date' => now()->subMonth(),
            'expiry_date' => now()->addMonth(),
            'cost_amount' => 9_000_000,
            'cost_unit' => AiAccountCostUnit::Monthly,
            'status' => AiAccountStatus::Active,
            'notify_before_days' => 14,
        ]);

        $this->postJson(route('api.ai-accounts.proposals.store'), $this->proposalPayload([
            'tool_name' => 'Pending Tool',
            'cost_amount' => 2_000_000,
        ]))->assertCreated();

        $pending = AiPurchaseProposal::query()->where('tool_name', 'Pending Tool')->first();
        $pending->update(['status' => AiPurchaseProposalStatus::Pending]);

        $this->postJson(route('api.ai-accounts.proposals.store'), $this->proposalPayload([
            'tool_name' => 'Approved Only',
            'cost_amount' => 600_000,
        ]))->assertCreated();

        $approved = AiPurchaseProposal::query()->where('tool_name', 'Approved Only')->first();
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $this->actingAs($admin, 'system');
        $this->postJson(route('api.ai-accounts.proposals.approve', ['proposal' => $approved->id]))->assertOk();

        $cards = $this->getJson(route('api.ai-accounts.index'))
            ->assertOk()
            ->json('data.summary_cards');

        $this->assertSame(600_000, $cards['monthly_cost_running']);
        $this->assertSame(600_000, $cards['monthly_cost_all']);

        $accountRow = collect($this->getJson(route('api.ai-accounts.index'))->json('data.groups'))
            ->flatMap(fn ($g) => $g['accounts'])
            ->firstWhere('tool_name', 'No Proposal Tool');
        $this->assertFalse($accountRow['cost_in_budget']);
        $this->assertSame(0, $accountRow['budget_cost_monthly']);
        $this->assertFalse($accountRow['show_renewal_payment']);
    }

    // ─── ĐNTT workflow gate tests ─────────────────────────────────────────────

    /** @test */
    public function cannot_create_account_without_payment_request(): void
    {
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $proposal = $this->approvedProposal($admin);

        $this->actingAs($admin, 'system');
        $this->postJson(route('api.ai-accounts.store'), [
            'proposal_id' => $proposal->id,
            'email_registered' => 'test@example.com',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.proposal_id.0', fn ($msg) => str_contains($msg, 'đề nghị thanh toán'));
    }

    /** @test */
    public function cannot_create_account_when_payment_request_still_pending(): void
    {
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $proposal = $this->approvedProposal($admin);

        AiPaymentRequest::create([
            'ai_purchase_proposal_id' => $proposal->id,
            'amount' => $proposal->cost_amount,
            'status' => AiPaymentRequestStatus::Pending,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'system');
        $this->postJson(route('api.ai-accounts.store'), [
            'proposal_id' => $proposal->id,
            'email_registered' => 'test@example.com',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.proposal_id.0', fn ($msg) => str_contains($msg, 'chưa được duyệt'));
    }

    /** @test */
    public function can_create_account_when_payment_request_is_approved(): void
    {
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $proposal = $this->approvedProposal($admin);

        AiPaymentRequest::create([
            'ai_purchase_proposal_id' => $proposal->id,
            'amount' => $proposal->cost_amount,
            'status' => AiPaymentRequestStatus::Approved,
            'created_by' => $admin->id,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($admin, 'system');
        $this->postJson(route('api.ai-accounts.store'), [
            'proposal_id' => $proposal->id,
            'email_registered' => 'user@example.com',
        ])->assertCreated();

        $this->assertDatabaseHas('ai_accounts', ['email_registered' => 'user@example.com']);
    }

    /** @test */
    public function mark_paid_splits_kpi_from_approved(): void
    {
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $proposal = $this->approvedProposal($admin, 1_000_000);

        $pr = AiPaymentRequest::create([
            'ai_purchase_proposal_id' => $proposal->id,
            'amount' => 1_000_000,
            'status' => AiPaymentRequestStatus::Approved,
            'created_by' => $admin->id,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($admin, 'system');

        $metrics = $this->getJson(route('api.ai-accounts.summary'))
            ->assertOk()
            ->json('data.workflow_metrics');

        $this->assertSame(1_000_000, $metrics['budget_payment_approved_total']);
        $this->assertSame(0, $metrics['budget_paid_total']);

        $this->postJson(route('api.ai-accounts.payment-requests.mark-paid', ['paymentRequest' => $pr->id]))
            ->assertOk();

        $metrics2 = $this->getJson(route('api.ai-accounts.summary'))
            ->assertOk()
            ->json('data.workflow_metrics');

        $this->assertSame(1_000_000, $metrics2['budget_paid_total']);
    }

    /** @test */
    public function workflow_metrics_do_not_count_proposal_approved_as_paid(): void
    {
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $this->approvedProposal($admin, 500_000);

        $this->actingAs($admin, 'system');

        $metrics = $this->getJson(route('api.ai-accounts.summary'))
            ->assertOk()
            ->json('data.workflow_metrics');

        $this->assertSame(500_000, $metrics['budget_proposal_approved_total']);
        $this->assertSame(0, $metrics['budget_paid_total'],
            'Phiếu đề xuất đã duyệt không được tính vào budget_paid_total khi ĐNTT chưa ghi nhận thanh toán');
    }

    /** Helper: tạo PĐX đã duyệt */
    private function approvedProposal(SystemAccount $admin, int $amount = 500_000): AiPurchaseProposal
    {
        $member = SystemAccount::factory()->role(SystemRole::Member)->create();
        $this->actingAs($member, 'system');
        $this->postJson(route('api.ai-accounts.proposals.store'), $this->proposalPayload([
            'cost_amount' => $amount,
        ]))->assertCreated();

        $proposal = AiPurchaseProposal::query()->latest()->first();

        $this->actingAs($admin, 'system');
        $this->postJson(route('api.ai-accounts.proposals.approve', ['proposal' => $proposal->id]))
            ->assertOk();

        return $proposal->fresh();
    }
}
