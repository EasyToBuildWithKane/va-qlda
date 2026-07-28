<?php

namespace App\Console\Commands;

use App\Services\Auth\BootstrapAdminRoleService;
use Illuminate\Console\Command;

class BootstrapAdminRoles extends Command
{
    protected $signature = 'va:bootstrap-admins
                            {--no-create : Không tạo system_accounts nếu thiếu}';

    protected $description = 'Gán role theo config/va_permissions.php (bootstrap_accounts)';

    public function handle(BootstrapAdminRoleService $service): int
    {
        $create = ! $this->option('no-create');

        $stats = $service->applyBootstrapRoles($create);

        $this->table(
            ['Cập nhật role', 'Tạo account', 'Thiếu employee', 'Không đổi'],
            [[
                $stats['updated'],
                $stats['created'],
                $stats['missing_employee'],
                $stats['skipped'],
            ]],
        );

        if ($stats['missing_employee'] > 0) {
            foreach ($stats['missing_emails'] as $email) {
                $this->warn("  Thiếu: {$email}");
            }
            foreach ($stats['hints'] as $hint) {
                $this->line("  {$hint}");
            }
            $this->warn('Nhân sự tự tạo khi đăng nhập Google lần đầu (HRM SSOT) — kiểm tra email tồn tại trong va_hrm.');

            return self::FAILURE;
        }

        $this->info('Hoàn tất.');

        return self::SUCCESS;
    }
}
