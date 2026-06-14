<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use Illuminate\Database\Seeder;

/**
 * Nhân sự + tài khoản cho đăng nhập Google ngoại lệ (GOOGLE_ALLOWED_EMAILS).
 */
class CoachingGoogleGuestSeeder extends Seeder
{
    public const GUEST_EMAIL = 'minhtu270404@gmail.com';

    public function run(): void
    {
        $email = strtolower(self::GUEST_EMAIL);

        $employee = Employee::query()->updateOrCreate(
            ['email' => $email],
            [
                'code' => 'EMP-COACH-GUEST',
                'full_name' => 'Minh Tú (Coaching)',
                'role_title' => 'Coaching Guest',
                'join_date' => now()->subMonth(),
                'skills' => [],
                'is_active' => true,
            ],
        );

        SystemAccount::query()->updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'username' => 'minhtu_coach_guest',
                'password' => 'password',
                'display_name' => $employee->full_name,
                'role' => SystemRole::Member,
                'is_active' => true,
            ],
        );
    }
}
