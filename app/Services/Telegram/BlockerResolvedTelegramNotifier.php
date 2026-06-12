<?php

namespace App\Services\Telegram;

use App\Models\Blocker;
use App\Models\SystemAccount;
use App\Support\Enums\BlockerStatus;

class BlockerResolvedTelegramNotifier
{
    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly BlockerResolvedTelegramFormatter $formatter,
    ) {}

    public function notifyStatusChanged(
        Blocker $blocker,
        SystemAccount $actor,
        string $oldStatus,
        string $newStatus,
        ?string $recheckNote = null,
    ): void {
        if (! config('telegram.blocker_resolved')) {
            return;
        }

        $chatId = config('telegram.blocker_chat_id');
        if (! is_string($chatId) || $chatId === '') {
            return;
        }

        $old = BlockerStatus::tryFrom($oldStatus);
        $new = BlockerStatus::tryFrom($newStatus);
        if ($old === null || $new === null || $old === $new) {
            return;
        }

        $blocker->loadMissing(['project', 'owner', 'raisedBy']);

        $this->telegram->sendMessage(
            $this->formatter->format($blocker, $actor, $old, $new, $recheckNote),
            'HTML',
            $chatId,
        );
    }
}
