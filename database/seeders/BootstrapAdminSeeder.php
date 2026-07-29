<?php

namespace Database\Seeders;

use App\Services\Auth\BootstrapAdminRoleService;
use Illuminate\Database\Seeder;

/**
 * Gán role admin (và role khác) theo config/va_permissions.php → bootstrap_accounts.
 *
 * Nhân sự được tạo lazy khi đăng nhập Google lần đầu (HRM SSOT — va_hrm):
 *   php artisan db:seed --class=BootstrapAdminSeeder
 */
class BootstrapAdminSeeder extends Seeder
{
    public function run(): void
    {
        $stats = app(BootstrapAdminRoleService::class)->applyBootstrapRoles(createMissingAccounts: true);

        $this->command?->info('Bootstrap admin roles');
        $this->command?->table(
            ['Cập nhật role', 'Tạo account', 'Thiếu employee (email)', 'Không đổi'],
            [[
                $stats['updated'],
                $stats['created'],
                $stats['missing_employee'],
                $stats['skipped'],
            ]],
        );

        if ($stats['missing_emails'] !== []) {
            $this->command?->warn('Email config chưa khớp nhân sự Workspace:');
            foreach ($stats['missing_emails'] as $missing) {
                $this->command?->line("  - {$missing}");
            }
            foreach ($stats['hints'] as $hint) {
                $this->command?->line("  {$hint}");
            }
            $this->command?->warn('Nhân sự tự tạo khi đăng nhập Google lần đầu (HRM SSOT) — kiểm tra email trong va_hrm.');
        }
    }
}
