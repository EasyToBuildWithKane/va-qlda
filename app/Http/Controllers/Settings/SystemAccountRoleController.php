<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AssignAccountRoleRequest;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use Illuminate\Http\RedirectResponse;

/**
 * Runtime role assignment for login accounts (super-admin only). Lets a super
 * admin promote/demote without editing config/va_permissions.php and redeploying.
 *
 * Guards against locking the system out of configuration by refusing to remove
 * the last active super admin.
 */
class SystemAccountRoleController extends Controller
{
    public function update(AssignAccountRoleRequest $request, SystemAccount $account): RedirectResponse
    {
        $newRole = SystemRole::from($request->validated()['role']);

        if ($account->role === $newRole) {
            return back()->with('success', 'Vai trò không thay đổi.');
        }

        // Never demote the final active super admin — that would lock every
        // account out of system configuration and the permission matrix.
        if ($account->role === SystemRole::SuperAdmin && $newRole !== SystemRole::SuperAdmin) {
            $remainingSupers = SystemAccount::query()
                ->where('role', SystemRole::SuperAdmin->value)
                ->where('is_active', true)
                ->where('id', '!=', $account->id)
                ->count();

            if ($remainingSupers === 0) {
                return back()->with('error', 'Không thể hạ quyền Super Admin cuối cùng. Hãy chỉ định một Super Admin khác trước.');
            }
        }

        $account->forceFill(['role' => $newRole])->save();

        return back()->with('success', "Đã đổi vai trò của {$account->display_name} thành {$newRole->label()}.");
    }
}
