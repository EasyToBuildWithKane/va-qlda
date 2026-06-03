<?php

namespace App\Console\Commands;

use App\Services\Cms\CmsEmployeeSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCmsEmployees extends Command
{
    protected $signature = 'cms:sync-employees
                            {--dry-run : Chỉ thống kê, không ghi vào va_prd_employees}
                            {--no-provision : Không tạo system_accounts sau đồng bộ}';

    protected $description = 'Đồng bộ nhân sự từ CMS (users + user_info) sang va_prd_employees (cms_user_id)';

    public function handle(CmsEmployeeSyncService $sync): int
    {
        if (! $sync->isCmsConfigured()) {
            $this->error('Chưa cấu hình CMS_DB_* trong .env (database + username).');

            return self::FAILURE;
        }

        try {
            DB::connection('cms_mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Không kết nối được CMS MySQL: '.$e->getMessage());

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $provision = ! $this->option('no-provision');

        if ($dryRun) {
            $this->warn('Chế độ dry-run — không ghi dữ liệu QLDA.');
        }

        $this->info('Đang đồng bộ từ CMS…');

        $stats = $sync->syncAll($dryRun, $provision);

        $this->table(
            ['Tạo mới', 'Cập nhật', 'Bỏ qua (không đổi)', 'Tài khoản login', 'Lỗi'],
            [[
                $stats['created'],
                $stats['updated'],
                $stats['skipped'],
                $stats['accounts'],
                $stats['errors'],
            ]],
        );

        if ($stats['errors'] > 0) {
            return self::FAILURE;
        }

        $this->info('Hoàn tất.');

        return self::SUCCESS;
    }
}
