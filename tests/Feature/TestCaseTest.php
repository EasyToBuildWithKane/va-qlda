<?php

namespace Tests\Feature;

use App\Models\Blocker;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Models\TestCase;
use App\Models\TestCaseRun;
use App\Models\TestSuite;
use App\Support\Enums\SystemRole;
use App\Support\Enums\TestCasePriority;
use App\Support\Enums\TestCaseRunResult;
use App\Support\Enums\TestCaseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase as BaseTestCase;

class TestCaseTest extends BaseTestCase
{
    use RefreshDatabase;

    private function admin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Admin)->create();
    }

    private function lead(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Lead)->create();
    }

    private function member(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Member)->create();
    }

    private function viewer(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Viewer)->create();
    }

    private function casePayload(int $projectId, array $overrides = []): array
    {
        return array_merge([
            'project_id' => $projectId,
            'title' => 'Test case thử nghiệm',
            'priority' => TestCasePriority::Medium->value,
        ], $overrides);
    }

    private function createTestCase(Project $project, array $overrides = []): TestCase
    {
        return TestCase::create(array_merge([
            'project_id' => $project->id,
            'title' => 'Test case hiện có',
            'priority' => TestCasePriority::High->value,
            'status' => TestCaseStatus::Ready->value,
        ], $overrides));
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_view_test_cases(): void
    {
        $this->actingAs($this->member(), 'system')
            ->get('/test-cases')
            ->assertOk();
    }

    public function test_viewer_can_view_test_cases(): void
    {
        $this->actingAs($this->viewer(), 'system')
            ->get('/test-cases')
            ->assertOk();
    }

    public function test_guest_is_redirected_from_test_cases(): void
    {
        $this->get('/test-cases')->assertRedirect('/login');
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_member_can_create_test_case(): void
    {
        $project = Project::factory()->create();

        $response = $this->actingAs($this->member(), 'system')
            ->post('/test-cases', $this->casePayload($project->id))
            ->assertRedirect();

        $response->assertSessionHas('created_test_case_id');

        $this->assertDatabaseHas('test_cases', [
            'project_id' => $project->id,
            'title' => 'Test case thử nghiệm',
        ]);
    }

    public function test_test_case_auto_generates_code(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->member(), 'system')
            ->post('/test-cases', $this->casePayload($project->id))
            ->assertRedirect();

        $tc = TestCase::where('project_id', $project->id)->first();
        $this->assertNotNull($tc->code);
        $this->assertStringStartsWith('TC-', $tc->code);
    }

    public function test_viewer_cannot_create_test_case(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->viewer(), 'system')
            ->post('/test-cases', $this->casePayload($project->id))
            ->assertForbidden();
    }

    public function test_test_case_requires_title_and_priority(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->member(), 'system')
            ->post('/test-cases', ['project_id' => $project->id])
            ->assertSessionHasErrors(['title', 'priority']);
    }

    public function test_admin_can_create_test_case_with_suite(): void
    {
        $project = Project::factory()->create();
        $suite = TestSuite::create(['project_id' => $project->id, 'name' => 'Bộ test UI', 'sort_order' => 1]);

        $this->actingAs($this->admin(), 'system')
            ->post('/test-cases', $this->casePayload($project->id, ['suite_id' => $suite->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('test_cases', [
            'project_id' => $project->id,
            'suite_id' => $suite->id,
        ]);
    }

    public function test_admin_can_create_test_case_with_new_suite_name(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->admin(), 'system')
            ->post('/test-cases', $this->casePayload($project->id, ['suite_name' => 'Đăng nhập']))
            ->assertRedirect();

        $this->assertDatabaseHas('test_suites', [
            'project_id' => $project->id,
            'name' => 'Đăng nhập',
        ]);

        $suiteId = TestSuite::query()->where('project_id', $project->id)->where('name', 'Đăng nhập')->value('id');
        $this->assertDatabaseHas('test_cases', [
            'project_id' => $project->id,
            'suite_id' => $suiteId,
        ]);
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_admin_can_update_test_case(): void
    {
        $project = Project::factory()->create();
        $tc = $this->createTestCase($project);

        $this->actingAs($this->admin(), 'system')
            ->put("/test-cases/{$tc->id}", [
                'title' => 'Tiêu đề đã cập nhật',
                'priority' => TestCasePriority::Critical->value,
                'status' => TestCaseStatus::Ready->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('test_cases', ['id' => $tc->id, 'title' => 'Tiêu đề đã cập nhật']);
    }

    public function test_viewer_cannot_update_test_case(): void
    {
        $project = Project::factory()->create();
        $tc = $this->createTestCase($project);

        $this->actingAs($this->viewer(), 'system')
            ->put("/test-cases/{$tc->id}", [
                'title' => 'Không được phép',
                'priority' => TestCasePriority::Low->value,
            ])
            ->assertForbidden();
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_test_case(): void
    {
        $project = Project::factory()->create();
        $tc = $this->createTestCase($project);

        $this->actingAs($this->admin(), 'system')
            ->delete("/test-cases/{$tc->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('test_cases', ['id' => $tc->id]);
    }

    public function test_lead_can_delete_test_case(): void
    {
        $project = Project::factory()->create();
        $tc = $this->createTestCase($project);

        $this->actingAs($this->lead(), 'system')
            ->delete("/test-cases/{$tc->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('test_cases', ['id' => $tc->id]);
    }

    public function test_member_cannot_delete_test_case(): void
    {
        $project = Project::factory()->create();
        $tc = $this->createTestCase($project);

        $this->actingAs($this->member(), 'system')
            ->delete("/test-cases/{$tc->id}")
            ->assertForbidden();
    }

    // ─── Execute: pass ────────────────────────────────────────────────────────

    public function test_member_can_execute_test_case_pass(): void
    {
        $project = Project::factory()->create();
        $tc = $this->createTestCase($project);

        $this->actingAs($this->member(), 'system')
            ->post("/test-cases/{$tc->id}/execute", [
                'result' => TestCaseRunResult::Pass->value,
                'actual_result' => 'Giao diện hiển thị đúng',
                'note' => 'Chạy tốt trên Chrome',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('test_case_runs', [
            'test_case_id' => $tc->id,
            'result' => TestCaseRunResult::Pass->value,
        ]);

        $fresh = $tc->fresh();
        $this->assertSame(TestCaseRunResult::Pass->value, $fresh->last_result);
        $this->assertNotNull($fresh->last_run_at);
    }

    // ─── Execute: fail + create_blocker ───────────────────────────────────────

    public function test_execute_fail_creates_blocker_when_requested(): void
    {
        $project = Project::factory()->create();
        $tc = $this->createTestCase($project);

        $this->actingAs($this->member(), 'system')
            ->post("/test-cases/{$tc->id}/execute", [
                'result' => TestCaseRunResult::Fail->value,
                'actual_result' => 'Button không phản hồi',
                'create_blocker' => true,
                'blocker_title' => 'Nút lưu không hoạt động',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('test_case_runs', [
            'test_case_id' => $tc->id,
            'result' => TestCaseRunResult::Fail->value,
        ]);

        $this->assertDatabaseHas('blockers', [
            'project_id' => $project->id,
            'title' => 'Nút lưu không hoạt động',
        ]);

        $fresh = $tc->fresh();
        $this->assertSame(TestCaseRunResult::Fail->value, $fresh->last_result);
        $this->assertNotNull($fresh->blocker_id);

        $run = TestCaseRun::where('test_case_id', $tc->id)->first();
        $this->assertNotNull($run->blocker_id);
    }

    public function test_execute_fail_without_create_blocker_flag_does_not_create_blocker(): void
    {
        $project = Project::factory()->create();
        $tc = $this->createTestCase($project);

        $this->actingAs($this->member(), 'system')
            ->post("/test-cases/{$tc->id}/execute", [
                'result' => TestCaseRunResult::Fail->value,
                'actual_result' => 'Lỗi hiển thị',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('blockers', ['project_id' => $project->id]);
    }

    public function test_viewer_cannot_execute_test_case(): void
    {
        $project = Project::factory()->create();
        $tc = $this->createTestCase($project);

        $this->actingAs($this->viewer(), 'system')
            ->post("/test-cases/{$tc->id}/execute", [
                'result' => TestCaseRunResult::Pass->value,
            ])
            ->assertForbidden();
    }

    // ─── Import ───────────────────────────────────────────────────────────────

    public function test_admin_can_import_test_cases(): void
    {
        $project = Project::factory()->create();

        $rows = [
            ['title' => 'TC nhập khẩu 1', 'priority' => TestCasePriority::Low->value],
            ['title' => 'TC nhập khẩu 2', 'priority' => TestCasePriority::High->value],
        ];

        $this->actingAs($this->admin(), 'system')
            ->post('/test-cases/import', [
                'project_id' => $project->id,
                'rows' => $rows,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('test_cases', ['title' => 'TC nhập khẩu 1', 'project_id' => $project->id]);
        $this->assertDatabaseHas('test_cases', ['title' => 'TC nhập khẩu 2', 'project_id' => $project->id]);
    }

    public function test_viewer_cannot_import_test_cases(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->viewer(), 'system')
            ->post('/test-cases/import', [
                'project_id' => $project->id,
                'rows' => [['title' => 'TC', 'priority' => TestCasePriority::Low->value]],
            ])
            ->assertForbidden();
    }

    public function test_import_enforces_max_200_rows(): void
    {
        $project = Project::factory()->create();
        $rows = array_fill(0, 201, ['title' => 'TC', 'priority' => TestCasePriority::Low->value]);

        $this->actingAs($this->admin(), 'system')
            ->post('/test-cases/import', [
                'project_id' => $project->id,
                'rows' => $rows,
            ])
            ->assertSessionHasErrors('rows');
    }

    // ─── Test Suites ─────────────────────────────────────────────────────────

    public function test_member_can_create_test_suite(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->member(), 'system')
            ->post('/test-cases/suites', [
                'project_id' => $project->id,
                'name' => 'Bộ test đăng nhập',
                'sort_order' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('created_suite_id');

        $this->assertDatabaseHas('test_suites', ['name' => 'Bộ test đăng nhập']);
    }

    public function test_admin_can_delete_test_suite(): void
    {
        $project = Project::factory()->create();
        $suite = TestSuite::create(['project_id' => $project->id, 'name' => 'Suite xoá']);

        $this->actingAs($this->admin(), 'system')
            ->delete("/test-cases/suites/{$suite->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('test_suites', ['id' => $suite->id]);
    }
}
