<?php

namespace Tests\Feature;

use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_dashboard_requires_auth(): void
    {
        $this->getJson(route('api.ai-accounts.analytics.dashboard'))
            ->assertUnauthorized();
    }

    public function test_analytics_dashboard_returns_kpi_payload(): void
    {
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();

        $response = $this->actingAs($admin, 'system')
            ->getJson(route('api.ai-accounts.analytics.dashboard', ['granularity' => 'month']));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'kpis' => [
                        'accounts_in_use',
                        'cost_current_month',
                        'cost_forecast_year_end',
                        'usage_rate_percent',
                        'monthly_run_rate_change_percent',
                    ],
                    'cost_over_time' => ['labels', 'datasets'],
                    'by_product',
                    'by_department',
                    'alerts',
                ],
            ]);
    }

    public function test_analytics_report_page_renders(): void
    {
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();

        $this->actingAs($admin, 'system')
            ->get(route('ai-accounts.analytics'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('AiAccount/AnalyticsReport'));
    }

    public function test_executive_dashboard_page_renders(): void
    {
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();

        $this->actingAs($admin, 'system')
            ->get(route('ai-accounts.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('AiAccount/Dashboard'));
    }
}
