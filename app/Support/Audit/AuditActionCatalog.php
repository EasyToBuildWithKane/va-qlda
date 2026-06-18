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
            'contract' => ['label' => 'Quản lý hợp đồng', 'icon' => 'documents'],
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

            // ── Hợp đồng & NCC ─────────────────────────────────────────
            'contract.created' => ['label' => 'Tạo hợp đồng', 'module' => 'contract', 'severity' => 'notice'],
            'contract.updated' => ['label' => 'Cập nhật hợp đồng', 'module' => 'contract', 'severity' => 'info'],
            'contract.deleted' => ['label' => 'Xóa hợp đồng', 'module' => 'contract', 'severity' => 'warning'],
            'vendor.review_created' => ['label' => 'Đánh giá NCC (hồ sơ gốc)', 'module' => 'contract', 'severity' => 'notice'],
            'vendor.review_updated' => ['label' => 'Sửa đánh giá NCC (hồ sơ gốc)', 'module' => 'contract', 'severity' => 'info'],
            'vendor.review_deleted' => ['label' => 'Xóa đánh giá NCC (hồ sơ gốc)', 'module' => 'contract', 'severity' => 'warning'],
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
        $action = trim($action);
        if ($action === '') {
            return 'system';
        }

        $modules = self::modules();
        $segment = strtok($action, '.') ?: '';
        if ($segment !== '' && isset($modules[$segment])) {
            return $segment;
        }

        foreach (self::actionPrefixModuleMap() as $prefix => $moduleKey) {
            if (str_starts_with($action, $prefix.'.') || str_starts_with($action, $prefix.'_')) {
                return $moduleKey;
            }
        }

        return 'system';
    }

    /**
     * Tiền tố action (có dấu chấm/gạch dưới) → module key khi action chưa có trong catalog.
     *
     * @return array<string, string>
     */
    private static function actionPrefixModuleMap(): array
    {
        return [
            'auth' => 'auth',
            'settings' => 'settings',
            'account' => 'rbac',
            'ai_account' => 'ai_account',
            'ai_payment' => 'ai_proposal',
            'ai_proposal' => 'ai_proposal',
            'kb_article' => 'kb',
            'coaching_course' => 'coaching',
            'coaching_session' => 'coaching',
            'org_team' => 'org_team',
            'employee' => 'employee',
            'department' => 'department',
            'congnghe_proposal' => 'congnghe',
            'contract' => 'contract',
            'vendor' => 'contract',
        ];
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

    /**
     * Tiền tố action (segment trước dấu chấm) gom về module — dùng lọc SQL khi action chưa khai báo catalog.
     *
     * @return array<int, string>
     */
    public static function actionPrefixesForModule(string $module): array
    {
        $prefixes = [];
        if (isset(self::modules()[$module])) {
            $prefixes[] = $module;
        }
        foreach (self::actionPrefixModuleMap() as $prefix => $moduleKey) {
            if ($moduleKey === $module) {
                $prefixes[] = $prefix;
            }
        }

        return array_values(array_unique($prefixes));
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<\App\Models\SecurityAuditLog>  $query */
    public static function applyModuleFilter($query, string $module): void
    {
        $known = self::actionsForModule($module);
        $prefixes = self::actionPrefixesForModule($module);

        $query->where(function ($q) use ($known, $prefixes) {
            $applied = false;
            if ($known !== []) {
                $q->whereIn('action', $known);
                $applied = true;
            }
            foreach ($prefixes as $prefix) {
                if (! $applied) {
                    $q->where('action', 'like', $prefix.'.%');
                    $applied = true;
                } else {
                    $q->orWhere('action', 'like', $prefix.'.%');
                }
            }
            if (! $applied) {
                $q->whereRaw('0 = 1');
            }
        });
    }

    public static function subjectTypeLabel(?string $type): ?string
    {
        if ($type === null || trim($type) === '') {
            return null;
        }

        return match ($type) {
            'contract' => 'Hợp đồng',
            'vendor' => 'Nhà cung cấp',
            'system_account' => 'Tài khoản hệ thống',
            'employee' => 'Nhân sự',
            'department' => 'Phòng ban',
            'settings_group' => 'Nhóm cấu hình',
            'email_template' => 'Mẫu email',
            'ai_account' => 'Tài khoản AI',
            'ai_payment_request' => 'Đề nghị thanh toán AI',
            'ai_purchase_proposal' => 'Đề xuất mua AI',
            'kb_article' => 'Bài tri thức',
            'coaching_course' => 'Khóa đào tạo',
            'coaching_session' => 'Buổi đào tạo',
            'org_team' => 'Nhóm tổ chức',
            'congnghe_software_proposal' => 'Đề xuất phần mềm',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    /**
     * Dòng tóm tắt đối tượng cho UI audit (tiếng Việt).
     *
     * @param  array<string, mixed>  $meta
     */
    public static function formatSubjectSummary(?string $subjectType, ?int $subjectId, array $meta): ?string
    {
        $typeLabel = self::subjectTypeLabel($subjectType);
        $hasId = $subjectId !== null;
        $hint = self::metaSubjectHint($meta);

        if ($typeLabel === null && ! $hasId && $hint === null) {
            return null;
        }

        $idPart = $hasId ? "#{$subjectId}" : '';
        $base = trim(($typeLabel ?? 'Đối tượng').$idPart);

        return $hint !== null ? "{$base} · {$hint}" : $base;
    }

    /**
     * Một dòng xem nhanh metadata (không lộ secret).
     *
     * @param  array<string, mixed>  $meta
     */
    public static function formatMetaPreview(array $meta): ?string
    {
        if ($meta === []) {
            return null;
        }

        $parts = [];
        if (isset($meta['code']) && is_scalar($meta['code']) && (string) $meta['code'] !== '') {
            $parts[] = 'Mã: '.(string) $meta['code'];
        }
        if (isset($meta['name']) && is_scalar($meta['name']) && (string) $meta['name'] !== '') {
            $parts[] = 'Tên: '.(string) $meta['name'];
        }
        if (isset($meta['fields']) && is_array($meta['fields']) && $meta['fields'] !== []) {
            $fields = array_values(array_filter(array_map('strval', $meta['fields'])));
            if ($fields !== []) {
                $parts[] = 'Trường: '.implode(', ', $fields);
            }
        }
        if (isset($meta['target']) && is_scalar($meta['target']) && (string) $meta['target'] !== '') {
            $parts[] = 'Đích: '.(string) $meta['target'];
        }
        if (isset($meta['group']) && is_scalar($meta['group']) && (string) $meta['group'] !== '') {
            $parts[] = 'Nhóm: '.(string) $meta['group'];
        }

        if ($parts !== []) {
            return implode(' · ', $parts);
        }

        $keys = array_keys($meta);
        if (count($keys) === 1) {
            $k = $keys[0];
            $v = $meta[$k];
            if (is_scalar($v)) {
                return "{$k}: {$v}";
            }
        }

        return count($keys).' trường — bấm «Chi tiết» để xem đầy đủ';
    }

    /** @param  array<string, mixed>  $meta */
    private static function metaSubjectHint(array $meta): ?string
    {
        if (isset($meta['code']) && is_scalar($meta['code']) && (string) $meta['code'] !== '') {
            return (string) $meta['code'];
        }
        if (isset($meta['name']) && is_scalar($meta['name']) && (string) $meta['name'] !== '') {
            $name = (string) $meta['name'];

            return mb_strlen($name) > 48 ? mb_substr($name, 0, 45).'…' : $name;
        }

        return null;
    }
}
