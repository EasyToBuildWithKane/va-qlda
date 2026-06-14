<?php

namespace App\Support\Coaching;

use App\Models\CoachingSession;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use Illuminate\Database\Eloquent\Builder;

final class CoachingSessionScope
{
    /**
     * @return Builder<CoachingSession>
     */
    public static function forAccount(SystemAccount $account): Builder
    {
        $query = CoachingSession::query();

        if ($account->role === SystemRole::Member && $account->employee_id) {
            $query->whereHas('course', fn (Builder $q) => $q->where('student_id', $account->employee_id));
        }

        return $query;
    }
}
