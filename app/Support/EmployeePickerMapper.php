<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Support\Collection;

class EmployeePickerMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function row(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'code' => $employee->code,
            'name' => $employee->full_name,
            'role_title' => $employee->role_title,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'department' => is_array($employee->meta) ? ($employee->meta['department_name'] ?? null) : null,
            'avatar_path' => PublicMediaUrl::fromPublicDisk($employee->avatar_path),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function search(?string $query, int $limit = 40, ?int $id = null): array
    {
        if ($id !== null) {
            $one = Employee::query()->find($id);

            return $one ? [self::row($one)] : [];
        }

        /** @var Collection<int, Employee> $rows */
        $rows = Employee::query()
            ->orderBy('full_name')
            ->get(['id', 'code', 'full_name', 'role_title', 'email', 'phone', 'meta', 'avatar_path']);

        $q = trim((string) $query);
        if ($q !== '') {
            $rows = $rows->filter(function (Employee $e) use ($q) {
                $dept = is_array($e->meta) ? ($e->meta['department_name'] ?? null) : null;

                return VietnameseSearch::matches(
                    [$e->full_name, $e->email, $e->code, $e->role_title, $dept],
                    $q,
                );
            })->take($limit);
        } else {
            $rows = $rows->take(min(50, $limit));
        }

        return $rows->map(fn (Employee $e) => self::row($e))->values()->all();
    }
}
