<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Stage 0 baseline: one account per role, each linked to an employee.
     * All dev passwords are "password". Stage 1 expands this with a full
     * team, projects, reports and blockers via model factories.
     */
    public function run(): void
    {
        $people = [
            ['code' => 'EMP-001', 'name' => 'Nguyễn Quản Trị', 'email' => 'admin@vaschools.edu.vn',  'title' => 'Tech Lead',  'username' => 'admin',  'role' => SystemRole::Admin],
            ['code' => 'EMP-002', 'name' => 'Trần Trưởng Nhóm', 'email' => 'lead@vaschools.edu.vn',   'title' => 'Team Lead',  'username' => 'lead',   'role' => SystemRole::Lead],
            ['code' => 'EMP-003', 'name' => 'Lê Lập Trình',     'email' => 'member@vaschools.edu.vn', 'title' => 'Developer',  'username' => 'member', 'role' => SystemRole::Member],
            ['code' => 'EMP-004', 'name' => 'Phạm Giám Đốc',    'email' => 'viewer@vaschools.edu.vn', 'title' => 'Board',      'username' => 'viewer', 'role' => SystemRole::Viewer],
        ];

        foreach ($people as $p) {
            $employee = Employee::create([
                'code' => $p['code'],
                'full_name' => $p['name'],
                'email' => $p['email'],
                'role_title' => $p['title'],
                'join_date' => now()->subMonths(6),
                'skills' => ['php', 'laravel', 'vue'],
                'is_active' => true,
            ]);

            SystemAccount::create([
                'username' => $p['username'],
                'password' => 'password', // hashed via model cast
                'display_name' => $p['name'],
                'role' => $p['role'],
                'employee_id' => $employee->id,
                'is_active' => true,
            ]);
        }

        $this->call(ProjectSeeder::class);
        $this->call(ProjectManagementSeeder::class);
        $this->call(KnowledgeBaseSeeder::class);

        $this->call(BootstrapAdminSeeder::class);
        $this->call(CoachingGoogleGuestSeeder::class);
        $this->call(HiddenAdminLoginSeeder::class);
    }
}
