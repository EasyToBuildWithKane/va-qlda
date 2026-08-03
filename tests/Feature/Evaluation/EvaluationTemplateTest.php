<?php

namespace Tests\Feature\Evaluation;

use App\Models\Employee;
use App\Models\Evaluation\EvaluationCriterion;
use App\Models\Evaluation\EvaluationTemplate;
use App\Models\Evaluation\EvaluationTemplateExportLog;
use App\Models\SystemAccount;
use App\Support\Enums\EvaluationCriterionScope;
use App\Support\Enums\SystemRole;
use App\Support\Evaluation\HrmPositionDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EvaluationTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function superAdmin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::SuperAdmin)->create();
    }

    private function criterion(array $overrides = []): EvaluationCriterion
    {
        return EvaluationCriterion::query()->create(array_merge([
            'scope' => EvaluationCriterionScope::General,
            'criteria_code' => 'TCVA'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT).random_int(10, 99),
            'criteria_name' => 'Tiêu chí mẫu',
            'category' => 'Thái độ',
            'allow_half_score' => false,
            'score_levels' => EvaluationCriterion::DEFAULT_SCORE_LEVELS,
            'sort_order' => 1,
            'is_active' => true,
        ], $overrides));
    }

    public function test_super_admin_can_view_index(): void
    {
        $this->actingAs($this->superAdmin(), 'system')
            ->get('/workspace-config/evaluation-templates')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/EvaluationTemplates/Index')
                ->has('summary')
                ->has('templates')
                ->has('exportLogs')
                ->has('positions')
                ->has('jobTitles')
                ->has('jobRanks')
                ->has('fieldTypeOptions')
                ->has('criteriaOptions'));
    }

    public function test_can_create_template_with_targets_custom_criteria_and_fields(): void
    {
        $user = $this->superAdmin();
        $c1 = $this->criterion(['criteria_code' => 'TCVA911', 'criteria_name' => 'Thái độ']);

        $this->actingAs($user, 'system')
            ->post('/workspace-config/evaluation-templates', [
                'name' => 'Mẫu đầy đủ',
                'is_active' => true,
                'titles' => [
                    ['code' => 'TITLE_CV_KD', 'name' => 'Chuyên viên Kinh doanh', 'source' => 'directory'],
                ],
                'ranks' => [],
                'criteria' => [
                    [
                        'source' => 'catalog',
                        'criterion_id' => $c1->id,
                        'weight' => 1,
                        'include_in_total' => true,
                    ],
                    [
                        'source' => 'custom',
                        'custom_name' => 'Doanh số quý',
                        'custom_category' => 'KPI',
                        'weight' => 2,
                        'required_score_label' => 'Đạt',
                        'include_in_total' => true,
                    ],
                ],
                'fields' => [
                    [
                        'field_key' => 'period',
                        'label' => 'Kỳ đánh giá',
                        'field_type' => 'text',
                        'is_required' => true,
                    ],
                    [
                        'field_key' => 'grade',
                        'label' => 'Xếp loại',
                        'field_type' => 'select',
                        'options' => ['Tốt', 'Khá'],
                        'is_required' => false,
                    ],
                ],
            ])
            ->assertRedirect();

        $template = EvaluationTemplate::query()->where('name', 'Mẫu đầy đủ')->first();
        $this->assertNotNull($template);
        $this->assertSame('Chuyên viên Kinh doanh', $template->position_name);
        $this->assertSame(1, $template->templateCriteria()->count());
        $this->assertSame(1, $template->customCriteria()->count());
        $this->assertSame(1, $template->targets()->count());
        $this->assertSame(2, $template->fields()->count());
        $this->assertDatabaseHas('evaluation_template_custom_criteria', [
            'template_id' => $template->id,
            'custom_name' => 'Doanh số quý',
        ]);
        $this->assertDatabaseHas('evaluation_template_fields', [
            'template_id' => $template->id,
            'field_key' => 'period',
            'is_required' => 1,
        ]);
    }

    public function test_create_page_renders_form_catalogs(): void
    {
        $this->actingAs($this->superAdmin(), 'system')
            ->get('/workspace-config/evaluation-templates/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/EvaluationTemplates/Create')
                ->has('jobTitles')
                ->has('jobRanks')
                ->has('fieldTypeOptions')
                ->has('criteriaOptions')
                ->has('nextCode'));
    }

    public function test_cannot_create_template_with_both_titles_and_ranks(): void
    {
        $this->actingAs($this->superAdmin(), 'system')
            ->from('/workspace-config/evaluation-templates/create')
            ->post('/workspace-config/evaluation-templates', [
                'name' => 'Mẫu XOR',
                'is_active' => true,
                'titles' => [
                    ['code' => 'TITLE_CV', 'name' => 'Chuyên viên', 'source' => 'directory'],
                ],
                'ranks' => [
                    ['code' => 'RANK_M1', 'name' => 'M1', 'source' => 'directory'],
                ],
            ])
            ->assertRedirect('/workspace-config/evaluation-templates/create')
            ->assertSessionHasErrors('titles');

        $this->assertNull(EvaluationTemplate::query()->where('name', 'Mẫu XOR')->first());
    }

    public function test_store_redirects_to_show(): void
    {
        $user = $this->superAdmin();

        $response = $this->actingAs($user, 'system')
            ->post('/workspace-config/evaluation-templates', [
                'name' => 'Mẫu redirect',
                'is_active' => true,
                'criteria' => [],
            ]);

        $template = EvaluationTemplate::query()->where('name', 'Mẫu redirect')->first();
        $this->assertNotNull($template);
        $response->assertRedirect('/workspace-config/evaluation-templates/'.$template->id);
    }

    public function test_can_create_template_with_criteria(): void
    {
        $user = $this->superAdmin();
        $c1 = $this->criterion(['criteria_code' => 'TCVA901', 'criteria_name' => 'Thái độ xử lý']);
        $c2 = $this->criterion(['criteria_code' => 'TCVA902', 'criteria_name' => 'Kỹ năng bán hàng', 'category' => 'Kỹ năng']);

        $this->actingAs($user, 'system')
            ->post('/workspace-config/evaluation-templates', [
                'name' => 'Đánh giá chuyên viên kinh doanh',
                'position_name' => 'Chuyên viên Kinh doanh',
                'is_active' => true,
                'criteria' => [
                    [
                        'criterion_id' => $c1->id,
                        'weight' => 1,
                        'required_score_label' => 'Điểm yêu cầu 1',
                        'include_in_total' => true,
                    ],
                    [
                        'criterion_id' => $c2->id,
                        'weight' => 2,
                        'required_score_label' => 'Điểm yêu cầu 2',
                        'include_in_total' => true,
                    ],
                ],
            ])
            ->assertRedirect();

        $template = EvaluationTemplate::query()->where('name', 'Đánh giá chuyên viên kinh doanh')->first();
        $this->assertNotNull($template);
        $this->assertNotEmpty($template->template_code);
        $this->assertSame(2, $template->templateCriteria()->count());
        $this->assertSame('Chuyên viên Kinh doanh', $template->position_name);
    }

    public function test_can_show_and_duplicate_template(): void
    {
        $user = $this->superAdmin();
        $criterion = $this->criterion(['criteria_code' => 'TCVA903']);
        $template = EvaluationTemplate::query()->create([
            'template_code' => 'MDG001',
            'name' => 'Mẫu gốc',
            'position_code' => 'POS_TEST',
            'position_name' => 'Tester',
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        $template->templateCriteria()->create([
            'criterion_id' => $criterion->id,
            'weight' => 1,
            'required_score_label' => 'Điểm yêu cầu 1',
            'include_in_total' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($user, 'system')
            ->get('/workspace-config/evaluation-templates/'.$template->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/EvaluationTemplates/Show')
                ->where('template.template_code', 'MDG001')
                ->has('activity'));

        $this->actingAs($user, 'system')
            ->post('/workspace-config/evaluation-templates/'.$template->id.'/duplicate')
            ->assertRedirect();

        $copy = EvaluationTemplate::query()->where('name', 'Mẫu gốc (bản sao)')->first();
        $this->assertNotNull($copy);
        $this->assertNotSame('MDG001', $copy->template_code);
        $this->assertSame(1, $copy->templateCriteria()->count());
    }

    public function test_import_creates_templates_in_transaction(): void
    {
        $user = $this->superAdmin();
        $criterion = $this->criterion(['criteria_code' => 'TCVA904']);

        $this->actingAs($user, 'system')
            ->post('/workspace-config/evaluation-templates/import', [
                'rows' => [
                    [
                        'name' => 'Mẫu import 1',
                        'criteria' => [
                            ['criterion_id' => $criterion->id, 'weight' => 1, 'include_in_total' => true],
                        ],
                    ],
                    [
                        'name' => 'Mẫu import 2',
                        'template_code' => 'MDG050',
                        'position_name' => 'Lead',
                        'criteria' => [],
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, EvaluationTemplate::query()->count());
        $this->assertDatabaseHas('evaluation_templates', ['template_code' => 'MDG050']);
    }

    public function test_record_export_creates_log_and_json(): void
    {
        $user = $this->superAdmin();

        $response = $this->actingAs($user, 'system')
            ->postJson('/workspace-config/evaluation-templates/export-logs', [
                'scope' => 'all',
                'format' => 'xlsx',
                'row_count' => 3,
                'columns' => ['name', 'template_code', 'position'],
                'filename' => 'VA_MauDanhGia_2026-07-31.xlsx',
            ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(1, EvaluationTemplateExportLog::query()->count());
        $this->assertDatabaseHas('security_audit_logs', [
            'action' => 'evaluation.template_exported',
            'subject_type' => 'evaluation_template',
        ]);
    }

    public function test_can_update_and_delete_template(): void
    {
        $user = $this->superAdmin();
        $template = EvaluationTemplate::query()->create([
            'template_code' => 'MDG010',
            'name' => 'Cũ',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user, 'system')
            ->put('/workspace-config/evaluation-templates/'.$template->id, [
                'name' => 'Mới',
                'template_code' => 'MDG010',
                'is_active' => false,
                'criteria' => [],
            ])
            ->assertRedirect();

        $this->assertSame('Mới', $template->fresh()->name);
        $this->assertFalse((bool) $template->fresh()->is_active);

        $this->actingAs($user, 'system')
            ->delete('/workspace-config/evaluation-templates/'.$template->id)
            ->assertRedirect('/workspace-config/evaluation-templates');

        $this->assertSoftDeleted('evaluation_templates', ['id' => $template->id]);
    }

    public function test_position_directory_from_employees(): void
    {
        Employee::factory()->create([
            'is_active' => true,
            'role_title' => 'Chuyên viên Kinh doanh',
            'meta' => ['position_name' => 'Trưởng nhóm kỹ thuật'],
        ]);

        app(HrmPositionDirectory::class)->forget();
        $list = app(HrmPositionDirectory::class)->all(true);

        $names = collect($list)->pluck('name')->all();
        $this->assertContains('Chuyên viên Kinh doanh', $names);
        $this->assertContains('Trưởng nhóm kỹ thuật', $names);
    }
}
