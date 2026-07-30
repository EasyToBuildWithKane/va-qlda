<?php

namespace App\Support\Auth;

/**
 * Single source of truth for the RBAC permission catalog.
 *
 * Each module declares a label, an icon and its abilities (key suffix → label).
 * Full permission keys are "{module}.{ability}". The matrix at /settings
 * (permissions tab) toggles which role holds which key; policies consult them
 * through {@see \App\Models\SystemAccount::allows()} →
 * {@see Permissions::roleAllows()} (which understands the grant hierarchy:
 * '*', '{module}.*', '{module}.manage', exact key).
 *
 * Standard actions map to the production UX columns the admin expects
 * (Xem / Tạo / Sửa / Xóa / Duyệt / Nhập / Xuất / Phân công / Toàn quyền);
 * modules may add business-specific abilities on top.
 *
 * Reserved keys ({@see RESERVED}) are super-admin only — stripped from every
 * other role both on save (controller) and overlay (provider).
 */
final class PermissionCatalog
{
    /** Standard action suffixes in display order, with VI labels. */
    public const STANDARD_ACTIONS = [
        'view' => 'Xem',
        'create' => 'Tạo',
        'update' => 'Sửa',
        'delete' => 'Xóa',
        'review' => 'Duyệt',
        'import' => 'Nhập',
        'export' => 'Xuất',
        'assign' => 'Phân công',
        'manage' => 'Toàn quyền',
    ];

    /** Keys only super_admin may ever hold. */
    public const RESERVED = [
        'system.settings.view',
        'system.settings.manage',
        'permissions.manage',
        'roles.assign',
        'workspace.hub.manage',
        'workspace.evaluation.view',
        'workspace.evaluation.manage',
    ];

    /**
     * module key → [label, icon, abilities(suffix => label)].
     *
     * @return array<string, array{label:string, icon:string, group:string, abilities:array<string,string>}>
     */
    public static function modules(): array
    {
        return [
            // ── Nghiệp vụ dự án ───────────────────────────────────────
            'project' => ['label' => 'Dự án & Công việc', 'icon' => 'projects', 'group' => 'Công việc', 'abilities' => [
                'view' => 'Xem', 'create' => 'Tạo', 'update' => 'Sửa', 'delete' => 'Xóa',
                'manage' => 'Quản lý (sprint, task, thành viên)', 'contribute' => 'Đóng góp công việc',
            ]],
            'blocker' => ['label' => 'Vướng mắc', 'icon' => 'blockers', 'group' => 'Công việc', 'abilities' => [
                'create' => 'Tạo', 'update' => 'Sửa', 'delete' => 'Xóa', 'comment' => 'Bình luận',
            ]],
            'daily_report' => ['label' => 'Báo cáo ngày', 'icon' => 'daily', 'group' => 'Công việc', 'abilities' => [
                'view' => 'Xem', 'create' => 'Tạo', 'update' => 'Sửa', 'delete' => 'Xóa', 'review' => 'Duyệt / Chấm điểm',
            ]],
            'feedback' => ['label' => 'Phản hồi', 'icon' => 'feedback', 'group' => 'Công việc', 'abilities' => [
                'create' => 'Tạo', 'update' => 'Sửa', 'delete' => 'Xóa',
            ]],
            'weekly_report' => ['label' => 'Báo cáo tuần', 'icon' => 'daily', 'group' => 'Công việc', 'abilities' => [
                'view' => 'Xem', 'generate' => 'Tạo / Tổng hợp', 'update' => 'Chỉnh sửa',
                'submit' => 'Gửi duyệt', 'approve' => 'Duyệt / Trả lại', 'export' => 'Xuất',
            ]],
            'my_work' => ['label' => 'Việc của tôi', 'icon' => 'calendar-clock', 'group' => 'Công việc', 'abilities' => [
                'view_team' => 'Xem việc của thành viên nhóm', 'act_team' => 'Đổi trạng thái việc của thành viên',
            ]],

            // ── Hợp đồng & Nhà cung cấp ──────────────────────────────
            'contract' => ['label' => 'Hợp đồng (CLM)', 'icon' => 'budget', 'group' => 'Hợp đồng', 'abilities' => [
                'view' => 'Xem', 'create' => 'Tạo', 'update' => 'Sửa', 'delete' => 'Xóa',
                'manage' => 'Quản lý chung', 'import' => 'Nhập', 'export' => 'Xuất',
            ]],
            'vendor' => ['label' => 'Nhà cung cấp', 'icon' => 'org-teams', 'group' => 'Hợp đồng', 'abilities' => [
                'view' => 'Xem', 'create' => 'Tạo', 'update' => 'Sửa', 'delete' => 'Xóa',
            ]],

            // ── AI Workspace ─────────────────────────────────────────
            'ai_account' => ['label' => 'Tài khoản AI', 'icon' => 'sparkles', 'group' => 'AI', 'abilities' => [
                'create' => 'Tạo', 'update' => 'Sửa', 'delete' => 'Xóa', 'renew' => 'Gia hạn',
                'view_password' => 'Xem mật khẩu', 'manage_password_viewers' => 'Quản lý người xem mật khẩu',
                'trigger_reminder' => 'Gửi nhắc thủ công', 'update_renewal_payment' => 'Cập nhật thanh toán gia hạn',
            ]],
            'ai_proposal' => ['label' => 'Đề xuất & Thanh toán AI', 'icon' => 'documents', 'group' => 'AI', 'abilities' => [
                'create' => 'Tạo đề xuất', 'update' => 'Sửa', 'review' => 'Duyệt đề xuất', 'delete' => 'Xóa',
                'payment_review' => 'Duyệt đề nghị thanh toán', 'payment_mark_paid' => 'Đánh dấu đã thanh toán',
                'payment_delete' => 'Xóa đề nghị thanh toán',
            ]],

            // ── Bảo mật & Tài sản số ─────────────────────────────────
            'credential' => ['label' => 'Kho tài khoản & mật khẩu', 'icon' => 'vault', 'group' => 'Bảo mật', 'abilities' => [
                'create' => 'Tạo', 'update' => 'Sửa', 'delete' => 'Xóa', 'view_password' => 'Xem mật khẩu',
                'share' => 'Chia sẻ', 'manage_access' => 'Quản lý quyền truy cập', 'export' => 'Xuất',
            ]],

            // ── Tổ chức & Nhân sự ────────────────────────────────────
            'department' => ['label' => 'Phòng ban', 'icon' => 'department', 'group' => 'Tổ chức', 'abilities' => [
                'create' => 'Tạo', 'update' => 'Sửa', 'delete' => 'Xóa',
            ]],
            'employee' => ['label' => 'Hồ sơ nhân sự', 'icon' => 'members', 'group' => 'Tổ chức', 'abilities' => [
                'update' => 'Sửa hồ sơ (mọi người)',
            ]],

            // ── Đào tạo & Tri thức ───────────────────────────────────
            'kb' => ['label' => 'Cơ sở tri thức', 'icon' => 'knowledge', 'group' => 'Tri thức', 'abilities' => [
                'create' => 'Tạo', 'update' => 'Sửa', 'delete' => 'Xóa', 'publish' => 'Xuất bản',
            ]],

            // ── Trung tâm Công nghệ ──────────────────────────────────
            'congnghe' => ['label' => 'Trung tâm Công Nghệ', 'icon' => 'rocket', 'group' => 'Công nghệ', 'abilities' => [
                'manage_content' => 'Quản trị nội dung trang', 'manage_proposals' => 'Quản lý đề xuất phần mềm',
            ]],

            // ── Phân tích & Hệ thống ─────────────────────────────────
            'performance' => ['label' => 'Hiệu suất & Audit', 'icon' => 'performance', 'group' => 'Hệ thống', 'abilities' => [
                'view' => 'Xem bảng hiệu suất & audit',
            ]],
            'notification' => ['label' => 'Trung tâm vận hành (Thông báo)', 'icon' => 'send', 'group' => 'Hệ thống', 'abilities' => [
                'manage' => 'Gửi & quản lý thông báo hệ thống',
            ]],
            'audit' => ['label' => 'Nhật ký truy vết', 'icon' => 'shield', 'group' => 'Hệ thống', 'abilities' => [
                'view' => 'Xem nhật ký audit hệ thống',
            ]],

            // ── Workspace hub (hub.view mở cho role thường; manage/evaluation.* reserved) ──
            'workspace' => ['label' => 'Cấu hình workspace', 'icon' => 'system-config', 'group' => 'Quản trị tối cao', 'abilities' => [
                'hub.view' => 'Xem workspace phòng ban của mình',
                'hub.manage' => 'Quản trị mọi workspace phòng ban',
                'evaluation.view' => 'Xem cấu hình tiêu chí đánh giá',
                'evaluation.manage' => 'Quản lý cấu hình tiêu chí đánh giá',
            ]],
            'system' => ['label' => 'Cấu hình hệ thống', 'icon' => 'system-config', 'group' => 'Quản trị tối cao', 'abilities' => [
                'settings.view' => 'Xem cấu hình', 'settings.manage' => 'Sửa cấu hình hệ thống',
            ]],
            'permissions' => ['label' => 'Ma trận phân quyền', 'icon' => 'members', 'group' => 'Quản trị tối cao', 'abilities' => [
                'manage' => 'Chỉnh sửa ma trận phân quyền',
            ]],
            'roles' => ['label' => 'Vai trò tài khoản', 'icon' => 'account', 'group' => 'Quản trị tối cao', 'abilities' => [
                'assign' => 'Gán vai trò cho tài khoản',
            ]],
        ];
    }

    /**
     * Flat list of every permission key in "{module}.{ability}" form.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        $keys = [];
        foreach (self::modules() as $module => $def) {
            foreach (array_keys($def['abilities']) as $ability) {
                $keys[] = "{$module}.{$ability}";
            }
        }

        return $keys;
    }

    /**
     * key → human label ("{module label} · {ability label}").
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $out = [];
        foreach (self::modules() as $module => $def) {
            foreach ($def['abilities'] as $ability => $label) {
                $out["{$module}.{$ability}"] = "{$def['label']} · {$label}";
            }
        }

        return $out;
    }

    /** @return array<int, string> */
    public static function reservedKeys(): array
    {
        return self::RESERVED;
    }

    public static function isReserved(string $key): bool
    {
        return \in_array($key, self::RESERVED, true);
    }

    /**
     * Strip reserved keys from a grant list (used for any role ≠ super_admin).
     *
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    public static function withoutReserved(array $keys): array
    {
        return array_values(array_filter($keys, static fn (string $k) => ! self::isReserved($k)));
    }

    /** Every non-reserved key — the default full set for `admin`. @return array<int,string> */
    public static function adminKeys(): array
    {
        return self::withoutReserved(self::keys());
    }

    /**
     * Default role → permission grants. Mirrors the historical policy behaviour
     * so enabling the matrix causes no regression; super_admin is unconditional.
     *
     * Two intentional hardenings vs. the old open code (documented in
     * docs/PERMISSIONS.md): ai_account mutations default to admin/lead only
     * (previously any active account could CRUD AI accounts).
     *
     * @return array<string, array<int, string>>
     */
    public static function defaultGrants(): array
    {
        return [
            'super_admin' => ['*'],
            'admin' => self::adminKeys(),
            'lead' => [
                'project.view', 'project.create', 'project.update', 'project.manage', 'project.contribute',
                'blocker.create', 'blocker.update', 'blocker.delete', 'blocker.comment',
                'daily_report.view', 'daily_report.create', 'daily_report.review',
                'feedback.create', 'feedback.update',
                'weekly_report.view', 'weekly_report.generate', 'weekly_report.update', 'weekly_report.export',
                'my_work.view_team', 'my_work.act_team',
                'contract.view', 'contract.create', 'contract.update', 'contract.delete', 'contract.manage', 'contract.import', 'contract.export',
                'vendor.view', 'vendor.create', 'vendor.update', 'vendor.delete',
                'ai_account.create', 'ai_account.update', 'ai_account.delete', 'ai_account.renew',
                'ai_account.trigger_reminder', 'ai_account.update_renewal_payment',
                'ai_proposal.create',
                'credential.create',
                'department.create', 'department.update',
                'kb.create', 'kb.update', 'kb.delete', 'kb.publish',
                'congnghe.manage_proposals',
                'performance.view',
                'workspace.hub.view',
            ],
            'member' => [
                'daily_report.create',
                'blocker.create', 'blocker.comment',
                'ai_proposal.create',
                'credential.create',
                'kb.create',
                'feedback.create',
                'weekly_report.view',
                'workspace.hub.view',
            ],
            'viewer' => [
                'project.view',
                'daily_report.view',
                'contract.view',
                'vendor.view',
                'performance.view',
                'weekly_report.view', 'weekly_report.export',
                'workspace.hub.view',
            ],
        ];
    }
}
