<?php

namespace App\Support\Enums;

enum EvaluationFormRaterRole: string
{
    case Self = 'self';
    case DeptHead = 'dept_head';
    case DirectManager = 'direct_manager';
    case Board = 'board';
    case Custom = 'custom';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Self => 'Nhân viên tự đánh giá',
            self::DeptHead => 'Trưởng phòng đánh giá',
            self::DirectManager => 'Quản lý trực tiếp đánh giá',
            self::Board => 'Ban giám đốc đánh giá',
            self::Custom => 'Hội đồng tùy chỉnh',
        };
    }

    /**
     * Default hội đồng rows for a new form.
     *
     * @return list<array{role_key: string, label: string, weight_percent: float, sort_order: int}>
     */
    public static function defaultRaters(): array
    {
        return [
            ['role_key' => self::Self->value, 'label' => self::Self->label(), 'weight_percent' => 0, 'sort_order' => 0],
            ['role_key' => self::DeptHead->value, 'label' => self::DeptHead->label(), 'weight_percent' => 0, 'sort_order' => 1],
            ['role_key' => self::DirectManager->value, 'label' => self::DirectManager->label(), 'weight_percent' => 0, 'sort_order' => 2],
            ['role_key' => self::Board->value, 'label' => self::Board->label(), 'weight_percent' => 0, 'sort_order' => 3],
        ];
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $c) => ['value' => $c->value, 'label' => $c->label()],
            self::cases(),
        );
    }
}
