<?php

namespace App\Support\Enums;

/**
 * Trạng thái báo cáo tuần — luồng phê duyệt:
 * draft → generated → edited → submitted → approved | rejected.
 */
enum WeeklyReportStatus: string
{
    case Draft = 'draft';
    case Generated = 'generated';
    case Edited = 'edited';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Generated => 'Đã tạo bằng AI',
            self::Edited => 'Đã chỉnh sửa',
            self::Submitted => 'Chờ duyệt',
            self::Approved => 'Đã duyệt',
            self::Rejected => 'Bị trả lại',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Generated => 'sky',
            self::Edited => 'violet',
            self::Submitted => 'amber',
            self::Approved => 'emerald',
            self::Rejected => 'rose',
        };
    }

    /** Báo cáo đã khoá (không cho sửa nội dung). */
    public function isLocked(): bool
    {
        return $this === self::Approved;
    }

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }

    public function isSubmitted(): bool
    {
        return $this === self::Submitted;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** @return array<int, array{value:string, label:string, color:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'color' => $c->color(),
        ], self::cases());
    }
}
