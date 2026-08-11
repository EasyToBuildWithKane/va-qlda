<?php

namespace Tests\Feature;

use App\Models\AiAccount;
use App\Models\SystemAccount;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiAccountTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): SystemAccount
    {
        return SystemAccount::factory()->create([
            'role' => SystemRole::Admin->value,
        ]);
    }

    private function accountPayload(array $overrides = []): array
    {
        return array_merge([
            'tool_name' => 'ChatGPT Team',
            'group_function' => AiAccountGroupFunction::Dev->value,
            'email_registered' => 'ai.dev@vaschools.edu.vn',
            'password' => 'secret-pass',
            'purchase_date' => now()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
            'cost_amount' => 500_000,
            'cost_unit' => AiAccountCostUnit::Monthly->value,
            'notify_before_days' => 14,
            'proposal_sent_at' => now()->subDays(10)->toDateString(),
            'payment_request_sent_at' => now()->subDays(5)->toDateString(),
            'notes' => 'Ghi chú test',
        ], $overrides);
    }

    public function test_index_page_renders(): void
    {
        $this->actingAs($this->admin(), 'system')
            ->get(route('ai-accounts.index'))
            ->assertOk();
    }

    public function test_cost_report_page_renders(): void
    {
        $this->actingAs($this->admin(), 'system')
            ->get(route('ai-accounts.cost-report'))
            ->assertOk();
    }

    public function test_store_update_summary_and_destroy(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $this->actingAs($admin, 'system');

        $proposalFile = UploadedFile::fake()->create('pdx.pdf', 100, 'application/pdf');
        $paymentFile = UploadedFile::fake()->create('dntt.pdf', 100, 'application/pdf');

        $create = $this->post(route('api.ai-accounts.store'), array_merge(
            $this->accountPayload([
                'proposal_approved_at' => now()->subDays(7)->toDateString(),
            ]),
            [
                'proposal_documents' => [$proposalFile],
                'payment_request_documents' => [$paymentFile],
            ],
        ));
        $create->assertCreated();
        $id = $create->json('data.account.id');
        $this->assertNotEmpty($id);

        $account = AiAccount::query()->findOrFail($id);
        $this->assertSame('ChatGPT Team', $account->tool_name);
        $this->assertSame('ai.dev@vaschools.edu.vn', $account->email_registered);
        $this->assertSame(500_000, $account->cost_amount);
        $this->assertNotEmpty($account->proposal_document_paths);
        $this->assertCount(1, $account->proposal_document_paths);
        $this->assertNotEmpty($account->payment_request_document_paths);
        $this->assertSame(
            now()->subDays(7)->toDateString(),
            $account->proposal_approved_at?->toDateString(),
        );
        $this->assertSame('secret-pass', $account->login_password);

        $summary = $this->getJson(route('api.ai-accounts.summary'));
        $summary->assertOk();
        $this->assertSame(1, $summary->json('data.cards.total_accounts'));
        $this->assertSame(500_000, $summary->json('data.cards.monthly_cost_all'));

        $this->putJson(route('api.ai-accounts.update', ['aiAccount' => $id]), $this->accountPayload([
            'tool_name' => 'ChatGPT Team Plus',
            'cost_amount' => 600_000,
            'password' => null,
            'notes' => 'Đã cập nhật',
        ]))->assertOk();

        $account->refresh();
        $this->assertSame('ChatGPT Team Plus', $account->tool_name);
        $this->assertSame(600_000, $account->cost_amount);
        $this->assertSame('secret-pass', $account->login_password);

        $this->deleteJson(route('api.ai-accounts.destroy', ['aiAccount' => $id]))
            ->assertOk();
        $this->assertSoftDeleted('ai_accounts', ['id' => $id]);
    }

    public function test_member_without_create_cannot_store(): void
    {
        $member = SystemAccount::factory()->create([
            'role' => SystemRole::Member->value,
        ]);
        $this->actingAs($member, 'system');

        $this->postJson(route('api.ai-accounts.store'), $this->accountPayload())
            ->assertForbidden();
    }
}
