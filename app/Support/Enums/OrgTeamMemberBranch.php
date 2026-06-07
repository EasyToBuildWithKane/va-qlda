<?php

namespace App\Support\Enums;

enum OrgTeamMemberBranch: string
{
    case Gvs = 'gvs';
    case DepartmentSoftware = 'department_software';
    case ProjectAssistant = 'project_assistant';

    public function label(): string
    {
        return match ($this) {
            self::Gvs => 'Nhánh GVS',
            self::DepartmentSoftware => 'Phần mềm phòng ban',
            self::ProjectAssistant => 'Trợ lý dự án',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Gvs => 'violet',
            self::DepartmentSoftware => 'sky',
            self::ProjectAssistant => 'amber',
        };
    }

    /**
     * @return list<array{value: string, label: string, color: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'color' => $case->color(),
            ],
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
