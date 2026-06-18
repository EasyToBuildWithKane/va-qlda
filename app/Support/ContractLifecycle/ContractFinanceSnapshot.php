<?php

namespace App\Support\ContractLifecycle;

use App\Models\Contract;
use App\Models\ContractFinance;
use App\Support\MoneyAmount;

/**
 * Đồng bộ cột tổng hợp trên `contracts` từ `contract_finances` (master data).
 */
class ContractFinanceSnapshot
{
    public static function sync(Contract $contract): void
    {
        $contract->load('finances');

        if ($contract->finances->isEmpty()) {
            return;
        }

        $monthly = (float) $contract->finances->sum(
            fn (ContractFinance $f) => (float) ($f->maintenance_fee ?? 0),
        );
        $totalSum = (float) $contract->finances->sum(
            fn (ContractFinance $f) => (float) ($f->total ?? 0),
        );
        $latest = $contract->finances->first();
        $annual = $totalSum > 0
            ? $totalSum
            : ($monthly > 0 ? $monthly * 12 : 0);

        $contract->forceFill([
            'monthly_cost' => $monthly > 0 ? MoneyAmount::truncate($monthly) : null,
            'lifecycle_cost' => $totalSum > 0 ? MoneyAmount::truncate($totalSum) : null,
            'unit_price' => $latest?->unit_price !== null ? MoneyAmount::truncate((float) $latest->unit_price) : null,
            'annual_cost' => $annual > 0 ? MoneyAmount::truncate($annual) : null,
        ])->save();
    }
}
