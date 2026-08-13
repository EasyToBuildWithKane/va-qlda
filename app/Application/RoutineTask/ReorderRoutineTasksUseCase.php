<?php

namespace App\Application\RoutineTask;

use App\Domain\RoutineTask\Models\RoutineTask;
use Illuminate\Support\Facades\DB;

class ReorderRoutineTasksUseCase
{
    /**
     * @param  array<int, string>  $orderedIds  UUID list in desired order
     */
    public function execute(int $employeeId, array $orderedIds): void
    {
        $ids = array_values(array_filter(array_map(
            static fn ($id) => is_string($id) ? trim($id) : '',
            $orderedIds,
        )));

        if ($ids === []) {
            return;
        }

        DB::transaction(function () use ($employeeId, $ids) {
            $owned = RoutineTask::query()
                ->forEmployee($employeeId)
                ->whereIn('id', $ids)
                ->pluck('id')
                ->all();

            $ownedSet = array_flip($owned);
            $position = 0;

            foreach ($ids as $id) {
                if (! isset($ownedSet[$id])) {
                    continue;
                }

                RoutineTask::query()
                    ->whereKey($id)
                    ->where('employee_id', $employeeId)
                    ->update(['position' => $position]);

                $position++;
            }
        });
    }
}
