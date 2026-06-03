<?php

namespace App\Console\Commands;

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

        $count = $sync->provisionMissingLoginAccounts($dryRun);

        $this->info($dryRun
            ? "Sẽ tạo {$count} tài khoản đăng nhập."
            : "Đã tạo/cập nhật {$count} tài khoản đăng nhập (role mặc định: member).");

        return self::SUCCESS;
    }
}
