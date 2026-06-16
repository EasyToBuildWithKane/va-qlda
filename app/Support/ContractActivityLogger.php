<?php

namespace App\Support;

use App\Models\Contract;
use App\Models\ContractActivity;
use App\Models\SystemAccount;

class ContractActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function log(Contract $contract, string $event, string $description, ?array $meta = null, ?int $employeeId = null): void
    {
        ContractActivity::create([
            'contract_id' => $contract->id,
            'employee_id' => $employeeId,
            'event' => $event,
            'description' => $description,
            'meta' => $meta,
        ]);
    }

    public static function created(Contract $contract, ?SystemAccount $account): void
    {
        self::log(
            $contract,
            'created',
            'Tạo hợp đồng mới',
            ['name' => $contract->name],
            $account?->employee_id,
        );
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public static function updated(Contract $contract, ?SystemAccount $account, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $labels = [
            'name' => 'tên hợp đồng',
            'description' => 'mô tả',
            'vendor_id' => 'nhà cung cấp',
            'category_id' => 'nhóm dịch vụ',
            'department_id' => 'phòng ban',
            'using_unit' => 'đơn vị sử dụng',
            'owner_id' => 'người phụ trách',
            'manager_id' => 'người quản lý',
            'currency' => 'đơn vị tiền',
            'unit_price' => 'đơn giá',
            'monthly_cost' => 'chi phí tháng',
            'annual_cost' => 'chi phí năm',
            'lifecycle_cost' => 'chi phí vòng đời',
            'payment_status' => 'tình trạng thanh toán',
            'signed_date' => 'ngày ký',
            'effective_date' => 'ngày hiệu lực',
            'expiry_date' => 'ngày hết hạn',
            'auto_renew' => 'tự động gia hạn',
            'status' => 'trạng thái',
        ];

        foreach ($changes as $field => $value) {
            if (! isset($labels[$field])) {
                continue;
            }
            self::log(
                $contract,
                'updated',
                'Cập nhật '.$labels[$field],
                ['field' => $field, 'value' => $value],
                $account?->employee_id,
            );
        }
    }

    public static function renewed(Contract $contract, ?string $previousExpiry, ?string $newExpiry, ?SystemAccount $account): void
    {
        self::log(
            $contract,
            'renewed',
            'Gia hạn hợp đồng'.($newExpiry ? ": đến {$newExpiry}" : ''),
            ['previous_expiry' => $previousExpiry, 'new_expiry' => $newExpiry],
            $account?->employee_id,
        );
    }

    public static function statusSynced(Contract $contract, string $from, string $to): void
    {
        self::log(
            $contract,
            'status_synced',
            "Tự động chuyển trạng thái: {$from} → {$to}",
            ['from' => $from, 'to' => $to],
            null,
        );
    }

    public static function attachmentAdded(Contract $contract, string $name, ?SystemAccount $account): void
    {
        self::log($contract, 'attachment', "Thêm hồ sơ: {$name}", ['file' => $name], $account?->employee_id);
    }

    public static function attachmentRemoved(Contract $contract, string $name, ?SystemAccount $account): void
    {
        self::log($contract, 'attachment_removed', "Xoá hồ sơ: {$name}", ['file' => $name], $account?->employee_id);
    }

    public static function deleted(Contract $contract, ?SystemAccount $account): void
    {
        self::log($contract, 'deleted', 'Xoá hợp đồng', ['name' => $contract->name], $account?->employee_id);
    }
}
