<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\SystemAccount;
use App\Services\AiAccount\ReminderRecipientEmailResolver;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderRecipientEmailResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_synced_employee_email(): void
    {
        $employee = Employee::factory()->create([
            'email' => 'Synced.From.Cms@vaschools.test',
            'cms_user_id' => 1001,
        ]);
        $account = SystemAccount::factory()->forEmployee($employee)->create([
            'role' => SystemRole::Admin,
            'username' => 'other-username',
        ]);

        $email = app(ReminderRecipientEmailResolver::class)->resolve($account);

        $this->assertSame('synced.from.cms@vaschools.test', $email);
    }

    public function test_falls_back_to_login_username_when_it_is_email(): void
    {
        $employee = Employee::factory()->create([
            'email' => null,
            'cms_user_id' => null,
        ]);
        $account = SystemAccount::factory()->forEmployee($employee)->create([
            'username' => 'login.email@vaschools.test',
        ]);

        $email = app(ReminderRecipientEmailResolver::class)->resolve($account);

        $this->assertSame('login.email@vaschools.test', $email);
    }
}
