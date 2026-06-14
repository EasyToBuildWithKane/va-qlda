<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SystemAccount;
use App\Support\Auth\CoachingOnlyAccess;
use App\Support\Enums\SystemRole;
use App\Support\Navigation;
use Database\Seeders\CoachingGoogleGuestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachingGoogleGuestTest extends TestCase
{
    use RefreshDatabase;

    public function test_coaching_guest_seeder_creates_employee_and_account(): void
    {
        $this->seed(CoachingGoogleGuestSeeder::class);

        $employee = Employee::query()
            ->where('email', CoachingGoogleGuestSeeder::GUEST_EMAIL)
            ->first();

        $this->assertNotNull($employee);
        $this->assertTrue($employee->is_active);

        $account = SystemAccount::query()->where('employee_id', $employee->id)->first();
        $this->assertNotNull($account);
        $this->assertSame(SystemRole::Member, $account->role);
    }

    public function test_coaching_only_user_sees_only_coaching_nav(): void
    {
        config(['va.google_allowed_emails' => [CoachingGoogleGuestSeeder::GUEST_EMAIL]]);

        $employee = Employee::factory()->create([
            'email' => CoachingGoogleGuestSeeder::GUEST_EMAIL,
        ]);
        $account = SystemAccount::factory()->forEmployee($employee)->create();

        $nav = Navigation::for($account);

        $this->assertCount(1, $nav);
        $this->assertSame('coaching', $nav[0]['key']);
    }

    public function test_coaching_only_user_redirected_from_dashboard(): void
    {
        config(['va.google_allowed_emails' => [CoachingGoogleGuestSeeder::GUEST_EMAIL]]);

        $employee = Employee::factory()->create([
            'email' => CoachingGoogleGuestSeeder::GUEST_EMAIL,
        ]);
        $account = SystemAccount::factory()->forEmployee($employee)->create();

        $this->actingAs($account, 'system')
            ->get('/dashboard')
            ->assertRedirect(route('coaching.dashboard'));
    }

    public function test_google_email_whitelist_allows_gmail_without_opening_domain(): void
    {
        config([
            'va.google_allowed_domains' => ['vaschools.edu.vn'],
            'va.google_allowed_emails' => [CoachingGoogleGuestSeeder::GUEST_EMAIL],
        ]);

        $this->assertTrue(CoachingOnlyAccess::googleEmailAllowed(CoachingGoogleGuestSeeder::GUEST_EMAIL));
        $this->assertFalse(CoachingOnlyAccess::googleEmailAllowed('other@gmail.com'));
    }
}
