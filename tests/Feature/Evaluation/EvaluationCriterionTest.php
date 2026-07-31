<?php

namespace Tests\Feature\Evaluation;

use App\Models\Department;
use App\Models\Evaluation\EvaluationCriterion;
use App\Models\SystemAccount;
use App\Support\Enums\EvaluationCriterionScope;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EvaluationCriterionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'hrm.api.base_url' => 'https://hrm.test/api/v1',
            'hrm.api.token' => '1|test-token',
            'hrm.api.verify_ssl' => false,
        ]);
        Http::fake([
            'https://hrm.test/api/v1/org-units*' => Http::response([
                'data' => [],
                'meta' => ['cursor' => ['next' => null, 'count' => 0, 'per_page' => 100]],
            ]),
        ]);
    }

    private function superAdmin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::SuperAdmin)->create();
    }

    private function admin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Admin)->create();
    }

    /** @return list<array{label: string, weight: int}> */
    private function defaultLevels(): array
    {
        return [
            ['label' => 'Không đáp ứng', 'weight' => -2],
            ['label' => 'Cần cố gắng hơn', 'weight' => -1],
            ['label' => 'Đạt yêu cầu', 'weight' => 0],
            ['label' => 'Tốt', 'weight' => 1],
            ['label' => 'Rất tốt', 'weight' => 2],
        ];
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
            'score_levels' => $this->defaultLevels(),
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
                ->where('nextCode', 'TCVA001')
                ->where('can.manage', true)
            );
    }

    public function test_admin_without_department_cannot_view_evaluation_criteria(): void
    {
        $this->actingAs($this->admin(), 'system')
            ->get('/workspace-config/evaluation')
            ->assertForbidden();
    }

    public function test_member_with_department_can_view_scoped_evaluation_index(): void
    {
        Department::query()->create([
            'code' => 'HCNS',
            'name' => 'Hành Chính Nhân Sự',
            'color' => 'slate',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        Cache::flush();

        $employee = \App\Models\Employee::factory()->create([
            'meta' => [
                'department_code' => 'HCNS',
                'department_name' => 'Hành Chính Nhân Sự',
            ],
        ]);
        $member = SystemAccount::factory()
            ->role(SystemRole::Member)
            ->forEmployee($employee)
            ->create();

        $this->actingAs($member, 'system')
            ->get('/workspace-config/evaluation')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/Evaluation/Index')
                ->where('can.manage', false)
                ->where('viewer.forced_department_code', 'HCNS')
            );
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/workspace-config/evaluation')->assertRedirect('/login');
    }

    public function test_super_admin_can_create_general_criterion_with_auto_code(): void
    {
        $response = $this->actingAs($this->superAdmin(), 'system')
            ->from(route('workspace.evaluation.index'))
            ->post('/workspace-config/evaluation', $this->validPayload());

        $criterion = EvaluationCriterion::query()->where('criteria_name', 'Thái độ hợp tác/tinh thần tập thể')->first();
        $this->assertNotNull($criterion);
        $response->assertRedirect(route('workspace.evaluation.index'));
        $response->assertSessionHas('success', 'Đã tạo tiêu chí đánh giá.');
        $this->assertSame(EvaluationCriterionScope::General, $criterion->scope);
        $this->assertSame('TCVA001', $criterion->criteria_code);
        $this->assertNull($criterion->department_code);
        $this->assertCount(5, $criterion->score_levels);
        $this->assertSame('Không đáp ứng', $criterion->score_levels[0]['label']);
        $this->assertSame(-2, $criterion->score_levels[0]['weight']);
        $this->assertFalse($criterion->allow_half_score);
    }

    public function test_super_admin_can_create_criterion_with_custom_level_count(): void
    {
        $response = $this->actingAs($this->superAdmin(), 'system')
            ->from(route('workspace.evaluation.index'))
            ->post('/workspace-config/evaluation', $this->validPayload([
                'criteria_name' => 'Tiêu chí 3 mức',
                'score_levels' => [
                    ['label' => 'Không đạt', 'weight' => -2],
                    ['label' => 'Đạt', 'weight' => 0],
                    ['label' => 'Vượt', 'weight' => 2],
                ],
            ]));

        $criterion = EvaluationCriterion::query()->where('criteria_name', 'Tiêu chí 3 mức')->first();
        $this->assertNotNull($criterion);
        $response->assertRedirect(route('workspace.evaluation.index'));
        $this->assertCount(3, $criterion->score_levels);
        $this->assertSame(2, $criterion->score_levels[2]['weight']);
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
            ->from(route('workspace.evaluation.index'))
            ->post('/workspace-config/evaluation', $this->validPayload([
                'scope' => 'department',
                'department_code' => 'HCNS',
                'criteria_code' => 'TCVA076',
                'allow_half_score' => true,
            ]));

        $criterion = EvaluationCriterion::query()->where('criteria_code', 'TCVA076')->first();
        $this->assertNotNull($criterion);
        $response->assertRedirect(route('workspace.evaluation.index'));
        $this->assertSame(EvaluationCriterionScope::Department, $criterion->scope);
        $this->assertSame('HCNS', $criterion->department_code);
        $this->assertSame('Hành Chính Nhân Sự', $criterion->department_name);
        $this->assertTrue($criterion->allow_half_score);
        $this->assertStringContainsString('[Hành Chính Nhân Sự]', $criterion->displayName());
    }

    public function test_half_point_weight_rejected_when_allow_half_score_disabled(): void
    {
        $this->actingAs($this->superAdmin(), 'system')
            ->post('/workspace-config/evaluation', $this->validPayload([
                'allow_half_score' => false,
                'score_levels' => [
                    ['label' => 'Không đạt', 'weight' => -2],
                    ['label' => 'Đạt', 'weight' => 0.5],
                ],
            ]))
            ->assertSessionHasErrors('score_levels.1.weight');
    }

    public function test_half_point_weight_accepted_when_allow_half_score_enabled(): void
    {
        $response = $this->actingAs($this->superAdmin(), 'system')
            ->from(route('workspace.evaluation.index'))
            ->post('/workspace-config/evaluation', $this->validPayload([
                'criteria_name' => 'Tiêu chí chấm 0.5',
                'allow_half_score' => true,
                'score_levels' => [
                    ['label' => 'Không đạt', 'weight' => -1.5],
                    ['label' => 'Đạt', 'weight' => 0.5],
                    ['label' => 'Vượt', 'weight' => 2],
                ],
            ]));

        $criterion = EvaluationCriterion::query()->where('criteria_name', 'Tiêu chí chấm 0.5')->first();
        $this->assertNotNull($criterion);
        $response->assertRedirect(route('workspace.evaluation.index'));
        $this->assertTrue($criterion->allow_half_score);
        $this->assertSame(-1.5, $criterion->score_levels[0]['weight']);
        $this->assertSame(0.5, $criterion->score_levels[1]['weight']);
        $this->assertSame(2, $criterion->score_levels[2]['weight']);
    }

    public function test_score_level_code_and_description_are_saved_and_default_code_is_generated(): void
    {
        $response = $this->actingAs($this->superAdmin(), 'system')
            ->from(route('workspace.evaluation.index'))
            ->post('/workspace-config/evaluation', $this->validPayload([
                'criteria_name' => 'Tiêu chí có mã mức',
                'score_levels' => [
                    ['code' => 'KD', 'label' => 'Không đạt', 'description' => 'Chưa đạt yêu cầu tối thiểu', 'weight' => -2],
                    ['label' => 'Đạt', 'weight' => 0],
                ],
            ]));

        $criterion = EvaluationCriterion::query()->where('criteria_name', 'Tiêu chí có mã mức')->first();
        $this->assertNotNull($criterion);
        $response->assertRedirect(route('workspace.evaluation.index'));
        $this->assertSame('KD', $criterion->score_levels[0]['code']);
        $this->assertSame('Chưa đạt yêu cầu tối thiểu', $criterion->score_levels[0]['description']);
        $this->assertSame('M2', $criterion->score_levels[1]['code']);
        $this->assertSame('', $criterion->score_levels[1]['description']);
    }

    public function test_empty_department_becomes_general_for_super_admin(): void
    {
        $response = $this->actingAs($this->superAdmin(), 'system')
            ->from(route('workspace.evaluation.index'))
            ->post('/workspace-config/evaluation', $this->validPayload([
                'scope' => 'department',
                'department_code' => '',
                'criteria_name' => 'Tiêu chí không PB',
            ]));

        $criterion = EvaluationCriterion::query()->where('criteria_name', 'Tiêu chí không PB')->first();
        $this->assertNotNull($criterion);
        $response->assertRedirect(route('workspace.evaluation.index'));
        $this->assertSame(EvaluationCriterionScope::General, $criterion->scope);
        $this->assertNull($criterion->department_code);
    }

    public function test_unique_criteria_code(): void
    {
        EvaluationCriterion::query()->create([
            'scope' => EvaluationCriterionScope::General,
            'criteria_code' => 'TCVA010',
            'criteria_name' => 'Đã có',
            'category' => 'Khác',
            'score_levels' => $this->defaultLevels(),
            'is_active' => true,
        ]);

        $this->actingAs($this->superAdmin(), 'system')
            ->post('/workspace-config/evaluation', $this->validPayload([
                'criteria_code' => 'TCVA010',
            ]))
            ->assertSessionHasErrors('criteria_code');
    }

    public function test_score_levels_require_minimum_count(): void
    {
        $this->actingAs($this->superAdmin(), 'system')
            ->post('/workspace-config/evaluation', $this->validPayload([
                'score_levels' => [
                    ['label' => 'Chỉ một mức', 'weight' => 1],
                ],
            ]))
            ->assertSessionHasErrors('score_levels');
    }

    public function test_resource_exposes_score_levels(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user, 'system')
            ->post('/workspace-config/evaluation', $this->validPayload([
                'criteria_code' => 'TCVA098',
            ]));

        $criterion = EvaluationCriterion::query()->where('criteria_code', 'TCVA098')->firstOrFail();

        $this->actingAs($user, 'system')
            ->get(route('workspace.evaluation.show', $criterion))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/Evaluation/Show')
                ->where('criterion.criteria_code', 'TCVA098')
                ->where('criterion.score_levels_count', 5)
            );
    }

    public function test_show_includes_activity_after_create(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user, 'system')
            ->post('/workspace-config/evaluation', $this->validPayload([
                'criteria_code' => 'TCVA099',
            ]));

        $criterion = EvaluationCriterion::query()->where('criteria_code', 'TCVA099')->firstOrFail();

        $this->actingAs($user, 'system')
            ->get(route('workspace.evaluation.show', $criterion))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/Evaluation/Show')
                ->where('criterion.criteria_code', 'TCVA099')
                ->has('activity', 1)
                ->has('activity.0.actor_avatar')
                ->has('activity.0.score_summary')
                ->has('criterion.score_levels', 5)
            );
    }

    public function test_update_logs_field_changes_in_activity(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user, 'system')
            ->post('/workspace-config/evaluation', $this->validPayload([
                'criteria_code' => 'TCVA100',
                'criteria_name' => 'Tên cũ',
            ]));

        $criterion = EvaluationCriterion::query()->where('criteria_code', 'TCVA100')->firstOrFail();

        $this->actingAs($user, 'system')
            ->put(route('workspace.evaluation.update', $criterion), $this->validPayload([
                'criteria_code' => 'TCVA100',
                'criteria_name' => 'Tên mới',
            ]))
            ->assertRedirect();

        $this->actingAs($user, 'system')
            ->get(route('workspace.evaluation.show', $criterion))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/Evaluation/Show')
                ->has('activity', 2)
                ->where('activity.0.action', 'evaluation.criteria_updated')
                ->where('activity.0.changes.0.label', 'Tên tiêu chí')
                ->where('activity.0.changes.0.from', 'Tên cũ')
                ->where('activity.0.changes.0.to', 'Tên mới')
            );
    }

    public function test_super_admin_can_soft_delete_criterion(): void
    {
        $criterion = EvaluationCriterion::query()->create([
            'scope' => EvaluationCriterionScope::General,
            'criteria_code' => 'TCVA005',
            'criteria_name' => 'Xóa thử',
            'category' => 'Khác',
            'score_levels' => $this->defaultLevels(),
            'is_active' => true,
        ]);

        $this->actingAs($this->superAdmin(), 'system')
            ->delete("/workspace-config/evaluation/{$criterion->id}")
            ->assertRedirect(route('workspace.evaluation.index'));

        $this->assertSoftDeleted('evaluation_criteria', ['id' => $criterion->id]);
    }

    public function test_suggest_next_code_increments_tcva_sequence(): void
    {
        EvaluationCriterion::query()->create([
            'scope' => EvaluationCriterionScope::General,
            'criteria_code' => 'TCVA003',
            'criteria_name' => 'A',
            'category' => 'Khác',
            'score_levels' => $this->defaultLevels(),
            'is_active' => true,
        ]);

        $this->assertSame('TCVA004', EvaluationCriterion::suggestNextCode());
    }

    /** @return array<string, mixed> */
    private function importRow(array $overrides = []): array
    {
        return array_merge([
            'scope' => 'general',
            'criteria_name' => 'Tiêu chí nhập '.uniqid(),
            'category' => 'Thái độ',
            'description' => null,
            'allow_half_score' => false,
            'score_levels' => $this->defaultLevels(),
            'is_active' => true,
        ], $overrides);
    }

    public function test_super_admin_can_import_multiple_criteria(): void
    {
        $rows = [
            $this->importRow(['criteria_name' => 'Nhập dòng 1']),
            $this->importRow(['criteria_name' => 'Nhập dòng 2']),
        ];

        $response = $this->actingAs($this->superAdmin(), 'system')
            ->from(route('workspace.evaluation.index'))
            ->post(route('workspace.evaluation.import'), ['rows' => $rows]);

        $response->assertRedirect(route('workspace.evaluation.index'));
        $response->assertSessionHas('success');
        $this->assertSame(2, EvaluationCriterion::query()->whereIn('criteria_name', ['Nhập dòng 1', 'Nhập dòng 2'])->count());
    }

    public function test_import_auto_generates_sequential_codes_for_blank_rows(): void
    {
        $rows = [
            $this->importRow(['criteria_name' => 'Tự sinh mã 1']),
            $this->importRow(['criteria_name' => 'Tự sinh mã 2']),
            $this->importRow(['criteria_name' => 'Tự sinh mã 3']),
        ];

        $this->actingAs($this->superAdmin(), 'system')
            ->from(route('workspace.evaluation.index'))
            ->post(route('workspace.evaluation.import'), ['rows' => $rows])
            ->assertRedirect(route('workspace.evaluation.index'));

        $codes = EvaluationCriterion::query()
            ->whereIn('criteria_name', ['Tự sinh mã 1', 'Tự sinh mã 2', 'Tự sinh mã 3'])
            ->orderBy('id')
            ->pluck('criteria_code')
            ->all();

        $this->assertSame(['TCVA001', 'TCVA002', 'TCVA003'], $codes);
    }

    public function test_import_rejects_more_than_200_rows(): void
    {
        $rows = array_map(fn ($i) => $this->importRow(['criteria_name' => "Dòng {$i}"]), range(1, 201));

        $this->actingAs($this->superAdmin(), 'system')
            ->post(route('workspace.evaluation.import'), ['rows' => $rows])
            ->assertSessionHasErrors('rows');

        $this->assertSame(0, EvaluationCriterion::query()->count());
    }

    public function test_import_rejects_invalid_row_and_keeps_transaction_atomic(): void
    {
        $rows = [
            $this->importRow(['criteria_name' => 'Dòng hợp lệ']),
            $this->importRow(['criteria_name' => '']),
        ];

        $this->actingAs($this->superAdmin(), 'system')
            ->post(route('workspace.evaluation.import'), ['rows' => $rows])
            ->assertSessionHasErrors('rows.1.criteria_name');

        $this->assertSame(0, EvaluationCriterion::query()->count());
    }

    public function test_import_rejects_half_point_weight_when_allow_half_score_false(): void
    {
        $rows = [
            $this->importRow([
                'allow_half_score' => false,
                'score_levels' => [
                    ['label' => 'Không đạt', 'weight' => -2],
                    ['label' => 'Đạt', 'weight' => 0.5],
                ],
            ]),
        ];

        $this->actingAs($this->superAdmin(), 'system')
            ->post(route('workspace.evaluation.import'), ['rows' => $rows])
            ->assertSessionHasErrors('rows.0.score_levels.1.weight');

        $this->assertSame(0, EvaluationCriterion::query()->count());
    }

    public function test_import_accepts_half_point_weight_when_allow_half_score_true(): void
    {
        $rows = [
            $this->importRow([
                'criteria_name' => 'Chấm 0.5 hợp lệ',
                'allow_half_score' => true,
                'score_levels' => [
                    ['label' => 'Không đạt', 'weight' => -1.5],
                    ['label' => 'Đạt', 'weight' => 0.5],
                ],
            ]),
        ];

        $this->actingAs($this->superAdmin(), 'system')
            ->from(route('workspace.evaluation.index'))
            ->post(route('workspace.evaluation.import'), ['rows' => $rows])
            ->assertRedirect(route('workspace.evaluation.index'));

        $criterion = EvaluationCriterion::query()->where('criteria_name', 'Chấm 0.5 hợp lệ')->firstOrFail();
        $this->assertSame(0.5, $criterion->score_levels[1]['weight']);
    }

    public function test_import_score_levels_require_min_two_max_ten(): void
    {
        $rows = [
            $this->importRow(['score_levels' => [['label' => 'Chỉ một mức', 'weight' => 1]]]),
            $this->importRow(['score_levels' => array_map(
                fn ($i) => ['label' => "Mức {$i}", 'weight' => $i],
                range(1, 11)
            )]),
        ];

        $this->actingAs($this->superAdmin(), 'system')
            ->post(route('workspace.evaluation.import'), ['rows' => $rows])
            ->assertSessionHasErrors(['rows.0.score_levels', 'rows.1.score_levels']);
    }

    public function test_import_logs_activity_per_created_criterion(): void
    {
        $rows = [
            $this->importRow(['criteria_name' => 'Audit dòng 1']),
            $this->importRow(['criteria_name' => 'Audit dòng 2']),
        ];

        $this->actingAs($this->superAdmin(), 'system')
            ->from(route('workspace.evaluation.index'))
            ->post(route('workspace.evaluation.import'), ['rows' => $rows])
            ->assertRedirect(route('workspace.evaluation.index'));

        $criteria = EvaluationCriterion::query()->whereIn('criteria_name', ['Audit dòng 1', 'Audit dòng 2'])->get();
        $this->assertCount(2, $criteria);

        foreach ($criteria as $criterion) {
            $this->assertDatabaseHas('security_audit_logs', [
                'action' => 'evaluation.criteria_created',
                'subject_type' => 'evaluation_criterion',
                'subject_id' => $criterion->id,
            ]);
        }
    }

    public function test_admin_without_manage_permission_cannot_import(): void
    {
        $this->actingAs($this->admin(), 'system')
            ->post(route('workspace.evaluation.import'), ['rows' => [$this->importRow()]])
            ->assertForbidden();

        $this->assertSame(0, EvaluationCriterion::query()->count());
    }

    public function test_import_rejects_duplicate_criteria_code_within_same_batch(): void
    {
        $rows = [
            $this->importRow(['criteria_name' => 'Trùng mã 1', 'criteria_code' => 'TCVA050']),
            $this->importRow(['criteria_name' => 'Trùng mã 2', 'criteria_code' => 'TCVA050']),
        ];

        $this->actingAs($this->superAdmin(), 'system')
            ->post(route('workspace.evaluation.import'), ['rows' => $rows])
            ->assertSessionHasErrors('rows.1.criteria_code');

        $this->assertSame(0, EvaluationCriterion::query()->count());
    }
}
