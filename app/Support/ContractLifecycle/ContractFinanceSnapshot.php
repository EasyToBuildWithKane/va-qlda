<?php

namespace App\Support\ContractLifecycle;

use App\Models\Contract;
use App\Models\ContractFinance;

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
        $lifecycle = (float) $contract->finances->sum(
            fn (ContractFinance $f) => (float) ($f->total ?? 0),
        );
        $latest = $contract->finances->first();
        $annual = $contract->annualCostResolved();

        $contract->forceFill([
            'monthly_cost' => $monthly > 0 ? round($monthly, 2) : null,
            'lifecycle_cost' => $lifecycle > 0 ? round($lifecycle, 2) : null,
            'unit_price' => $latest?->unit_price !== null ? (float) $latest->unit_price : null,
            'annual_cost' => $annual > 0 ? $annual : null,
        ])->save();
    }
}
