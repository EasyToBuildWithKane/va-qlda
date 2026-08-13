<?php

namespace App\Policies;

use App\Domain\RoutineTask\Models\RoutineTask;
use App\Models\SystemAccount;

class RoutineTaskPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return $account->employee_id !== null
            || $account->allows('routine_task.view');
    }

    public function view(SystemAccount $account, RoutineTask $routineTask): bool
    {
        return $this->owns($account, $routineTask)
            || $account->allows('routine_task.view');
    }

    public function create(SystemAccount $account): bool
    {
        return $account->employee_id !== null;
    }

    public function update(SystemAccount $account, RoutineTask $routineTask): bool
    {
        return $this->owns($account, $routineTask)
            || $account->allows('routine_task.manage');
    }

    public function delete(SystemAccount $account, RoutineTask $routineTask): bool
    {
        return $this->update($account, $routineTask);
    }

    public function reorder(SystemAccount $account): bool
    {
        return $account->employee_id !== null
            || $account->allows('routine_task.manage');
    }

    private function owns(SystemAccount $account, RoutineTask $routineTask): bool
    {
        return $account->employee_id !== null
            && (int) $account->employee_id === (int) $routineTask->employee_id;
    }
}
