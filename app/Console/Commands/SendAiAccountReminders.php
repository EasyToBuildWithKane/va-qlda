<?php

namespace App\Console\Commands;

use App\Services\AiAccount\AiAccountReminderService;
use Illuminate\Console\Command;

class SendAiAccountReminders extends Command
{
    protected $signature = 'ai-accounts:send-reminders';

    protected $description = 'Gửi nhắc hết hạn + chưa thanh toán gia hạn tài khoản AI';

    public function handle(AiAccountReminderService $service): int
    {
        $expiry = $service->sendDueReminders();
        $payment = $service->sendUnpaidRenewalReminders();
        $count = $expiry + $payment;
        $this->info("Đã gửi {$count} nhắc (hết hạn: {$expiry}, chưa TT: {$payment}).");

        return self::SUCCESS;
    }
}
