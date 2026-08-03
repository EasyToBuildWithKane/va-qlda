<?php

namespace Tests\Feature\Evaluation;

use App\Models\Employee;
use App\Models\Evaluation\EvaluationCriterion;
use App\Models\Evaluation\EvaluationForm;
use App\Models\Evaluation\EvaluationFormType;
use App\Models\Evaluation\EvaluationTemplate;
use App\Models\SystemAccount;
use App\Support\Enums\EvaluationCriterionScope;
use App\Support\Enums\EvaluationFormOrder;
use App\Support\Enums\EvaluationFormPeriodKind;
use App\Support\Enums\EvaluationFormStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationFormTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::SuperAdmin)->create();
    }

    private function member(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Member)->create();
    }

    private function defaultType(): EvaluationFormType
    {
        return EvaluationFormType::query()->firstOrCreate(
            ['name' => 'Đánh giá định kỳ'],
            ['sort_order' => 0, 'is_active' => true],
        );
    }

    private function criterion(): EvaluationCriterion
    {
        return EvaluationCriterion::query()->create([
            'scope' => EvaluationCriterionScope::General,
            'criteria_code' => 'TCVA'.random_int(100, 999),
            'criteria_name' => 'Tiêu chí mẫu',
            'category' => 'Thái độ',
            'allow_half_score' => false,
            'score_levels' => EvaluationCriterion::DEFAULT_SCORE_LEVELS,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        $type = $this->defaultType();
        $manager = Employee::factory()->create();
        $employee = Employee::factory()->create();
        $deptHead = Employee::factory()->create();
        $direct = Employee::factory()->create();
        $criterion = $this->criterion();

        return array_merge([
            'name' => 'Phiếu ĐG CNTT T8',
            'type_id' => $type->id,
            'period_kind' => EvaluationFormPeriodKind::Month->value,
            'period_month' => 8,
            'period_year' => 2026,
            'auto_create_next' => false,
            'manager_employee_id' => $manager->id,
            'deadline' => '2026-08-31',
            'evaluation_order' => EvaluationFormOrder::Parallel->value,
            'use_weight' => true,
            'status' => EvaluationFormStatus::Draft->value,
            'watcher_ids' => [],
            'raters' => [
                ['role_key' => 'self', 'label' => 'Nhân viên tự đánh giá', 'weight_percent' => 25, 'sort_order' => 0],
                ['role_key' => 'dept_head', 'label' => 'Trưởng phòng đánh giá', 'weight_percent' => 25, 'sort_order' => 1],
                ['role_key' => 'direct_manager', 'label' => 'Quản lý trực tiếp đánh giá', 'weight_percent' => 25, 'sort_order' => 2],
                ['role_key' => 'board', 'label' => 'Ban giám đốc đánh giá', 'weight_percent' => 25, 'sort_order' => 3],
            ],
            'fields' => [
                ['field_key' => 'evaluator_comment', 'label' => 'Ý kiến người đánh giá', 'field_type' => 'textarea', 'is_enabled' => true],
                ['field_key' => 'self_next_plan', 'label' => 'Kế hoạch bản thân trong lần tới', 'field_type' => 'textarea', 'is_enabled' => true],
            ],
            'criteria' => [
                [
                    'criterion_id' => $criterion->id,
                    'name' => $criterion->criteria_name,
                    'weight' => 100,
                    'required_score_label' => 'Đạt yêu cầu',
                    'evaluator_mode' => 'all',
                    'evaluator_role_keys' => [],
                ],
            ],
            'assignees' => [
                [
                    'employee_id' => $employee->id,
                    'employee_code' => $employee->code,
                    'employee_name' => $employee->full_name,
                    'dept_head_employee_id' => $deptHead->id,
                    'direct_manager_employee_id' => $direct->id,
                    'board_employee_id' => null,
                ],
            ],
        ], $overrides);
    }

    public function test_super_admin_can_view_index(): void
    {
        $this->actingAs($this->superAdmin(), 'system')
            ->get('/workspace-config/evaluation-forms')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/EvaluationForms/Index')
                ->has('summary')
                ->has('forms')
                ->has('statusOptions')
                ->has('typeOptions'));
    }

    public function test_member_cannot_create_form(): void
    {
        $this->actingAs($this->member(), 'system')
            ->get('/workspace-config/evaluation-forms/create')
            ->assertForbidden();
    }

    public function test_can_create_form_with_relations(): void
    {
        $user = $this->superAdmin();
        $payload = $this->validPayload(['name' => 'Phiếu đầy đủ']);

        $this->actingAs($user, 'system')
            ->post('/workspace-config/evaluation-forms', $payload)
            ->assertRedirect();

        $form = EvaluationForm::query()->where('name', 'Phiếu đầy đủ')->first();
        $this->assertNotNull($form);
        $this->assertNotNull($form->form_code);
        $this->assertSame(EvaluationFormStatus::Draft, $form->status);
        $this->assertCount(4, $form->raters);
        $this->assertCount(2, $form->fields);
        $this->assertCount(1, $form->criteria);
        $this->assertCount(1, $form->assignees);
    }

    public function test_use_weight_requires_sum_100(): void
    {
        $user = $this->superAdmin();
        $payload = $this->validPayload([
            'raters' => [
                ['role_key' => 'self', 'label' => 'Self', 'weight_percent' => 10, 'sort_order' => 0],
            ],
        ]);

        $this->actingAs($user, 'system')
            ->post('/workspace-config/evaluation-forms', $payload)
            ->assertSessionHasErrors('raters');
    }

    public function test_can_update_form(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user, 'system')
            ->post('/workspace-config/evaluation-forms', $this->validPayload(['name' => 'Phiếu cũ']))
            ->assertRedirect();

        $form = EvaluationForm::query()->where('name', 'Phiếu cũ')->firstOrFail();

        $this->actingAs($user, 'system')
            ->put('/workspace-config/evaluation-forms/'.$form->id, $this->validPayload([
                'name' => 'Phiếu mới',
                'status' => EvaluationFormStatus::Active->value,
            ]))
            ->assertRedirect();

        $form->refresh();
        $this->assertSame('Phiếu mới', $form->name);
        $this->assertSame(EvaluationFormStatus::Active, $form->status);
    }

    public function test_can_quick_create_type(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user, 'system')
            ->from('/workspace-config/evaluation-forms/create')
            ->post('/workspace-config/evaluation-forms/types', [
                'name' => 'Đánh giá thử việc',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('evaluation_form_types', [
            'name' => 'Đánh giá thử việc',
            'is_active' => 1,
        ]);
    }

    public function test_create_from_template_prefills_criteria(): void
    {
        $user = $this->superAdmin();
        $criterion = $this->criterion();
        $template = EvaluationTemplate::query()->create([
            'template_code' => 'MDG901',
            'name' => 'Mẫu test',
            'is_active' => true,
            'sort_order' => 1,
            'created_by' => $user->id,
        ]);
        $template->templateCriteria()->create([
            'criterion_id' => $criterion->id,
            'weight' => 50,
            'required_score_label' => 'Đạt yêu cầu',
            'include_in_total' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($user, 'system')
            ->get('/workspace-config/evaluation-forms/create?template_id='.$template->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/EvaluationForms/Create')
                ->where('prefill.template_id', $template->id)
                ->has('prefill.criteria', 1));
    }

    public function test_template_criteria_json_endpoint(): void
    {
        $user = $this->superAdmin();
        $criterion = $this->criterion();
        $template = EvaluationTemplate::query()->create([
            'template_code' => 'MDG902',
            'name' => 'Mẫu JSON',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $template->templateCriteria()->create([
            'criterion_id' => $criterion->id,
            'weight' => 100,
            'include_in_total' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($user, 'system')
            ->getJson('/workspace-config/evaluation-forms/templates/'.$template->id.'/criteria')
            ->assertOk()
            ->assertJsonCount(1, 'criteria');
    }
}
