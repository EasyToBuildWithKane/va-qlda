<?php

namespace App\Console\Commands;

use App\Services\Auth\BootstrapAdminRoleService;
use App\Services\Cms\CmsEmployeeSyncService;
use Illuminate\Console\Command;

class ProvisionCmsLoginAccounts extends Command
{
    protected $signature = 'cms:provision-accounts
                            {--dry-run : Chỉ đếm, không tạo system_accounts}';

    protected $description = 'Tạo system_accounts cho nhân sự đã sync CMS (chưa có login QLDA)';

    public function handle(CmsEmployeeSyncService $sync): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Chế độ dry-run — không ghi dữ liệu.');
        }

        $stats = $sync->qldaLinkStats();
        $this->line("Nhân sự CMS (active): {$stats['employees_active']}, thiếu login: {$stats['missing_login']}");

        if ($stats['missing_login'] === 0) {
            $this->info('Không có nhân sự active nào thiếu system_accounts.');
            $this->line('Gợi ý: php artisan cms:status — hoặc sync lại nếu chưa có cms_user_id.');

            return self::SUCCESS;
        }

        $this->info('Đang tạo tài khoản đăng nhập…');

        $bar = $this->output->createProgressBar($stats['missing_login']);
        $bar->start();

        $count = $sync->provisionMissingLoginAccounts($dryRun, function (int $done, int $total) use ($bar) {
            $bar->setMaxSteps(max(1, $total));
            $bar->setProgress($done);
        });

        $bar->finish();
        $this->newLine(2);

        $this->info($dryRun
            ? "Sẽ tạo {$count} tài khoản đăng nhập."
            : "Đã tạo {$count} tài khoản (role mặc định: member). Gán admin trong DB nếu cần.");

        if (! $dryRun) {
            $roles = app(BootstrapAdminRoleService::class)->applyBootstrapRoles(true);
            if ($roles['updated'] + $roles['created'] > 0) {
                $this->info("Bootstrap admin: {$roles['updated']} cập nhật role, {$roles['created']} tạo mới.");
            }
        }

        return self::SUCCESS;
    }
}
