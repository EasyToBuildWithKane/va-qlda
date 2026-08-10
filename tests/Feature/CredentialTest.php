<?php

namespace Tests\Feature;

use App\Models\Credential;
use App\Models\SystemAccount;
use App\Support\Enums\CredentialCategory;
use App\Support\Enums\CredentialType;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CredentialTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Admin)->create();
    }

    public function test_admin_can_view_credentials_index(): void
    {
        $response = $this->actingAs($this->admin(), 'system')
            ->get(route('credentials.index'));

        $response->assertOk();
    }

    public function test_admin_can_create_credential(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'system')
            ->post(route('credentials.store'), [
                'name' => 'CMS Production',
                'credential_type' => CredentialType::InternalSystem->value,
                'system_category' => CredentialCategory::Cms->value,
                'environment' => 'production',
                'login_password' => 'secret-pass',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('credentials', [
            'name' => 'CMS Production',
            'owner_id' => null,
        ]);

        $credential = Credential::first();
        $this->assertNotNull($credential);
        $this->assertSame('secret-pass', $credential->login_password);
    }

    public function test_admin_can_create_aws_infrastructure_credential(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'system')
            ->post(route('credentials.store'), [
                'name' => 'AWS Root Console',
                'credential_type' => CredentialType::Infrastructure->value,
                'system_category' => CredentialCategory::Aws->value,
                'environment' => 'production',
                'provider_name' => 'AWS',
                'login_url' => 'https://console.aws.amazon.com/',
                'login_password' => 'aws-secret',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('credentials', [
            'name' => 'AWS Root Console',
            'credential_type' => CredentialType::Infrastructure->value,
            'system_category' => CredentialCategory::Aws->value,
            'provider_name' => 'AWS',
        ]);
    }

    public function test_viewer_cannot_create_credential(): void
    {
        $viewer = SystemAccount::factory()->role(SystemRole::Viewer)->create();

        $response = $this->actingAs($viewer, 'system')
            ->post(route('credentials.store'), [
                'name' => 'Blocked',
                'credential_type' => CredentialType::InternalSystem->value,
                'system_category' => CredentialCategory::Cms->value,
                'environment' => 'production',
            ]);

        $response->assertForbidden();
    }

    public function test_admin_can_open_credential_edit_page(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'system')
            ->post(route('credentials.store'), [
                'name' => 'Edit me',
                'credential_type' => CredentialType::InternalSystem->value,
                'system_category' => CredentialCategory::Cms->value,
                'environment' => 'production',
            ]);

        $credential = Credential::query()->where('name', 'Edit me')->firstOrFail();

        $response = $this->actingAs($admin, 'system')
            ->get(route('credentials.edit', $credential));

        $response->assertOk();
    }
}
