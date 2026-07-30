<?php

namespace Tests\Feature\Evaluation;

use App\Models\Department;
use App\Models\Evaluation\EvaluationCriterion;
use App\Models\SystemAccount;
use App\Support\Enums\EvaluationCriterionScope;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationCriterionTest extends TestCase
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

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'scope' => 'general',
            'criteria_name' => 'Thái độ hợp tác/tinh thần tập thể',
            'category' => 'Thái độ',
            'description' => 'Xem xét khả năng làm việc phối hợp.',
            'allow_half_score' => false,
            'score_1' => 'Không đáp ứng',
            'score_2' => 'Cần cố gắng hơn',
            'score_3' => 'Đạt yêu cầu',
            'score_4' => 'Tốt',
            'score_5' => 'Rất tốt',
            'is_active' => true,
        ], $overrides);
    }

    public function test_super_admin_can_view_index(): void
    {
        $this->actingAs($this->superAdmin(), 'system')
            ->get('/workspace-config/evaluation')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/Evaluation/Index')
                ->has('summary')
                ->has('criteria')
                ->has('nextCode')
                ->where('can.manage', true)
            );
    }

    public function test_admin_cannot_view_evaluation_criteria(): void
    {
        $this->actingAs($this->admin(), 'system')
            ->get('/workspace-config/evaluation')
            ->assertForbidden();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/workspace-config/evaluation')->assertRedirect('/login');
    }

    public function test_super_admin_can_create_general_criterion_with_auto_code(): void
    {
        $response = $this->actingAs($this->superAdmin(), 'system')
            ->post('/workspace-config/evaluation', $this->validPayload());

        $criterion = EvaluationCriterion::query()->where('criteria_name', 'Thái độ hợp tác/tinh thần tập thể')->first();
        $this->assertNotNull($criterion);
        $response->assertRedirect(route('workspace.evaluation.show', $criterion));
        $this->assertSame(EvaluationCriterionScope::General, $criterion->scope);
        $this->assertSame('1', $criterion->criteria_code);
        $this->assertNull($criterion->department_code);
        $this->assertSame('Không đáp ứng', $criterion->score_1);
        $this->assertFalse($criterion->allow_half_score);
    }

    public function test_super_admin_can_create_department_criterion(): void
    {
        Department::query()->create([
            'code' => 'HCNS',
            'name' => 'Hành Chính Nhân Sự',
            'color' => 'slate',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin(), 'system')
            ->post('/workspace-config/evaluation', $this->validPayload([
                'scope' => 'department',
                'department_code' => 'HCNS',
                'criteria_code' => '76',
                'allow_half_score' => true,
            ]));

        $criterion = EvaluationCriterion::query()->where('criteria_code', '76')->first();
        $this->assertNotNull($criterion);
        $response->assertRedirect(route('workspace.evaluation.show', $criterion));
        $this->assertSame(EvaluationCriterionScope::Department, $criterion->scope);
        $this->assertSame('HCNS', $criterion->department_code);
        $this->assertSame('Hành Chính Nhân Sự', $criterion->department_name);
        $this->assertTrue($criterion->allow_half_score);
        $this->assertStringContainsString('[Hành Chính Nhân Sự]', $criterion->displayName());
    }

    public function test_department_scope_requires_department_code(): void
    {
        $this->actingAs($this->superAdmin(), 'system')
            ->post('/workspace-config/evaluation', $this->validPayload([
                'scope' => 'department',
                'department_code' => '',
            ]))
            ->assertSessionHasErrors('department_code');
    }

    public function test_unique_criteria_code(): void
    {
        EvaluationCriterion::query()->create([
            'scope' => EvaluationCriterionScope::General,
            'criteria_code' => '10',
            'criteria_name' => 'Đã có',
            'category' => 'Khác',
            'score_1' => 'A',
            'score_2' => 'B',
            'score_3' => 'C',
            'score_4' => 'D',
            'score_5' => 'E',
            'is_active' => true,
        ]);

        $this->actingAs($this->superAdmin(), 'system')
            ->post('/workspace-config/evaluation', $this->validPayload([
                'criteria_code' => '10',
            ]))
            ->assertSessionHasErrors('criteria_code');
    }

    public function test_show_includes_activity_after_create(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user, 'system')
            ->post('/workspace-config/evaluation', $this->validPayload([
                'criteria_code' => '99',
            ]));

        $criterion = EvaluationCriterion::query()->where('criteria_code', '99')->firstOrFail();

        $this->actingAs($user, 'system')
            ->get(route('workspace.evaluation.show', $criterion))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/Evaluation/Show')
                ->where('criterion.criteria_code', '99')
                ->has('activity', 1)
            );
    }

    public function test_super_admin_can_soft_delete_criterion(): void
    {
        $criterion = EvaluationCriterion::query()->create([
            'scope' => EvaluationCriterionScope::General,
            'criteria_code' => '5',
            'criteria_name' => 'Xóa thử',
            'category' => 'Khác',
            'score_1' => 'A',
            'score_2' => 'B',
            'score_3' => 'C',
            'score_4' => 'D',
            'score_5' => 'E',
            'is_active' => true,
        ]);

        $this->actingAs($this->superAdmin(), 'system')
            ->delete("/workspace-config/evaluation/{$criterion->id}")
            ->assertRedirect(route('workspace.evaluation.index'));

        $this->assertSoftDeleted('evaluation_criteria', ['id' => $criterion->id]);
    }
}
