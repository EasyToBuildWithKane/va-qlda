<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    private static int $deptSeq = 0;

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

    private function deptPayload(array $overrides = []): array
    {
        $n = ++self::$deptSeq;

        return array_merge([
            'code' => "DEPT-{$n}",
            'name' => "Department {$n}",
            'is_active' => true,
        ], $overrides);
    }

    private function createDept(array $overrides = []): Department
    {
        $n = ++self::$deptSeq;

        return Department::create(array_merge([
            'code' => "DEPT-{$n}",
            'name' => "Department {$n}",
            'is_active' => true,
            'sort_order' => $n,
        ], $overrides));
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_all_roles_can_view_departments(): void
    {
        foreach ([SystemRole::Admin, SystemRole::Lead, SystemRole::Member, SystemRole::Viewer] as $role) {
            $account = SystemAccount::factory()->role($role)->create();

            $this->actingAs($account, 'system')
                ->get('/departments')
                ->assertOk();
        }
    }

    public function test_guest_is_redirected_from_departments(): void
    {
        $this->get('/departments')->assertRedirect('/login');
    }

    public function test_departments_index_paginates_and_filters(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->createDept([
                'code' => sprintf('PAG-%02d', $i),
                'name' => sprintf('Dept %02d', $i),
                'is_active' => true,
            ]);
        }

        $this->actingAs($this->admin(), 'system')
            ->get('/departments?per_page=5&page=2')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('departments.meta.total', 6)
                ->where('departments.meta.per_page', 5)
                ->where('departments.meta.current_page', 2)
                ->has('departments.data', 1)
            );

        $this->createDept(['code' => 'FLT-99', 'name' => 'Inactive Only', 'is_active' => false]);

        $this->actingAs($this->admin(), 'system')
            ->get('/departments?status=inactive&per_page=20')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('departments.data', 1)
                ->where('departments.data.0.code', 'FLT-99')
            );
    }

    public function test_admin_can_create_department_with_members(): void
    {
        $admin = $this->admin();
        $empA = $admin->employee;
        $empB = SystemAccount::factory()->role(SystemRole::Member)->create()->employee;

        $this->actingAs($admin, 'system')
            ->post('/departments', $this->deptPayload([
                'code' => 'DEPT-M',
                'name' => 'With Members',
                'manager_id' => $empA->id,
                'member_ids' => [$empB->id],
            ]))
            ->assertRedirect();

        $dept = Department::where('code', 'DEPT-M')->first();
        $this->assertNotNull($dept);
        $this->assertDatabaseHas('department_member', [
            'department_id' => $dept->id,
            'employee_id' => $empA->id,
        ]);
        $this->assertDatabaseHas('department_member', [
            'department_id' => $dept->id,
            'employee_id' => $empB->id,
        ]);
    }

    public function test_admin_can_update_department_members(): void
    {
        $admin = $this->admin();
        $dept = $this->createDept(['code' => 'MEM-01']);
        $emp = SystemAccount::factory()->role(SystemRole::Member)->create()->employee;

        $this->actingAs($admin, 'system')
            ->put("/departments/{$dept->id}", [
                'code' => 'MEM-01',
                'name' => $dept->name,
                'member_ids' => [$emp->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('department_member', [
            'department_id' => $dept->id,
            'employee_id' => $emp->id,
        ]);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_admin_can_create_department(): void
    {
        $this->actingAs($this->admin(), 'system')
            ->post('/departments', $this->deptPayload(['code' => 'DEPT-A', 'name' => 'HR']))
            ->assertRedirect();

        $this->assertDatabaseHas('departments', ['code' => 'DEPT-A', 'name' => 'HR']);
    }

    public function test_lead_can_create_department(): void
    {
        $this->actingAs($this->lead(), 'system')
            ->post('/departments', $this->deptPayload(['code' => 'DEPT-B', 'name' => 'Tech']))
            ->assertRedirect();

        $this->assertDatabaseHas('departments', ['code' => 'DEPT-B']);
    }

    public function test_member_cannot_create_department(): void
    {
        $this->actingAs($this->member(), 'system')
            ->post('/departments', $this->deptPayload())
            ->assertForbidden();
    }

    public function test_viewer_cannot_create_department(): void
    {
        $this->actingAs($this->viewer(), 'system')
            ->post('/departments', $this->deptPayload())
            ->assertForbidden();
    }

    public function test_department_code_must_be_unique(): void
    {
        $this->createDept(['code' => 'DUP-01']);

        $this->actingAs($this->admin(), 'system')
            ->post('/departments', ['code' => 'DUP-01', 'name' => 'Duplicate'])
            ->assertSessionHasErrors('code');
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_admin_can_update_department(): void
    {
        $dept = $this->createDept(['code' => 'UPD-01', 'name' => 'Old Name']);

        $this->actingAs($this->admin(), 'system')
            ->put("/departments/{$dept->id}", ['code' => 'UPD-01', 'name' => 'New Name'])
            ->assertRedirect();

        $this->assertDatabaseHas('departments', ['id' => $dept->id, 'name' => 'New Name']);
    }

    public function test_lead_can_update_department(): void
    {
        $dept = $this->createDept(['code' => 'UPD-02', 'name' => 'Old']);

        $this->actingAs($this->lead(), 'system')
            ->put("/departments/{$dept->id}", ['code' => 'UPD-02', 'name' => 'Lead Updated'])
            ->assertRedirect();

        $this->assertDatabaseHas('departments', ['id' => $dept->id, 'name' => 'Lead Updated']);
    }

    public function test_member_cannot_update_department(): void
    {
        $dept = $this->createDept(['code' => 'UPD-03']);

        $this->actingAs($this->member(), 'system')
            ->put("/departments/{$dept->id}", ['code' => 'UPD-03', 'name' => 'Hacked'])
            ->assertForbidden();
    }

    // ─── Toggle ───────────────────────────────────────────────────────────────

    public function test_admin_can_toggle_department_status(): void
    {
        $dept = $this->createDept(['is_active' => true]);

        $this->actingAs($this->admin(), 'system')
            ->patch("/departments/{$dept->id}/toggle")
            ->assertRedirect();

        $this->assertFalse($dept->fresh()->is_active);
    }

    public function test_lead_can_toggle_department_status(): void
    {
        $dept = $this->createDept(['is_active' => false]);

        $this->actingAs($this->lead(), 'system')
            ->patch("/departments/{$dept->id}/toggle")
            ->assertRedirect();

        $this->assertTrue($dept->fresh()->is_active);
    }

    public function test_member_cannot_toggle_department_status(): void
    {
        $dept = $this->createDept();

        $this->actingAs($this->member(), 'system')
            ->patch("/departments/{$dept->id}/toggle")
            ->assertForbidden();
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_department(): void
    {
        $dept = $this->createDept();

        $this->actingAs($this->admin(), 'system')
            ->delete("/departments/{$dept->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('departments', ['id' => $dept->id]);
    }

    public function test_lead_cannot_delete_department(): void
    {
        $dept = $this->createDept();

        $this->actingAs($this->lead(), 'system')
            ->delete("/departments/{$dept->id}")
            ->assertForbidden();
    }

    public function test_member_cannot_delete_department(): void
    {
        $dept = $this->createDept();

        $this->actingAs($this->member(), 'system')
            ->delete("/departments/{$dept->id}")
            ->assertForbidden();
    }
}
