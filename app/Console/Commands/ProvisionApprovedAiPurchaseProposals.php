<?php

namespace App\Console\Commands;

use App\Models\AiPurchaseProposal;
use App\Services\AiAccount\AiAccountFromProposalCreator;
use App\Support\Enums\AiPurchaseProposalStatus;
use Illuminate\Console\Command;

class ProvisionApprovedAiPurchaseProposals extends Command
{
    protected $signature = 'ai-accounts:provision-approved-proposals {--dry-run : Chỉ liệt kê, không tạo tài khoản}';

    protected $description = 'Lập tài khoản AI từ phiếu đã duyệt chưa gắn ai_account_id (dùng email trên phiếu)';

    public function handle(AiAccountFromProposalCreator $creator): int
    {
        $query = AiPurchaseProposal::query()
            ->whereNull('ai_account_id')
            ->whereIn('status', [
                AiPurchaseProposalStatus::Approved,
                AiPurchaseProposalStatus::Purchased,
                AiPurchaseProposalStatus::Active,
            ]);

        $proposals = $query->get();

        if ($proposals->isEmpty()) {
            $this->info('Không có phiếu nào cần đồng bộ.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            foreach ($proposals as $proposal) {
                $this->line("- #{$proposal->id} {$proposal->proposal_code} — {$proposal->tool_name}");
            }
            $this->info("Tổng: {$proposals->count()} phiếu (dry-run).");

            return self::SUCCESS;
        }

        $created = 0;
        foreach ($proposals as $proposal) {
            $email = trim((string) ($proposal->registration_email ?: $proposal->recipient_email));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->warn("Bỏ qua #{$proposal->id}: thiếu email đăng ký hợp lệ.");

                continue;
            }

            $creator->create($proposal->fresh(), [
                'email_registered' => $email,
                'login_password' => null,
                'notify_before_days' => (int) config('ai_accounts.defaults.notify_before_days', 14),
                'notes' => null,
            ]);
            $created++;
            $this->line("✓ #{$proposal->id} → ai_account_id={$proposal->fresh()->ai_account_id}");
        }

        $this->info("Đã tạo {$created} tài khoản AI.");

        return self::SUCCESS;
    }
}
