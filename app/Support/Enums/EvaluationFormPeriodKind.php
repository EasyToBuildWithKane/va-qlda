<?php

namespace App\Support\Enums;

enum EvaluationFormPeriodKind: string
{
    case Month = 'month';
    case Quarter = 'quarter';
    case HalfYear = 'half_year';
    case Year = 'year';
    case Random = 'random';
    case DateRange = 'date_range';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Month => 'Tháng',
            self::Quarter => 'Quý',
            self::HalfYear => 'Nửa năm',
            self::Year => 'Năm',
            self::Random => 'Ngẫu nhiên',
            self::DateRange => 'Theo ngày',
        };
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
