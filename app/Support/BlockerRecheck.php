<?php

namespace App\Support;

use App\Models\Blocker;
use App\Models\SystemAccount;
use App\Support\Enums\BlockerRecheckResult;
use App\Support\Enums\BlockerStatus;

class BlockerRecheck
{
    public static function markPendingOnResolved(Blocker $blocker): void
    {
        $blocker->recheck_result = BlockerRecheckResult::Pending;
        $blocker->recheck_note = null;
        $blocker->rechecked_at = null;
        $blocker->rechecked_by_id = null;
    }

    /**
     * @return array{old_status: string, new_status: string}
     */
    public static function apply(
        Blocker $blocker,
        BlockerRecheckResult $result,
        SystemAccount $account,
        ?string $note = null,
    ): array {
        $oldStatus = $blocker->status->value;

        $blocker->recheck_result = $result;
        $blocker->recheck_note = $note;
        $blocker->rechecked_at = now();
        $blocker->rechecked_by_id = $account->employee_id;

        if ($result === BlockerRecheckResult::Passed) {
            $blocker->status = BlockerStatus::Closed;
            $blocker->resolved_at ??= now();
        } else {
            $blocker->status = BlockerStatus::InProgress;
            $blocker->resolved_at = null;
        }

        $blocker->save();

        return [
            'old_status' => $oldStatus,
            'new_status' => $blocker->status->value,
        ];
    }

    public static function needsRecheck(Blocker $blocker): bool
    {
        if ($blocker->status !== BlockerStatus::Resolved) {
            return false;
        }

        $result = $blocker->recheck_result;

        return $result === null || $result === BlockerRecheckResult::Pending;
    }
}
