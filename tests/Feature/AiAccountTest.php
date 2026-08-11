<?php

namespace Tests\Feature;

use App\Models\AiAccount;
use App\Models\SystemAccount;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountLoginMethod;
use App\Support\Enums\AiAccountPurchaseType;
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
            'login_method' => AiAccountLoginMethod::Password->value,
            'password' => 'secret-pass',
            'purchase_date' => now()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
            'cost_amount' => 500_000,
            'cost_unit' => AiAccountCostUnit::Monthly->value,
            'purchase_type' => AiAccountPurchaseType::New->value,
            'notify_before_days' => 14,
            'proposal_sent_at' => now()->subDays(10)->toDateString(),
            'payment_request_sent_at' => now()->subDays(5)->toDateString(),
            'purchase_url' => 'https://chatgpt.com/team',
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
        $this->assertSame($admin->id, $account->created_by);
        $this->assertSame(AiAccountLoginMethod::Password, $account->login_method);
        $this->assertSame('https://chatgpt.com/team', $account->purchase_url);
        $this->assertSame(AiAccountPurchaseType::New, $account->purchase_type);
        $this->assertSame(500_000, $account->cost_amount);
        $this->assertNotEmpty($account->proposal_document_paths);
        $this->assertCount(1, $account->proposal_document_paths);
        $this->assertSame('secret-pass', $account->login_password);

        $summary = $this->getJson(route('api.ai-accounts.summary'));
        $summary->assertOk();
        $this->assertSame(1, $summary->json('data.cards.total_accounts'));

        $this->putJson(route('api.ai-accounts.update', ['aiAccount' => $id]), $this->accountPayload([
            'tool_name' => 'ChatGPT Team Plus',
            'cost_amount' => 600_000,
            'password' => null,
            'notes' => 'Đã cập nhật',
        ]))->assertOk();

        $account->refresh();
        $this->assertSame('ChatGPT Team Plus', $account->tool_name);
        $this->assertSame(600_000, $account->cost_amount);

        $this->deleteJson(route('api.ai-accounts.destroy', ['aiAccount' => $id]))
            ->assertOk();
        $this->assertSoftDeleted('ai_accounts', ['id' => $id]);
    }

    public function test_store_google_login_clears_password(): void
    {
        $this->actingAs($this->admin(), 'system');

        $create = $this->postJson(route('api.ai-accounts.store'), $this->accountPayload([
            'login_method' => AiAccountLoginMethod::Google->value,
            'password' => null,
            'proposal_sent_at' => null,
            'payment_request_sent_at' => null,
        ]));
        $create->assertCreated();

        $account = AiAccount::query()->findOrFail($create->json('data.account.id'));
        $this->assertSame(AiAccountLoginMethod::Google, $account->login_method);
        $this->assertNull($account->login_password);
    }

    public function test_member_without_create_cannot_store(): void
    {
        $member = SystemAccount::factory()->create([
            'role' => SystemRole::Member->value,
        ]);
        $this->actingAs($member, 'system');

        $this->postJson(route('api.ai-accounts.store'), $this->accountPayload([
            'proposal_sent_at' => null,
            'payment_request_sent_at' => null,
        ]))->assertForbidden();
    }
}
