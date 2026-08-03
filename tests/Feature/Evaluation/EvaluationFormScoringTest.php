<?php

namespace Tests\Feature\Evaluation;

use App\Models\Employee;
use App\Models\Evaluation\EvaluationCriterion;
use App\Models\Evaluation\EvaluationForm;
use App\Models\Evaluation\EvaluationFormAssignee;
use App\Models\Evaluation\EvaluationFormSubmission;
use App\Models\Evaluation\EvaluationFormType;
use App\Models\SystemAccount;
use App\Support\Enums\EvaluationCriterionScope;
use App\Support\Enums\EvaluationFormOrder;
use App\Support\Enums\EvaluationFormPeriodKind;
use App\Support\Enums\EvaluationFormStatus;
use App\Support\Enums\EvaluationFormSubmissionStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationFormScoringTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::SuperAdmin)->create();
    }

    private function accountFor(Employee $employee): SystemAccount
    {
        return SystemAccount::factory()
            ->role(SystemRole::Member)
            ->forEmployee($employee)
            ->create();
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
     * @return array{form: EvaluationForm, assignee: EvaluationFormAssignee, self: Employee, head: Employee, manager: Employee, criterionId: int}
     */
    private function seedActiveForm(bool $sequential = false): array
    {
        $type = $this->defaultType();
        $manager = Employee::factory()->create();
        $self = Employee::factory()->create();
        $head = Employee::factory()->create();
        $direct = Employee::factory()->create();
        $criterion = $this->criterion();

        $form = EvaluationForm::query()->create([
            'form_code' => 'PDG901',
            'name' => 'Phiếu chấm test',
            'type_id' => $type->id,
            'period_kind' => EvaluationFormPeriodKind::Month,
            'period_month' => 8,
            'period_year' => 2026,
            'auto_create_next' => false,
            'manager_employee_id' => $manager->id,
            'deadline' => '2026-08-31',
            'evaluation_order' => $sequential
                ? EvaluationFormOrder::Sequential
                : EvaluationFormOrder::Parallel,
            'use_weight' => true,
            'status' => EvaluationFormStatus::Draft,
            'created_by' => null,
        ]);

        $form->raters()->createMany([
            ['role_key' => 'self', 'label' => 'Tự ĐG', 'weight_percent' => 50, 'sort_order' => 0],
            ['role_key' => 'dept_head', 'label' => 'Trưởng phòng', 'weight_percent' => 50, 'sort_order' => 1],
        ]);

        $formCriterion = $form->criteria()->create([
            'criterion_id' => $criterion->id,
            'name' => $criterion->criteria_name,
            'weight' => 100,
            'required_score_label' => 'Đạt yêu cầu',
            'evaluator_mode' => 'all',
            'evaluator_role_keys' => [],
            'sort_order' => 0,
        ]);

        $assignee = $form->assignees()->create([
            'employee_id' => $self->id,
            'employee_code' => $self->code,
            'employee_name' => $self->full_name,
            'dept_head_employee_id' => $head->id,
            'direct_manager_employee_id' => $direct->id,
            'board_employee_id' => null,
            'sort_order' => 0,
        ]);

        return [
            'form' => $form,
            'assignee' => $assignee,
            'self' => $self,
            'head' => $head,
            'manager' => $manager,
            'criterionId' => $formCriterion->id,
            'levels' => $criterion->normalizedScoreLevels(),
        ];
    }

    public function test_can_open_and_close_form(): void
    {
        $admin = $this->superAdmin();
        $seed = $this->seedActiveForm();

        $this->actingAs($admin, 'system')
            ->post('/workspace-config/evaluation-forms/'.$seed['form']->id.'/open')
            ->assertRedirect();

        $this->assertSame(EvaluationFormStatus::Active, $seed['form']->fresh()->status);

        $this->actingAs($admin, 'system')
            ->post('/workspace-config/evaluation-forms/'.$seed['form']->id.'/close')
            ->assertRedirect();

        $this->assertSame(EvaluationFormStatus::Closed, $seed['form']->fresh()->status);
    }

    public function test_self_can_save_and_submit_scores(): void
    {
        $seed = $this->seedActiveForm();
        $seed['form']->update(['status' => EvaluationFormStatus::Active]);
        $account = $this->accountFor($seed['self']);
        // Grant hub view so they can reach scoring pages
        $account->forceFill([
            // permissions come from role defaults — member has hub.view
        ])->save();

        $level = $seed['levels'][2] ?? $seed['levels'][0];

        $payload = [
            'rater_role_key' => 'self',
            'comment' => 'Tự nhận xét',
            'lines' => [[
                'form_criterion_id' => $seed['criterionId'],
                'score_level_code' => $level['code'] ?? null,
                'score_level_label' => $level['label'] ?? 'Mức',
                'score_weight' => $level['weight'] ?? 3,
            ]],
            'field_values' => [],
        ];

        $this->actingAs($account, 'system')
            ->put(
                '/workspace-config/evaluation-forms/'.$seed['form']->id.'/scoring/'.$seed['assignee']->id,
                $payload
            )
            ->assertRedirect();

        $sub = EvaluationFormSubmission::query()
            ->where('assignee_id', $seed['assignee']->id)
            ->where('rater_role_key', 'self')
            ->first();
        $this->assertNotNull($sub);
        $this->assertSame(EvaluationFormSubmissionStatus::Draft, $sub->status);

        $this->actingAs($account, 'system')
            ->post(
                '/workspace-config/evaluation-forms/'.$seed['form']->id.'/scoring/'.$seed['assignee']->id.'/submit',
                $payload
            )
            ->assertRedirect();

        $sub->refresh();
        $this->assertSame(EvaluationFormSubmissionStatus::Submitted, $sub->status);
        $this->assertNotNull($sub->total_score);
    }

    public function test_sequential_blocks_second_rater_until_first_submits(): void
    {
        $seed = $this->seedActiveForm(sequential: true);
        $seed['form']->update(['status' => EvaluationFormStatus::Active]);
        $headAccount = $this->accountFor($seed['head']);
        $level = $seed['levels'][0];

        $payload = [
            'rater_role_key' => 'dept_head',
            'lines' => [[
                'form_criterion_id' => $seed['criterionId'],
                'score_level_code' => $level['code'] ?? null,
                'score_level_label' => $level['label'] ?? 'Mức',
                'score_weight' => $level['weight'] ?? 1,
            ]],
        ];

        $this->actingAs($headAccount, 'system')
            ->put(
                '/workspace-config/evaluation-forms/'.$seed['form']->id.'/scoring/'.$seed['assignee']->id,
                $payload
            )
            ->assertForbidden();
    }

    public function test_admin_can_score_any_role(): void
    {
        $admin = $this->superAdmin();
        $seed = $this->seedActiveForm();
        $seed['form']->update(['status' => EvaluationFormStatus::Active]);
        $level = $seed['levels'][1] ?? $seed['levels'][0];

        $this->actingAs($admin, 'system')
            ->post(
                '/workspace-config/evaluation-forms/'.$seed['form']->id.'/scoring/'.$seed['assignee']->id.'/submit',
                [
                    'rater_role_key' => 'self',
                    'lines' => [[
                        'form_criterion_id' => $seed['criterionId'],
                        'score_level_code' => $level['code'] ?? null,
                        'score_level_label' => $level['label'] ?? 'Mức',
                        'score_weight' => $level['weight'] ?? 2,
                    ]],
                ]
            )
            ->assertRedirect();

        $this->assertDatabaseHas('evaluation_form_submissions', [
            'assignee_id' => $seed['assignee']->id,
            'rater_role_key' => 'self',
            'status' => 'submitted',
        ]);
    }

    public function test_closed_form_rejects_scoring(): void
    {
        $seed = $this->seedActiveForm();
        $seed['form']->update(['status' => EvaluationFormStatus::Closed]);
        $account = $this->accountFor($seed['self']);
        $level = $seed['levels'][0];

        $this->actingAs($account, 'system')
            ->put(
                '/workspace-config/evaluation-forms/'.$seed['form']->id.'/scoring/'.$seed['assignee']->id,
                [
                    'rater_role_key' => 'self',
                    'lines' => [[
                        'form_criterion_id' => $seed['criterionId'],
                        'score_level_code' => $level['code'] ?? null,
                        'score_level_label' => $level['label'] ?? 'Mức',
                        'score_weight' => $level['weight'] ?? 1,
                    ]],
                ]
            )
            ->assertForbidden();
    }

    public function test_scoring_index_renders_when_active(): void
    {
        $admin = $this->superAdmin();
        $seed = $this->seedActiveForm();
        $seed['form']->update(['status' => EvaluationFormStatus::Active]);

        $this->actingAs($admin, 'system')
            ->get('/workspace-config/evaluation-forms/'.$seed['form']->id.'/scoring')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/EvaluationForms/Scoring/Index')
                ->has('progress')
                ->has('raters'));
    }
}
