<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use Illuminate\Database\Seeder;

/**
 * Tài khoản đăng nhập ẩn /lh36 — usr_01 / password01 (admin).
 */
class HiddenAdminLoginSeeder extends Seeder
{
    public function run(): void
    {
        $employee = Employee::query()->updateOrCreate(
            ['email' => 'lh36-admin@vaschools.edu.vn'],
            [
                'code' => 'EMP-LH36',
                'full_name' => 'Quản trị LH36',
                'role_title' => 'System Admin',
                'join_date' => now()->subYear(),
                'skills' => ['admin'],
                'is_active' => true,
            ],
        );

        SystemAccount::query()->updateOrCreate(
            ['username' => 'usr_01'],
            [
                'password' => 'password01',
                'display_name' => 'Quản trị LH36',
                'role' => SystemRole::Admin,
                'employee_id' => $employee->id,
                'is_active' => true,
            ],
        );

        $this->command?->info('Hidden admin login: usr_01 / password01 (role admin).');
    }
}
