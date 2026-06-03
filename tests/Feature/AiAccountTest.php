<?php

namespace Tests\Feature;

use App\Mail\AiAccountExpiryReminderMail;
use App\Models\AiAccount;
use App\Models\SystemAccount;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountStatus;
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
}
