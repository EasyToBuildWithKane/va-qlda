<?php

namespace Tests\Feature;

use App\Models\AiAccount;
use App\Models\AiAccountAccessGrant;
use App\Models\SystemAccount;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountLoginMethod;
use App\Support\Enums\AiAccountStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAccountAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): SystemAccount
    {
        return SystemAccount::factory()->create([
            'role' => SystemRole::Admin->value,
        ]);
    }

    private function member(): SystemAccount
    {
        return SystemAccount::factory()->create([
            'role' => SystemRole::Member->value,
        ]);
    }

    private function seedAccount(SystemAccount $creator): AiAccount
    {
        return AiAccount::query()->create([
            'created_by' => $creator->id,
            'tool_name' => 'Claude Pro',
            'group_function' => AiAccountGroupFunction::Dev,
            'email_registered' => 'claude@vaschools.edu.vn',
            'login_method' => AiAccountLoginMethod::Password,
            'purchase_date' => now()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
            'cost_amount' => 400_000,
            'cost_unit' => AiAccountCostUnit::Monthly,
            'status' => AiAccountStatus::Active,
            'notify_before_days' => 14,
            'proposal_document_paths' => [],
            'payment_request_document_paths' => [],
        ]);
    }

    public function test_member_cannot_see_account_without_grant(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $this->seedAccount($admin);

        $this->actingAs($member, 'system');
        $res = $this->getJson(route('api.ai-accounts.index'));
        $res->assertOk();
        $this->assertSame([], $res->json('data.groups'));
    }

    public function test_member_sees_account_after_grant(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $account = $this->seedAccount($admin);

        AiAccountAccessGrant::query()->create([
            'ai_account_id' => $account->id,
            'account_id' => $member->id,
            'permissions' => ['view'],
            'granted_by' => $admin->id,
        ]);

        $this->actingAs($member, 'system');
        $res = $this->getJson(route('api.ai-accounts.index'));
        $res->assertOk();
        $groups = $res->json('data.groups');
        $this->assertNotEmpty($groups);
        $ids = collect($groups)->flatMap(fn ($g) => collect($g['accounts'])->pluck('id'))->all();
        $this->assertContains($account->id, $ids);
    }

    public function test_creator_can_grant_and_revoke_access(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $account = $this->seedAccount($admin);

        $this->actingAs($admin, 'system');

        $grant = $this->postJson(route('api.ai-accounts.access-grants.store', ['aiAccount' => $account->id]), [
            'account_id' => $member->id,
            'permissions' => ['view', 'view_password'],
        ]);
        $grant->assertOk();
        $grantId = $grant->json('data.id');
        $this->assertNotEmpty($grantId);

        $list = $this->getJson(route('api.ai-accounts.access-grants.index', ['aiAccount' => $account->id]));
        $list->assertOk();
        $this->assertCount(1, $list->json('data'));

        $this->deleteJson(route('api.ai-accounts.access-grants.destroy', [
            'aiAccount' => $account->id,
            'accessGrant' => $grantId,
        ]))->assertOk();

        $this->assertDatabaseMissing('ai_account_access_grants', ['id' => $grantId]);
    }
}
