<?php

namespace App\Support;

use App\Models\SecurityAuditLog;
use App\Models\SystemAccount;

/**
 * Ghi sổ cái audit hợp nhất (security_audit_logs) cho các sự kiện cross-module
 * không có timeline riêng: Auth, cấu hình, phân quyền, vòng đời AI/KB…
 *
 * Nhãn/màu/icon của mỗi action do {@see \App\Support\Audit\AuditActionCatalog}
 * quản lý. TUYỆT ĐỐI không truyền giá trị secret vào $meta — chỉ tên key.
 */
class SecurityAuditLogger
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function log(
        ?SystemAccount $actor,
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $meta = [],
    ): void {
        SecurityAuditLog::create([
            'actor_account_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'meta' => $meta === [] ? null : $meta,
            'created_at' => now(),
        ]);
    }

    // ── Auth ──────────────────────────────────────────────────────────

    public static function login(SystemAccount $actor, string $portal): void
    {
        self::log($actor, 'auth.login', 'system_account', $actor->id, ['portal' => $portal]);
    }

    public static function logout(?SystemAccount $actor): void
    {
        if ($actor === null) {
            return;
        }
        self::log($actor, 'auth.logout', 'system_account', $actor->id);
    }

    public static function loginFailed(?string $username, string $portal): void
    {
        self::log(null, 'auth.login_failed', null, null, array_filter([
            'username' => $username,
            'portal' => $portal,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    public static function techDenied(SystemAccount $actor, ?string $email): void
    {
        self::log($actor, 'auth.tech_denied', 'system_account', $actor->id, array_filter([
            'email' => $email,
        ]));
    }

    // ── Settings & RBAC ───────────────────────────────────────────────

    /** @param array<int,string> $changedKeys */
    public static function settingsUpdated(SystemAccount $actor, string $group, array $changedKeys): void
    {
        self::log($actor, 'settings.updated', 'settings_group', null, [
            'group' => $group,
            'keys' => array_values($changedKeys),
        ]);
    }

    public static function permissionMatrixUpdated(SystemAccount $actor): void
    {
        self::log($actor, 'settings.permissions_updated', 'settings_group', null, ['group' => 'permissions']);
    }

    public static function emailTemplateUpdated(SystemAccount $actor, int $templateId, string $key, bool $reset = false): void
    {
        self::log(
            $actor,
            $reset ? 'settings.email_template_reset' : 'settings.email_template_updated',
            'email_template',
            $templateId,
            ['key' => $key],
        );
    }

    public static function accountRoleChanged(SystemAccount $actor, SystemAccount $target, string $from, string $to): void
    {
        self::log($actor, 'account.role_changed', 'system_account', $target->id, [
            'target' => $target->display_name,
            'from' => $from,
            'to' => $to,
        ]);
    }

    // ── AI account (password view có sẵn helper riêng) ────────────────

    public static function aiAccountPasswordViewed(SystemAccount $actor, string $aiAccountId, string $label): void
    {
        self::log($actor, 'ai_account.password_viewed', 'ai_account', null, [
            'label' => $label,
            'ai_account_id' => $aiAccountId,
        ]);
    }

    /** @param array<string,mixed> $meta */
    public static function aiAccount(SystemAccount $actor, string $event, ?int $subjectId, array $meta = []): void
    {
        self::log($actor, "ai_account.{$event}", 'ai_account', $subjectId, $meta);
    }

    /** @param array<string,mixed> $meta */
    public static function aiPayment(SystemAccount $actor, string $event, ?int $subjectId, array $meta = []): void
    {
        self::log($actor, "ai_payment.{$event}", 'ai_payment_request', $subjectId, $meta);
    }

    /** @param array<string,mixed> $meta */
    public static function aiProposal(SystemAccount $actor, string $event, ?int $subjectId, array $meta = []): void
    {
        self::log($actor, "ai_proposal.{$event}", 'ai_purchase_proposal', $subjectId, $meta);
    }

    // ── Tri thức / Đào tạo / Tổ chức / Công nghệ ──────────────────────

    /** @param array<string,mixed> $meta */
    public static function kbArticle(SystemAccount $actor, string $event, ?int $subjectId, array $meta = []): void
    {
        self::log($actor, "kb_article.{$event}", 'kb_article', $subjectId, $meta);
    }

    /** @param array<string,mixed> $meta */
    public static function orgTeam(SystemAccount $actor, string $event, ?int $subjectId, array $meta = []): void
    {
        self::log($actor, "org_team.{$event}", 'org_team', $subjectId, $meta);
    }

    public static function employeeUpdated(SystemAccount $actor, int $employeeId, string $name): void
    {
        self::log($actor, 'employee.updated', 'employee', $employeeId, ['name' => $name]);
    }

    /** @param array<string,mixed> $meta */
    public static function department(SystemAccount $actor, string $event, ?int $subjectId, array $meta = []): void
    {
        self::log($actor, "department.{$event}", 'department', $subjectId, $meta);
    }

    /** @param array<string,mixed> $meta */
    public static function congngheProposal(SystemAccount $actor, string $event, ?int $subjectId, array $meta = []): void
    {
        self::log($actor, "congnghe_proposal.{$event}", 'congnghe_software_proposal', $subjectId, $meta);
    }

    /** @param array<string,mixed> $meta */
    public static function contract(SystemAccount $actor, string $event, ?int $subjectId, array $meta = []): void
    {
        self::log($actor, "contract.{$event}", 'contract', $subjectId, $meta);
    }

    /** @param array<string,mixed> $meta */
    public static function vendor(SystemAccount $actor, string $event, ?int $subjectId, array $meta = []): void
    {
        self::log($actor, "vendor.{$event}", 'vendor', $subjectId, $meta);
    }

    // ── Báo cáo ngày ──────────────────────────────────────────────────

    /** @param array<string,mixed> $meta */
    public static function dailyReport(SystemAccount $actor, string $event, ?int $subjectId, array $meta = []): void
    {
        self::log($actor, "daily_report.{$event}", 'daily_report', $subjectId, $meta);
    }

    // ── Báo cáo tuần ──────────────────────────────────────────────────

    /** @param array<string,mixed> $meta */
    public static function weeklyReport(SystemAccount $actor, string $event, ?int $subjectId, array $meta = []): void
    {
        self::log($actor, "weekly_report.{$event}", 'weekly_report', $subjectId, $meta);
    }
}
