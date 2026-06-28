<?php

namespace App\Support\Enums;

/**
 * Các khối nội dung của báo cáo tuần (Executive Dashboard layout).
 */
enum WeeklyReportSection: string
{
    case Result = 'result';      // KẾT QUẢ THỰC HIỆN
    case Current = 'current';    // TÌNH HÌNH HIỆN TẠI
    case Next = 'next';          // KẾ HOẠCH TIẾP THEO
    case Risk = 'risk';          // Rủi ro & Vấn đề
    case Feedback = 'feedback';  // Tổng hợp phản hồi
    case Activity = 'activity';  // Dòng sự kiện nổi bật

    public function label(): string
    {
        return match ($this) {
            self::Result => 'Kết quả thực hiện',
            self::Current => 'Tình hình hiện tại',
            self::Next => 'Kế hoạch tiếp theo',
            self::Risk => 'Rủi ro & Vấn đề',
            self::Feedback => 'Tổng hợp phản hồi',
            self::Activity => 'Sự kiện nổi bật',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Result => 'check-circle',
            self::Current => 'overview',
            self::Next => 'flag',
            self::Risk => 'alert',
            self::Feedback => 'feedback',
            self::Activity => 'report-history',
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::Result => 1,
            self::Current => 2,
            self::Next => 3,
            self::Risk => 4,
            self::Feedback => 5,
            self::Activity => 6,
        };
    }

    /** Ba thẻ chính do người dùng chỉnh sửa trực tiếp. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Result, self::Current, self::Next], true);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** @return array<int, self> */
    public static function editable(): array
    {
        return [self::Result, self::Current, self::Next];
    }

    /** @return array<int, array{value:string, label:string, icon:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'icon' => $c->icon(),
        ], self::cases());
    }
}
