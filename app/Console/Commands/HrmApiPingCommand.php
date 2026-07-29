<?php

namespace App\Console\Commands;

use App\Services\Hrm\HrmApiClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Smoke-test Bearer M2M tới HRM Public API v1 (GET /me, optional email lookup).
 */
class HrmApiPingCommand extends Command
{
    protected $signature = 'hrm:api-ping {--email= : Tra GET /employees?email=}';

    protected $description = 'Kiểm tra HRM_API_TOKEN — GET /me và tuỳ chọn tìm nhân sự theo email';

    public function handle(HrmApiClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->error('Thiếu HRM_API_BASE_URL hoặc HRM_API_TOKEN trong .env.');

            return self::FAILURE;
        }

        $this->line('Base: '.config('hrm.api.base_url'));

        try {
            $me = $client->me();
        } catch (Throwable $e) {
            $this->error('GET /me thất bại: '.$e->getMessage());
            if (str_contains($e->getMessage(), 'SSL certificate') || str_contains($e->getMessage(), 'error 60')) {
                $this->warn('Gợi ý local (ServBay): thêm HRM_API_VERIFY_SSL=false vào .env rồi php artisan config:clear');
                $this->warn('Hoặc sửa chuỗi cert trên server HRM (thiếu intermediate / CA nội bộ).');
            }

            return self::FAILURE;
        }

        if ($me === null) {
            $this->warn('GET /me trả data=null.');
        } else {
            $this->info('GET /me OK');
            $this->line(json_encode($me, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '');
        }

        $email = trim((string) $this->option('email'));
        if ($email === '') {
            return self::SUCCESS;
        }

        try {
            $row = $client->findActiveByEmail($email);
        } catch (Throwable $e) {
            $this->error('GET /employees?email= thất bại: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($row === null) {
            $this->warn("Không có nhân sự active với email [{$email}].");

            return self::SUCCESS;
        }

        $this->info('Employee active:');
        $this->line(json_encode([
            'uuid' => $row['uuid'] ?? null,
            'code' => $row['code'] ?? null,
            'full_name' => $row['full_name'] ?? null,
            'company_email' => $row['company_email'] ?? null,
            'legacy_user_id' => $row['legacy_user_id'] ?? null,
            'status' => $row['status'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '');

        return self::SUCCESS;
    }
}
