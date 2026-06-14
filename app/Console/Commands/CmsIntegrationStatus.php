<?php

namespace App\Console\Commands;

use App\Services\Cms\CmsEmployeeSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CmsIntegrationStatus extends Command
{
    protected $signature = 'cms:status';

    protected $description = 'Kiểm tra kết nối CMS và trạng thái đồng bộ nhân sự / login QLDA';

    public function handle(CmsEmployeeSyncService $sync): int
    {
        $this->line('QLDA CMS integration');

        if (! $sync->isCmsConfigured()) {
            $this->error('CMS_DB_* chưa có trong config (kiểm tra .env và chạy php artisan config:clear nếu vừa sửa .env).');

            return self::FAILURE;
        }

        $db = config('database.connections.cms_mysql.database');
        $host = config('database.connections.cms_mysql.host');
        $this->line("CMS target: {$host} / {$db}");

        try {
            DB::connection('cms_mysql')->getPdo();
            $this->info('Kết nối CMS MySQL: OK');
        } catch (\Throwable $e) {
            $this->error('Kết nối CMS MySQL: FAIL — '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            $cmsUsers = $sync->countCmsUsers();
            $this->line("Số user CMS (kể cả đã xóa mềm): {$cmsUsers}");
        } catch (\Throwable $e) {
            $this->error('Đọc bảng CMS users: FAIL — '.$e->getMessage());

            return self::FAILURE;
        }

        $stats = $sync->qldaLinkStats();
        $stats['cms_users'] = $cmsUsers;

        $this->newLine();
        $this->table(
            ['Chỉ số', 'Giá trị'],
            [
                ['Nhân sự QLDA có cms_user_id', $stats['employees_linked']],
                ['Trong đó is_active', $stats['employees_active']],
                ['Đã có system_accounts (active)', $stats['with_login']],
                ['Thiếu system_accounts (active)', $stats['missing_login']],
            ],
        );

        if ($stats['employees_linked'] === 0) {
            $this->warn('Chưa sync nhân sự — chạy: php artisan cms:sync-employees (có thể mất vài phút).');
        } elseif ($stats['missing_login'] > 0) {
            $this->warn('Còn thiếu login — chạy: php artisan cms:provision-accounts');
        } else {
            $this->info('Nhân sự active đã có system_accounts. Thử đăng nhập Google (email khớp employees.email).');
        }

        return self::SUCCESS;
    }
}
