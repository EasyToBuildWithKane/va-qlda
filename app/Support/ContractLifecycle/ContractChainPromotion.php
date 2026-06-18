<?php

namespace App\Support\ContractLifecycle;

use App\Models\Contract;
use App\Models\SystemAccount;
use App\Support\ContractActivityLogger;
use App\Support\Enums\ContractStatus;

/**
 * Khi phát sinh bản gia hạn / phụ lục: hợp đồng đang hiệu lực (hoặc còn theo dõi)
 * chuyển «Chuyển phụ lục»; bản mới là «Đang hiệu lực».
 */
class ContractChainPromotion
{
    /** @return array<int, ContractStatus> */
    public static function demotableStatuses(): array
    {
        return [
            ContractStatus::Active,
            ContractStatus::ExpiringSoon,
            ContractStatus::Expired,
            ContractStatus::PendingRenewal,
        ];
    }

    public static function chainRootId(Contract $contract): int
    {
        return $contract->root_contract_id ?? $contract->id;
    }

    public static function applySuccessor(Contract $predecessor, Contract $successor, ?SystemAccount $account): void
    {
        if (in_array($predecessor->status, self::demotableStatuses(), true)) {
            $predecessor->update(['status' => ContractStatus::Addendum->value]);
            ContractActivityLogger::updated(
                $predecessor,
                $account,
                ['status' => ContractStatus::Addendum->value],
            );
        }

        if ($successor->status === ContractStatus::Draft || $successor->status === ContractStatus::Terminated) {
            return;
        }

        if ($successor->status !== ContractStatus::Active) {
            $successor->update(['status' => ContractStatus::Active->value]);
            ContractActivityLogger::updated(
                $successor,
                $account,
                ['status' => ContractStatus::Active->value],
            );
        }
    }

    /** Đảm bảo một bản «Đang hiệu lực» trong bộ — các bản còn lại chuyển phụ lục. */
    public static function demoteActivePeersInChain(Contract $successor, ?SystemAccount $account): void
    {
        if ($successor->status !== ContractStatus::Active) {
            return;
        }

        $rootId = self::chainRootId($successor);

        Contract::query()
            ->where(function ($query) use ($rootId) {
                $query->where('id', $rootId)->orWhere('root_contract_id', $rootId);
            })
            ->where('id', '!=', $successor->id)
            ->get()
            ->each(function (Contract $peer) use ($account) {
                if (! in_array($peer->status, self::demotableStatuses(), true)) {
                    return;
                }
                $peer->update(['status' => ContractStatus::Addendum->value]);
                ContractActivityLogger::updated(
                    $peer,
                    $account,
                    ['status' => ContractStatus::Addendum->value],
                );
            });
    }
}
