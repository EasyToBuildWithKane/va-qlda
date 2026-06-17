<?php

namespace App\Support\ContractLifecycle;

use App\Models\ContractCategory;
use Illuminate\Support\Str;

/**
 * Danh mục «Nhóm dịch vụ» (Nhóm DV) — phân loại hợp đồng, không gắn riêng từng NCC.
 * Khớp cột Nhóm DV trong file Excel nhập liệu.
 */
final class ContractServiceGroups
{
    /** @return list<string> */
    public static function labels(): array
    {
        return [
            'Giáo vụ số',
            'License',
            'Phần mềm SaaS',
            'Website',
            'SaaS',
            'Cloud',
            'Phần cứng',
            'Cổng thông tin',
        ];
    }

    /** Đảm bảo bản ghi contract_categories (vendor_id = null) cho từng nhãn chuẩn. */
    public static function sync(): void
    {
        foreach (self::labels() as $i => $name) {
            ContractCategory::firstOrCreate(
                ['name' => $name, 'vendor_id' => null],
                [
                    'sort_order' => $i,
                    'slug' => Str::slug($name) ?: 'nhom-dv-'.$i,
                ],
            );
        }
    }
}
