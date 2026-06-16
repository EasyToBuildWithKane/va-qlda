<?php

namespace App\Http\Resources\Concerns;

use App\Models\Employee;
use App\Support\PublicMediaUrl;
use BackedEnum;

/**
 * Small presentation helpers shared by the project-management resources so the
 * front-end always receives enums as {value,label,color} and people as a
 * consistent mini-object.
 */
trait PresentsEntities
{
    /**
     * @return array{value:string, label:string, color:string|null}|null
     */
    protected function enum(?BackedEnum $enum): ?array
    {
        if ($enum === null) {
            return null;
        }

        $label = match (true) {
            method_exists($enum, 'label') => $enum->label(),
            method_exists($enum, 'labelVi') => $enum->labelVi(),
            default => (string) $enum->value,
        };

        $color = match (true) {
            method_exists($enum, 'color') => $enum->color(),
            method_exists($enum, 'badgeColor') => $enum->badgeColor(),
            default => null,
        };

        return [
            'value' => $enum->value,
            'label' => $label,
            'color' => $color,
        ];
    }

    /**
     * @return array{id:int, name:string, avatar_path:string|null, code?:string, email?:string|null, role_title?:string|null, is_active:bool}|null
     */
    protected function person(?Employee $employee): ?array
    {
        if ($employee === null) {
            return null;
        }

        return [
            'id' => $employee->id,
            'name' => $employee->full_name,
            'avatar_path' => PublicMediaUrl::fromPublicDisk($employee->avatar_path),
            'code' => $employee->code,
            'email' => $employee->email,
            'role_title' => $employee->role_title,
            'is_active' => (bool) $employee->is_active,
        ];
    }
}
