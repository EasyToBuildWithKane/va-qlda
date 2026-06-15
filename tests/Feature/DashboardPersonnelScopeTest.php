<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPersonnelScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_compliance_lists_only_tech_department_members(): void
    {
        $techDept = Department::create([
            'code' => 'PB-CN',
            'name' => 'Phòng Công nghệ thông tin',
            'color' => 'sky',
            'is_active' => true,
        ]);
        $otherDept = Department::create([
            'code' => 'PB-HR',
            'name' => 'Phòng Nhân sự',
            'color' => 'slate',
            'is_active' => true,
        ]);

        $techEmployee = Employee::factory()->create(['full_name' => 'Tech One', 'is_active' => true]);
        $otherEmployee = Employee::factory()->create(['full_name' => 'HR One', 'is_active' => true]);

        $techDept->members()->attach($techEmployee->id, ['is_active' => true]);
        $otherDept->members()->attach($otherEmployee->id, ['is_active' => true]);

        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();

        $this->actingAs($admin, 'system')
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dailyReportCompliance.summary.totalPeople', 1)
                ->where('dailyReportCompliance.people.0.name', 'Tech One')
                ->where('dailyReportCompliance.scope.departmentName', 'Phòng Công nghệ thông tin')
            );
    }

    public function test_dashboard_falls_back_to_project_members_when_dept_has_no_pivot(): void
    {
        $techDept = Department::create([
            'code' => 'PB-CN2',
            'name' => 'Phòng Công nghệ',
            'color' => 'brand',
            'is_active' => true,
        ]);

        $onProject = Employee::factory()->create(['full_name' => 'Dev On Project', 'is_active' => true]);
        $outsider = Employee::factory()->create(['full_name' => 'Outsider', 'is_active' => true]);

        $project = Project::factory()->create(['department_id' => $techDept->id]);
        $project->members()->attach($onProject->id, [
            'role' => 'developer',
            'rate_type' => 'hourly',
            'is_active' => true,
        ]);

        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();

        $this->actingAs($admin, 'system')
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dailyReportCompliance.summary.totalPeople', 1)
                ->where('dailyReportCompliance.people.0.name', 'Dev On Project')
            );
    }
}
