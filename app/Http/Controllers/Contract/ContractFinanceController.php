<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreContractFinanceRequest;
use App\Models\Contract;
use App\Models\ContractFinance;
use App\Support\ContractActivityLogger;
use App\Support\ContractLifecycle\ContractFinanceSnapshot;
use Illuminate\Http\RedirectResponse;

class ContractFinanceController extends Controller
{
    public function store(StoreContractFinanceRequest $request, Contract $contract): RedirectResponse
    {
        $contract->finances()->create($request->validated());
        ContractFinanceSnapshot::sync($contract);

        ContractActivityLogger::log(
            $contract,
            'finance_added',
            'Thêm dữ liệu tài chính.',
            null,
            $request->user()?->employee_id,
        );

        return back()->with('success', 'Đã thêm dữ liệu tài chính.');
    }

    public function update(StoreContractFinanceRequest $request, Contract $contract, ContractFinance $finance): RedirectResponse
    {
        abort_unless($finance->contract_id === $contract->id, 404);

        $finance->update($request->validated());
        ContractFinanceSnapshot::sync($contract);

        ContractActivityLogger::log(
            $contract,
            'finance_updated',
            'Cập nhật dữ liệu tài chính.',
            null,
            $request->user()?->employee_id,
        );

        return back()->with('success', 'Đã cập nhật dữ liệu tài chính.');
    }

    public function destroy(Contract $contract, ContractFinance $finance): RedirectResponse
    {
        $this->authorize('update', $contract);
        abort_unless($finance->contract_id === $contract->id, 404);

        $finance->delete();
        ContractFinanceSnapshot::sync($contract);

        ContractActivityLogger::log(
            $contract,
            'finance_deleted',
            'Xoá dữ liệu tài chính.',
            null,
            request()->user()?->employee_id,
        );

        return back()->with('success', 'Đã xoá dữ liệu tài chính.');
    }
}
