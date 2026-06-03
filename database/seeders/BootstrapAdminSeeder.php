<?php

namespace Database\Seeders;

use App\Services\Auth\BootstrapAdminRoleService;
use Illuminate\Database\Seeder;

/**
 * Gán role admin (và role khác) theo config/va_permissions.php → bootstrap_accounts.
 *
 * Chạy sau khi đã cms:sync-employees / cms:provision-accounts:
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
            $this->command?->warn('Email config chưa khớp nhân sự QLDA:');
            foreach ($stats['missing_emails'] as $missing) {
                $this->command?->line("  - {$missing}");
            }
            foreach ($stats['hints'] as $hint) {
                $this->command?->line("  {$hint}");
            }
            $this->command?->warn('Chạy: php artisan cms:post-sync (nếu sync CMS đã 100% nhưng bị Ctrl+C).');
        }
    }
}
