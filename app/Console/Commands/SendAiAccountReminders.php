<?php

namespace App\Console\Commands;

use App\Services\AiAccount\AiAccountReminderService;
use Illuminate\Console\Command;

class SendAiAccountReminders extends Command
{
    protected $signature = 'ai-accounts:send-reminders';

    protected $description = 'Gửi nhắc nhở tài khoản AI sắp hết hạn (inbox)';

    public function handle(AiAccountReminderService $service): int
    {
        $count = $service->sendDueReminders();
        $this->info("Đã gửi {$count} nhắc nhở.");

        return self::SUCCESS;
    }
}
