<?php

namespace App\Support\Audit;

/**
 * Nguồn sự thật cho sổ cái audit hợp nhất (security_audit_logs).
 *
 * Mỗi `action` (chuỗi lưu ở cột `action`) ánh xạ tới nhãn tiếng Việt, module
 * (để gom nhóm + tô màu/icon trên trang /audit) và severity. Dùng chung bởi
 * {@see \App\Support\SecurityAuditLogger} (ghi log) và
 * {@see \App\Http\Controllers\Audit\AuditLogController} (đọc/hiển thị).
 *
 * Severity điều khiển màu badge: info (xám) · notice (xanh) · warning (hổ phách)
 * · critical (đỏ).
 */
final class AuditActionCatalog
{
    /**
     * module key → [label, icon]. Icon khớp với map trong AppIcon.vue.
     *
     * @return array<string, array{label:string, icon:string}>
     */
    public static function modules(): array
    {
        return [
            'auth' => ['label' => 'Đăng nhập & Bảo mật', 'icon' => 'account'],
            'settings' => ['label' => 'Cấu hình hệ thống', 'icon' => 'system-config'],
            'rbac' => ['label' => 'Phân quyền & Vai trò', 'icon' => 'members'],
            'ai_account' => ['label' => 'Tài khoản AI', 'icon' => 'sparkles'],
            'ai_proposal' => ['label' => 'Đề xuất & Thanh toán AI', 'icon' => 'documents'],
            'kb' => ['label' => 'Cơ sở tri thức', 'icon' => 'knowledge'],
            'coaching' => ['label' => 'Đào tạo & Coaching', 'icon' => 'learning'],
            'org_team' => ['label' => 'Sơ đồ tổ chức', 'icon' => 'org-teams'],
            'employee' => ['label' => 'Hồ sơ nhân sự', 'icon' => 'members'],
            'department' => ['label' => 'Phòng ban', 'icon' => 'department'],
            'congnghe' => ['label' => 'Trung tâm Công nghệ', 'icon' => 'rocket'],
            'system' => ['label' => 'Hệ thống', 'icon' => 'settings'],
        ];
    }

    /**
     * action → [label, module, severity].
     *
     * @return array<string, array{label:string, module:string, severity:string}>
     */
    public static function actions(): array
    {
        return [
            // ── Auth ──────────────────────────────────────────────────
            'auth.login' => ['label' => 'Đăng nhập', 'module' => 'auth', 'severity' => 'info'],
            'auth.logout' => ['label' => 'Đăng xuất', 'module' => 'auth', 'severity' => 'info'],
            'auth.login_failed' => ['label' => 'Đăng nhập thất bại', 'module' => 'auth', 'severity' => 'warning'],
            'auth.tech_denied' => ['label' => 'Bị từ chối cổng Công nghệ', 'module' => 'auth', 'severity' => 'warning'],

            // ── Settings ──────────────────────────────────────────────
            'settings.updated' => ['label' => 'Cập nhật cấu hình', 'module' => 'settings', 'severity' => 'notice'],
            'settings.permissions_updated' => ['label' => 'Sửa ma trận phân quyền', 'module' => 'settings', 'severity' => 'critical'],
            'settings.email_template_updated' => ['label' => 'Cập nhật mẫu email', 'module' => 'settings', 'severity' => 'notice'],
            'settings.email_template_reset' => ['label' => 'Khôi phục mẫu email', 'module' => 'settings', 'severity' => 'notice'],

            // ── RBAC ──────────────────────────────────────────────────
            'account.role_changed' => ['label' => 'Đổi vai trò tài khoản', 'module' => 'rbac', 'severity' => 'critical'],

            // ── AI account ────────────────────────────────────────────
            'ai_account.created' => ['label' => 'Tạo tài khoản AI', 'module' => 'ai_account', 'severity' => 'notice'],
            'ai_account.updated' => ['label' => 'Sửa tài khoản AI', 'module' => 'ai_account', 'severity' => 'notice'],
            'ai_account.deleted' => ['label' => 'Xóa tài khoản AI', 'module' => 'ai_account', 'severity' => 'warning'],
            'ai_account.password_viewed' => ['label' => 'Xem mật khẩu AI', 'module' => 'ai_account', 'severity' => 'warning'],
            'ai_payment.created' => ['label' => 'Tạo đề nghị thanh toán AI', 'module' => 'ai_proposal', 'severity' => 'notice'],
            'ai_payment.updated' => ['label' => 'Cập nhật thanh toán AI', 'module' => 'ai_proposal', 'severity' => 'notice'],
            'ai_payment.approved' => ['label' => 'Duyệt thanh toán AI', 'module' => 'ai_proposal', 'severity' => 'notice'],
            'ai_payment.rejected' => ['label' => 'Từ chối thanh toán AI', 'module' => 'ai_proposal', 'severity' => 'warning'],
            'ai_payment.deleted' => ['label' => 'Xóa đề nghị thanh toán AI', 'module' => 'ai_proposal', 'severity' => 'warning'],
            'ai_proposal.created' => ['label' => 'Tạo đề xuất AI', 'module' => 'ai_proposal', 'severity' => 'notice'],
            'ai_proposal.updated' => ['label' => 'Sửa đề xuất AI', 'module' => 'ai_proposal', 'severity' => 'notice'],
            'ai_proposal.approved' => ['label' => 'Duyệt đề xuất AI', 'module' => 'ai_proposal', 'severity' => 'notice'],
            'ai_proposal.rejected' => ['label' => 'Từ chối đề xuất AI', 'module' => 'ai_proposal', 'severity' => 'warning'],
            'ai_proposal.deleted' => ['label' => 'Xóa đề xuất AI', 'module' => 'ai_proposal', 'severity' => 'warning'],

            // ── Knowledge Base ────────────────────────────────────────
            'kb_article.created' => ['label' => 'Tạo bài tri thức', 'module' => 'kb', 'severity' => 'notice'],
            'kb_article.updated' => ['label' => 'Sửa bài tri thức', 'module' => 'kb', 'severity' => 'info'],
            'kb_article.deleted' => ['label' => 'Xóa bài tri thức', 'module' => 'kb', 'severity' => 'warning'],
            'kb_article.published' => ['label' => 'Xuất bản bài tri thức', 'module' => 'kb', 'severity' => 'notice'],

            // ── Coaching ──────────────────────────────────────────────
            'coaching_course.created' => ['label' => 'Tạo khóa đào tạo', 'module' => 'coaching', 'severity' => 'notice'],
            'coaching_course.updated' => ['label' => 'Sửa khóa đào tạo', 'module' => 'coaching', 'severity' => 'info'],
            'coaching_course.deleted' => ['label' => 'Xóa khóa đào tạo', 'module' => 'coaching', 'severity' => 'warning'],
            'coaching_session.created' => ['label' => 'Tạo buổi đào tạo', 'module' => 'coaching', 'severity' => 'notice'],
            'coaching_session.updated' => ['label' => 'Sửa buổi đào tạo', 'module' => 'coaching', 'severity' => 'info'],
            'coaching_session.deleted' => ['label' => 'Xóa buổi đào tạo', 'module' => 'coaching', 'severity' => 'warning'],

            // ── OrgTeam ───────────────────────────────────────────────
            'org_team.created' => ['label' => 'Tạo nhóm tổ chức', 'module' => 'org_team', 'severity' => 'notice'],
            'org_team.updated' => ['label' => 'Sửa nhóm tổ chức', 'module' => 'org_team', 'severity' => 'info'],
            'org_team.deleted' => ['label' => 'Xóa nhóm tổ chức', 'module' => 'org_team', 'severity' => 'warning'],

            // ── Hồ sơ & Phòng ban ─────────────────────────────────────
            'employee.updated' => ['label' => 'Cập nhật hồ sơ nhân sự', 'module' => 'employee', 'severity' => 'info'],
            'department.created' => ['label' => 'Tạo phòng ban', 'module' => 'department', 'severity' => 'notice'],
            'department.updated' => ['label' => 'Sửa phòng ban', 'module' => 'department', 'severity' => 'info'],
            'department.deleted' => ['label' => 'Xóa phòng ban', 'module' => 'department', 'severity' => 'warning'],

            // ── Congnghe ──────────────────────────────────────────────
            'congnghe_proposal.created' => ['label' => 'Tạo đề xuất phần mềm', 'module' => 'congnghe', 'severity' => 'notice'],
            'congnghe_proposal.updated' => ['label' => 'Sửa đề xuất phần mềm', 'module' => 'congnghe', 'severity' => 'info'],
            'congnghe_proposal.status_changed' => ['label' => 'Đổi trạng thái đề xuất phần mềm', 'module' => 'congnghe', 'severity' => 'notice'],
            'congnghe_proposal.deleted' => ['label' => 'Xóa đề xuất phần mềm', 'module' => 'congnghe', 'severity' => 'warning'],
        ];
    }

    /**
     * Mô tả đầy đủ cho một action (fallback an toàn nếu chưa khai báo).
     *
     * @return array{action:string, label:string, severity:string, module:string, module_label:string, icon:string}
     */
    public static function describe(string $action): array
    {
        $meta = self::actions()[$action] ?? null;
        $moduleKey = $meta['module'] ?? self::guessModule($action);
        $module = self::modules()[$moduleKey] ?? self::modules()['system'];

        return [
            'action' => $action,
            'label' => $meta['label'] ?? $action,
            'severity' => $meta['severity'] ?? 'info',
            'module' => $moduleKey,
            'module_label' => $module['label'],
            'icon' => $module['icon'],
        ];
    }

    /** Suy ra module từ tiền tố action khi chưa khai báo (an toàn cho action lạ). */
    private static function guessModule(string $action): string
    {
        $prefix = strtok($action, '._');
        $modules = self::modules();

        return isset($modules[$prefix]) ? $prefix : 'system';
    }

    /**
     * Danh sách module cho bộ lọc trên UI: [{ key, label, icon }].
     *
     * @return array<int, array{key:string, label:string, icon:string}>
     */
    public static function moduleOptions(): array
    {
        $out = [];
        foreach (self::modules() as $key => $def) {
            $out[] = ['key' => $key, 'label' => $def['label'], 'icon' => $def['icon']];
        }

        return $out;
    }

    /** Mọi action thuộc một module (để build filter where-in). @return array<int,string> */
    public static function actionsForModule(string $module): array
    {
        $out = [];
        foreach (self::actions() as $action => $def) {
            if ($def['module'] === $module) {
                $out[] = $action;
            }
        }

        return $out;
    }
}
