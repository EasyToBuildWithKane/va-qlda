<?php

use App\Http\Middleware\RestrictCoachingOnlyUsers;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (Inertia)
|--------------------------------------------------------------------------
|
| Routes are split by functional area into routes/web/*.php. This file only
| wires up the two middleware contexts; each partial registers its own
| routes (with its own controller imports) inside the active group.
|
| Guest:  routes/web/auth.php
| Auth:   every other partial, behind `auth` + RestrictCoachingOnlyUsers.
|
*/

// Unauthenticated — login portals + OAuth.
Route::middleware('guest')->group(base_path('routes/web/auth.php'));

// Authenticated — coaching-only users are redirected to their dashboard.
Route::middleware(['auth', RestrictCoachingOnlyUsers::class])->group(function () {
    foreach ([
        'dashboard',       // dashboards + session
        'congnghe',        // Phòng Công Nghệ landing + đề xuất phần mềm
        'platform',        // performance, onboarding, notifications, audit
        'daily-reports',   // báo cáo ngày
        'projects',        // dự án, sprint, task, worklog, member
        'blockers',        // vướng mắc
        'contracts',       // quản lý hợp đồng (CLM)
        'feedback',        // phản hồi
        'knowledge-base',  // tri thức
        'coaching',        // coaching / mentoring
        'ai-accounts',     // tài khoản AI (API + pages)
        'credentials',     // kho mật khẩu (API + pages)
        'people',          // profile, members, org-teams, departments
        'settings',        // cấu hình hệ thống (super-admin)
        'comments',        // bình luận đa hình + realtime token
    ] as $partial) {
        require __DIR__."/web/{$partial}.php";
    }
});
