<?php

namespace Tests\Feature\Evaluation;

use App\Models\Department;
use App\Models\Evaluation\EvaluationConfig;
use App\Models\Evaluation\EvaluationTemplate;
use App\Models\SystemAccount;
use App\Support\Enums\EvaluationTemplateType;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationConfigTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::SuperAdmin)->create();
    }

    private function admin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Admin)->create();
    }

    public function test_super_admin_can_view_index(): void
    {
        $this->actingAs($this->superAdmin(), 'system')
            ->get('/workspace-config/evaluation')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/Evaluation/Index')
                ->has('summary')
                ->has('templates')
                ->where('can.manage', true)
            );
    }

    public function test_admin_cannot_view_evaluation_config(): void
    {
        $this->actingAs($this->admin(), 'system')
            ->get('/workspace-config/evaluation')
            ->assertForbidden();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/workspace-config/evaluation')->assertRedirect('/login');
    }

    public function test_super_admin_can_create_config_from_template(): void
    {
        $template = EvaluationTemplate::query()
            ->where('template_type', EvaluationTemplateType::PointSystem)
            ->firstOrFail();

        Department::query()->create([
            'code' => 'HCNS',
            'name' => 'Hành Chính Nhân Sự',
            'color' => 'slate',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin(), 'system')
            ->post('/workspace-config/evaluation', [
                'department_code' => 'HCNS',
                'department_name' => 'Hành Chính Nhân Sự',
                'template_id' => $template->id,
                'template_type' => 'point_system',
                'config_name' => 'ĐG HCNS T1/2026',
                'description' => 'Bộ quy tắc tháng 1',
                'effective_from' => '2026-01-01',
                'effective_to' => '2026-01-31',
                'base_score' => 100,
                'is_active' => true,
                'apply_template' => true,
            ]);

        $config = EvaluationConfig::query()->where('config_name', 'ĐG HCNS T1/2026')->first();
        $this->assertNotNull($config);
        $response->assertRedirect(route('workspace.evaluation.show', $config));
        $this->assertGreaterThan(10, $config->criteria()->count());
        $this->assertSame(100, $config->base_score);
    }

    public function test_unique_department_type_effective_from(): void
    {
        $user = $this->superAdmin();

        EvaluationConfig::query()->create([
            'department_code' => 'HCNS',
            'department_name' => 'Hành Chính Nhân Sự',
            'template_type' => EvaluationTemplateType::PointSystem,
            'config_name' => 'Đã có',
            'effective_from' => '2026-01-01',
            'base_score' => 100,
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user, 'system')
            ->post('/workspace-config/evaluation', [
                'department_code' => 'HCNS',
                'department_name' => 'Hành Chính Nhân Sự',
                'template_type' => 'point_system',
                'config_name' => 'Trùng',
                'effective_from' => '2026-01-01',
                'base_score' => 100,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('effective_from');
    }

    public function test_soft_delete_config(): void
    {
        $user = $this->superAdmin();
        $config = EvaluationConfig::query()->create([
            'department_code' => 'CNTT',
            'department_name' => 'Công Nghệ Thông Tin',
            'template_type' => EvaluationTemplateType::Scorecard,
            'config_name' => 'ĐG CNTT',
            'effective_from' => '2026-05-01',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user, 'system')
            ->delete("/workspace-config/evaluation/{$config->id}")
            ->assertRedirect(route('workspace.evaluation.index'));

        $this->assertSoftDeleted('evaluation_configs', ['id' => $config->id]);
    }

    public function test_templates_are_seeded_by_migration(): void
    {
        $this->assertSame(2, EvaluationTemplate::query()->count());
        $this->assertTrue(
            EvaluationTemplate::query()->where('template_type', EvaluationTemplateType::PointSystem)->exists()
        );
        $this->assertTrue(
            EvaluationTemplate::query()->where('template_type', EvaluationTemplateType::Scorecard)->exists()
        );
    }
}
