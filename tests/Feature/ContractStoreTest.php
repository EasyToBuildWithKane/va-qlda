<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\SystemAccount;
use App\Support\Enums\ContractStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractStoreTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Admin)->create();
    }

    public function test_admin_can_create_contract_without_status_field(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'system')
            ->post('/contracts', [
                'name' => 'Dịch vụ thử nghiệm',
                'links' => ['https://example.com/hop-dong.pdf'],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('created_contract_id');

        $contract = Contract::query()->where('name', 'Dịch vụ thử nghiệm')->first();
        $this->assertNotNull($contract);
        $this->assertSame(ContractStatus::Draft, $contract->status);
        $this->assertDatabaseHas('contract_attachments', [
            'contract_id' => $contract->id,
            'external_url' => 'https://example.com/hop-dong.pdf',
        ]);
    }

    public function test_create_contract_rejects_status_from_form(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'system')
            ->post('/contracts', [
                'name' => 'Dịch vụ thử nghiệm',
                'status' => ContractStatus::Draft->value,
            ])
            ->assertSessionHasErrors('status');
    }
}
