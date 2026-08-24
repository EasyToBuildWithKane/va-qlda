<?php

namespace App\Http\Controllers;

use App\Support\Enums\SystemRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Xem giao diện theo vai trò" — super-admin-only UI preview.
 *
 * Chỉ đổi những gì HandleInertiaRequests chia sẻ cho frontend (auth.user, nav,
 * permissions hiển thị). Không đụng tới $request->user() thật — mọi Policy/Gate/
 * FormRequest::authorize() vẫn chạy trên tài khoản super_admin thật, nên tính
 * năng này không bao giờ thay đổi quyền thật sự và không thể tự khoá chân người
 * dùng khỏi bất kỳ route nào (kể cả /settings).
 */
class ViewAsController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'role' => ['required', 'string', Rule::in(
                array_diff(SystemRole::values(), [SystemRole::SuperAdmin->value])
            )],
        ], [
            'role.required' => 'Vui lòng chọn vai trò cần xem thử.',
            'role.in' => 'Vai trò không hợp lệ.',
        ]);

        $request->session()->put('view_as.role', $data['role']);

        return back()->with(
            'success',
            'Đang xem giao diện như vai trò: '.SystemRole::from($data['role'])->label().'.'
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('view_as.role');

        return back()->with('success', 'Đã thoát chế độ xem thử.');
    }
}
