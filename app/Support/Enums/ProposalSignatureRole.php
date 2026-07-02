<?php

namespace App\Support\Enums;

enum ProposalSignatureRole: string
{
    case Proposer = 'proposer';
    case DepartmentHead = 'department_head';
    case BoardOfDirectors = 'board_of_directors';
    case Accountant = 'accountant';
    case Other = 'other';

    public function labelVi(): string
    {
        return match ($this) {
            self::Proposer => 'Người đề xuất',
            self::DepartmentHead => 'Trưởng bộ phận',
            self::BoardOfDirectors => 'Ban Giám hiệu',
            self::Accountant => 'Kế toán',
            self::Other => 'Khác',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->labelVi(),
        ], self::cases());
    }
}
