<?php

namespace App\Policies;

use App\Models\SystemAccount;
use App\Models\Task;
use App\Support\Performance\PerformanceTaskScope;
use App\Support\Team\LedTeamScope;
use Illuminate\Support\Facades\Gate;

/**
 * Policy hẹp cho Task — chỉ ability `changeStatus`, phục vụ đổi trạng thái nhanh
 * (Kanban / Gantt / màn hình "Việc của tôi").
 *
 * Additive: cho phép nếu đóng góp được vào dự án (quyền cũ — board không hồi quy)
 * HOẶC có `my_work.act_team` và là trưởng nhóm của người được giao việc. Không
 * bao giờ siết quyền hiện hữu; super_admin vẫn được Gate::before cho qua.
 */
class TaskPolicy
{
    public function changeStatus(SystemAccount $account, Task $task): bool
    {
        if (Gate::forUser($account)->allows('contribute', $task->project)) {
            return true;
        }

        if ($account->employee_id === null || ! $account->allows('my_work.act_team')) {
            return false;
        }

        $task->loadMissing('assignees');

        return LedTeamScope::leadsAny($account->employee_id, PerformanceTaskScope::assigneeIds($task));
    }
}
