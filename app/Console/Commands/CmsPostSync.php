<?php

namespace App\Console\Commands;

use App\Services\Auth\BootstrapAdminRoleService;
use App\Services\Cms\CmsEmployeeSyncService;
use Illuminate\Console\Command;

/**
 * Chạy sau cms:sync-employees khi progress 100% nhưng bị Ctrl+C trước bảng kết quả.
 */
class CmsPostSync extends Command
{
    protected $signature = 'cms:post-sync';

    protected $description = 'Provision system_accounts + bootstrap admin (không đọc lại CMS)';

    public function handle(CmsEmployeeSyncService $sync, BootstrapAdminRoleService $bootstrap): int
    {
        $this->info('Provision login thiếu…');

        $stats = $sync->qldaLinkStats();
        $this->line("Thiếu system_accounts (active): {$stats['missing_login']}");

        $bar = $this->output->createProgressBar(max(1, $stats['missing_login']));
        $bar->start();

        $created = $sync->provisionMissingLoginAccounts(false, function (int $done, int $total) use ($bar) {
            $bar->setMaxSteps(max(1, $total));
            $bar->setProgress($done);
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Đã provision {$created} tài khoản.");

        $this->info('Bootstrap admin roles…');
        $roles = $bootstrap->applyBootstrapRoles(true);

        $this->table(
            ['Cập nhật role', 'Tạo account', 'Thiếu email', 'Không đổi'],
            [[
                $roles['updated'],
                $roles['created'],
                $roles['missing_employee'],
                $roles['skipped'],
            ]],
        );

        foreach ($roles['missing_emails'] as $email) {
            $this->warn("  Thiếu: {$email}");
        }
        foreach ($roles['hints'] as $hint) {
            $this->line("  {$hint}");
        }

        return $roles['missing_employee'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
