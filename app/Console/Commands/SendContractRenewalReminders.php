<?php

namespace App\Console\Commands;

use App\Services\Contract\ContractReminderService;
use Illuminate\Console\Command;

class SendContractRenewalReminders extends Command
{
    protected $signature = 'contracts:send-reminders';

    protected $description = 'Đồng bộ trạng thái + gửi cảnh báo hợp đồng sắp/đã hết hạn';

    public function handle(ContractReminderService $service): int
    {
        $result = $service->run();

        $this->info("Đồng bộ {$result['synced']} trạng thái, gửi {$result['notified']} cảnh báo hợp đồng.");

        return self::SUCCESS;
    }
}
