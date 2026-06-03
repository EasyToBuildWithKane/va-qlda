<?php

namespace App\Services\AiAccount;

use App\Mail\AiAccountExpiryReminderMail;
use App\Models\AiAccount;
use App\Models\SystemAccount;
use App\Services\NotificationService;
use App\Support\Enums\AiAccountStatus;
use App\Support\Enums\NotificationPriority;
use App\Support\Enums\NotificationType;
use App\Support\Enums\SystemRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AiAccountReminderService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AiAccountCostCalculator $costCalculator,
        private readonly AiAccountStatusSync $statusSync,
        private readonly ReminderRecipientEmailResolver $emailResolver,
    ) {}

    /**
     * @return int Số tài khoản đã gửi nhắc
     */
    public function sendDueReminders(): int
    {
        $all = AiAccount::query()->get();
        $this->statusSync->syncCollection($all);

        $minHours = max(1, (int) config('ai_accounts.reminder.min_hours_between', 5));
        $notBefore = now()->subHours($minHours);

        $statuses = [AiAccountStatus::ExpiringSoon->value];
        if (config('ai_accounts.reminder.include_expired', true)) {
            $statuses[] = AiAccountStatus::Expired->value;
        }

        $accounts = AiAccount::query()
            ->whereIn('status', $statuses)
            ->where(function ($q) use ($notBefore) {
                $q->whereNull('last_reminded_at')
                    ->orWhere('last_reminded_at', '<', $notBefore);
            })
            ->get();

        $recipients = SystemAccount::query()
            ->where('is_active', true)
            ->whereIn('role', [SystemRole::Admin->value, SystemRole::Lead->value])
            ->with('employee')
            ->get();

        $emails = $this->resolveRecipientEmails($recipients);

        if ($recipients->isEmpty() && $emails === []) {
            return 0;
        }

        $sent = 0;
        foreach ($accounts as $account) {
            $this->notifyRecipients($recipients, $account, $emails);
            $account->update(['last_reminded_at' => now()]);
            $sent++;
        }

        return $sent;
    }

    /**
     * @param  Collection<int, SystemAccount>  $recipients
     * @param  array<int, string>  $emails
     */
    private function notifyRecipients(Collection $recipients, AiAccount $account, array $emails): void
    {
        $daysLeft = $this->statusSync->daysUntilExpiry($account);
        $monthly = $this->costCalculator->monthlyForAccount($account);
        $costLine = $this->costCalculator->formatVnd($monthly).' / tháng';

        $body = implode("\n", [
            'Công cụ: '.$account->tool_name,
            'Nhóm: '.$account->group_function->value,
            'License: '.$account->license_type,
            'Hết hạn: '.$account->expiry_date->format('d/m/Y').' (còn '.$daysLeft.' ngày)',
            'Chi phí: '.$costLine,
            'Email TK: '.$account->email_registered,
        ]);

        $title = $account->status === AiAccountStatus::Expired
            ? '🔴 Tài khoản AI đã hết hạn'
            : '⚠️ Tài khoản AI sắp hết hạn';

        $this->notifications->notify(
            $recipients,
            NotificationType::SystemAiAccountExpiry,
            $title,
            $body,
            [
                'priority' => NotificationPriority::High->value,
                'action_url' => '/ai-accounts',
                'entity_type' => 'ai_account',
                'entity_id' => $account->id,
            ],
        );

        $this->sendEmail($account, $daysLeft, $costLine, $emails);
    }

    /**
     * @param  array<int, string>  $emails
     */
    private function sendEmail(AiAccount $account, int $daysLeft, string $costLine, array $emails): void
    {
        if (! config('ai_accounts.reminder.send_email', true)) {
            return;
        }

        if ($emails === []) {
            Log::info('ai_account_reminder_no_recipient_emails', [
                'account_id' => $account->id,
                'hint' => 'Cần admin/lead có employee.email (CMS sync) hoặc AI_ACCOUNT_REMINDER_EXTRA_EMAILS',
            ]);

            return;
        }

        try {
            Mail::to($emails)->send(new AiAccountExpiryReminderMail($account, $daysLeft, $costLine));
        } catch (\Throwable $e) {
            Log::warning('ai_account_reminder_email_failed', [
                'account_id' => $account->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  Collection<int, SystemAccount>  $recipients
     * @return array<int, string>
     */
    private function resolveRecipientEmails(Collection $recipients): array
    {
        $emails = $this->extraEmails();

        foreach ($recipients as $account) {
            $resolved = $this->emailResolver->resolve($account);
            if ($resolved !== null) {
                $emails[] = $resolved;
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * @return array<int, string>
     */
    private function extraEmails(): array
    {
        $raw = config('ai_accounts.reminder.extra_recipients', []);

        return array_values(array_filter(
            is_array($raw) ? $raw : [],
            fn ($e) => is_string($e) && filter_var($e, FILTER_VALIDATE_EMAIL),
        ));
    }
}
