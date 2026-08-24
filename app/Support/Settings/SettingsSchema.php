<?php

namespace App\Support\Settings;

use App\Support\Auth\Permissions;
use App\Support\Navigation;
use Illuminate\Validation\Rule;

/**
 * Single source of truth for admin-editable system settings.
 *
 * Each scalar field declares its type, default, validation and the config()
 * path it overlays at runtime (see SettingsServiceProvider). The DB table
 * stores only overrides; the defaults here keep the app fully working with an
 * empty table. Groups map 1:1 to the tabs on /settings.
 *
 *   general      — app identity surfaced to the UI (va.*)
 *   auth         — login + Google domain/email allow-lists (va.*)
 *   telegram     — bot + notification channels (telegram.*)
 *   email        — task/AI email sending (task_email.*, ai_accounts.reminder.*)
 *   clm          — contract renewal alerts (clm.*)
 *   ai           — LLM API key for weekly report (weekly_report.llm.*)
 *   permissions  — editable role → permission matrix (va_permissions.role_grants)
 *
 * Setting keys are namespaced "{group}.{name}"; the matrix uses the single key
 * "permissions.role_grants".
 */
final class SettingsSchema
{
    /** Tabs/groups, in display order. */
    public const GROUPS = ['general', 'auth', 'telegram', 'email', 'clm', 'ai', 'permissions', 'accounts', 'menu', 'onboarding'];

    public const MATRIX_KEY = 'permissions.role_grants';

    /** Roles the super admin may edit in the matrix — "super_admin" stays full-access. */
    public const EDITABLE_ROLES = ['admin', 'manager', 'deputy_manager', 'team_leader', 'member', 'viewer'];

    public const LOCKED_ROLE = 'super_admin';

    /**
     * @return array<int, array{key:string, label:string, icon:string, description:string}>
     */
    public static function groups(): array
    {
        return [
            ['key' => 'general', 'label' => 'Chung', 'icon' => 'settings', 'description' => 'Nhận diện hệ thống'],
            ['key' => 'auth', 'label' => 'Đăng nhập & Bảo mật', 'icon' => 'account', 'description' => 'Đăng nhập & domain email'],
            ['key' => 'telegram', 'label' => 'Thông báo Telegram', 'icon' => 'send', 'description' => 'Bot & kênh thông báo'],
            ['key' => 'email', 'label' => 'Email & Thông báo', 'icon' => 'mail', 'description' => 'Cấu hình gửi email và mẫu thông báo'],
            ['key' => 'clm', 'label' => 'Hợp đồng (CLM)', 'icon' => 'budget', 'description' => 'Ngưỡng cảnh báo gia hạn hợp đồng'],
            ['key' => 'ai', 'label' => 'Trí tuệ nhân tạo', 'icon' => 'sparkles', 'description' => 'API key LLM — tổng hợp báo cáo tuần'],
            ['key' => 'permissions', 'label' => 'Phân quyền', 'icon' => 'members', 'description' => 'Ma trận vai trò × quyền'],
            ['key' => 'accounts', 'label' => 'Tài khoản & Vai trò', 'icon' => 'account', 'description' => 'Gán vai trò cho tài khoản hệ thống'],
            ['key' => 'menu', 'label' => 'Tùy chỉnh menu', 'icon' => 'columns', 'description' => 'Ẩn/hiện nhóm menu toàn hệ thống; đồng bộ module hub workspace. Menu theo PB cấu hình tại shell workspace'],
            ['key' => 'onboarding', 'label' => 'Chào mừng nhân viên', 'icon' => 'sparkles', 'description' => 'Màn hình chào mừng hiển thị lần đầu khi vào hệ thống'],
        ];
    }

    /**
     * Scalar field definitions (general / auth / telegram). The permissions
     * matrix is handled separately.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function fieldDefs(): array
    {
        return [
            // ── general ──────────────────────────────────────────────
            ['group' => 'general', 'name' => 'app_name', 'type' => 'string', 'label' => 'Tên hệ thống',
                'help' => 'Hiển thị ở tiêu đề trang và chân thanh bên.', 'config' => 'va.app_name',
                'default' => 'VAschools Workspace', 'rules' => ['required', 'string', 'max:120']],
            ['group' => 'general', 'name' => 'app_short_name', 'type' => 'string', 'label' => 'Tên viết tắt',
                'help' => '2–6 ký tự, hiện ở ô thương hiệu khi thu gọn thanh bên.', 'config' => 'va.app_short_name',
                'default' => 'VA', 'rules' => ['required', 'string', 'max:6']],
            ['group' => 'general', 'name' => 'support_email', 'type' => 'string', 'label' => 'Email hỗ trợ',
                'help' => 'Địa chỉ liên hệ hỗ trợ nội bộ.', 'config' => 'va.support_email',
                'default' => null, 'rules' => ['nullable', 'email', 'max:190']],
            ['group' => 'general', 'name' => 'app_version', 'type' => 'string', 'label' => 'Phiên bản hiển thị',
                'help' => 'Nhãn phiên bản ở chân thanh bên (vd. 1.0).', 'config' => 'va.app_version',
                'default' => '1.0', 'rules' => ['nullable', 'string', 'max:20']],
            ['group' => 'general', 'name' => 'congnghe_proposal_email', 'type' => 'string', 'label' => 'Email nhận đề xuất phần mềm',
                'help' => 'Hộp thư nhận đề xuất từ cổng /congnghe/de-xuat (Phòng Công nghệ).', 'config' => 'va.congnghe_proposal_email',
                'default' => null, 'rules' => ['nullable', 'email', 'max:190']],
            ['group' => 'general', 'name' => 'dashboard_personnel_pattern', 'type' => 'string', 'label' => 'Mẫu phòng ban Dashboard',
                'help' => 'Lọc phòng ban nhân sự trên trang Bảng điều khiển (LIKE %pattern%). Mặc định: Công nghệ.',
                'config' => 'va.dashboard_personnel_department_pattern',
                'default' => 'Công nghệ', 'rules' => ['nullable', 'string', 'max:120']],

            // ── auth ─────────────────────────────────────────────────
            ['group' => 'auth', 'name' => 'password_login_enabled', 'type' => 'bool', 'label' => 'Cho phép đăng nhập bằng mật khẩu',
                'help' => 'Bật route POST /login (chủ yếu cho dev/E2E). Production nên tắt — chỉ đăng nhập Google.',
                'config' => 'va.password_login_enabled', 'default' => false, 'rules' => ['boolean']],
            ['group' => 'auth', 'name' => 'google_allowed_domains', 'type' => 'list', 'label' => 'Domain email Google cho phép',
                'help' => 'Bỏ trống = chấp nhận mọi email đã xác minh. Mỗi dòng một domain (vd. vaschools.edu.vn).',
                'config' => 'va.google_allowed_domains', 'default' => [], 'rules' => ['array'], 'itemRules' => ['string', 'max:190']],
            ['group' => 'auth', 'name' => 'google_allowed_emails', 'type' => 'list', 'label' => 'Email Google cho phép (cá nhân)',
                'help' => 'Email không thuộc domain ở trên nhưng vẫn được đăng nhập (vd. gmail.com cá nhân). Mỗi dòng một địa chỉ.',
                'config' => 'va.google_allowed_emails', 'default' => [], 'rules' => ['array'], 'itemRules' => ['email', 'max:190']],
            ['group' => 'auth', 'name' => 'tech_login_allowed_emails', 'type' => 'list', 'label' => 'Email được phép đăng nhập cổng /tech',
                'help' => 'Danh sách email được phép dùng /tech/login. Bỏ trống = dùng danh sách mặc định trong config. Cẩn thận: để trống sẽ fallback về default code, không chặn hoàn toàn.',
                'config' => 'va.tech_login_allowed_emails', 'default' => [], 'rules' => ['array'], 'itemRules' => ['email', 'max:190']],

            // ── telegram ─────────────────────────────────────────────
            ['group' => 'telegram', 'name' => 'enabled', 'type' => 'bool', 'label' => 'Bật thông báo Telegram',
                'help' => 'Tắt sẽ không gọi API Telegram dù có token.', 'config' => 'telegram.enabled',
                'default' => false, 'rules' => ['boolean']],
            ['group' => 'telegram', 'name' => 'bot_token', 'type' => 'secret', 'label' => 'Bot token',
                'help' => 'Lấy từ @BotFather. Để trống nếu không muốn thay đổi.', 'config' => 'telegram.bot_token',
                'default' => null, 'rules' => ['nullable', 'string', 'max:190']],
            ['group' => 'telegram', 'name' => 'chat_id', 'type' => 'string', 'label' => 'Chat ID chung',
                'help' => 'ID nhóm nhận thông báo chung (thường âm với supergroup).', 'config' => 'telegram.chat_id',
                'default' => null, 'rules' => ['nullable', 'string', 'max:64']],
            ['group' => 'telegram', 'name' => 'blocker_chat_id', 'type' => 'string', 'label' => 'Chat ID vướng mắc',
                'help' => 'Nhóm riêng nhận thông báo vướng mắc (tùy chọn).', 'config' => 'telegram.blocker_chat_id',
                'default' => null, 'rules' => ['nullable', 'string', 'max:64']],
            ['group' => 'telegram', 'name' => 'daily_report_review', 'type' => 'bool', 'label' => 'Báo khi duyệt/trả báo cáo ngày',
                'help' => 'Gửi thông báo khi chấm điểm hoặc trả báo cáo trên trang Chờ phê duyệt.',
                'config' => 'telegram.daily_report_review', 'default' => true, 'rules' => ['boolean']],
            ['group' => 'telegram', 'name' => 'blocker_resolved', 'type' => 'bool', 'label' => 'Báo khi vướng mắc được giải quyết',
                'help' => 'Gửi thông báo khi vướng mắc chuyển Đã giải quyết/Đã đóng.',
                'config' => 'telegram.blocker_resolved', 'default' => true, 'rules' => ['boolean']],

            // ── email ────────────────────────────────────────────────
            ['group' => 'email', 'name' => 'enabled', 'type' => 'bool', 'label' => 'Bật gửi email công việc',
                'help' => 'Cho phép gửi email tổng hợp và thông báo giao việc (cần cấu hình MAIL_* trên server).',
                'config' => 'task_email.enabled', 'default' => false, 'rules' => ['boolean']],
            ['group' => 'email', 'name' => 'from_name', 'type' => 'string', 'label' => 'Tên người gửi (From name)',
                'help' => 'Hiển thị trên hộp thư người nhận.', 'config' => 'task_email.from_name',
                'default' => 'VAschools Workspace', 'rules' => ['nullable', 'string', 'max:120']],
            ['group' => 'email', 'name' => 'notify_on_assign', 'type' => 'bool', 'label' => 'Email khi giao việc',
                'help' => 'Gửi email cho người được assign khi tạo công việc mới.',
                'config' => 'task_email.notify_on_assign', 'default' => true, 'rules' => ['boolean']],
            ['group' => 'email', 'name' => 'notify_daily_at', 'type' => 'string', 'label' => 'Giờ gửi tổng hợp ngày (HH:MM)',
                'help' => 'Dùng cho lịch tự động sau này; nút gửi thủ công vẫn hoạt động bất kể giá trị này.',
                'config' => 'task_email.notify_daily_at', 'default' => '17:00',
                'rules' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/']],
            ['group' => 'email', 'name' => 'ai_reminder_enabled', 'type' => 'bool', 'label' => 'Gửi email nhắc hết hạn tài khoản AI',
                'help' => 'Gửi email nhắc hết hạn license AI theo lịch (08:00 và 14:00).',
                'config' => 'ai_accounts.reminder.send_email', 'default' => true, 'rules' => ['boolean']],
            ['group' => 'email', 'name' => 'ai_reminder_extra_emails', 'type' => 'list', 'label' => 'Email nhận nhắc bổ sung (AI)',
                'help' => 'Hộp thư ngoài danh sách admin/lead. Mỗi dòng một địa chỉ.',
                'config' => 'ai_accounts.reminder.extra_recipients', 'default' => [], 'rules' => ['array'], 'itemRules' => ['email', 'max:190']],
            ['group' => 'email', 'name' => 'ai_reminder_include_expired', 'type' => 'bool', 'label' => 'Nhắc tài khoản AI đã hết hạn',
                'help' => 'Gửi thêm nhắc cho tài khoản AI đã quá hạn nhưng chưa gia hạn.',
                'config' => 'ai_accounts.reminder.include_expired', 'default' => true, 'rules' => ['boolean']],
            ['group' => 'email', 'name' => 'ai_reminder_unpaid_renewal', 'type' => 'bool', 'label' => 'Nhắc gia hạn chưa thanh toán',
                'help' => 'Gửi nhắc riêng khi hợp đồng AI đã hết hạn + chưa thanh toán gia hạn.',
                'config' => 'ai_accounts.reminder.include_unpaid_renewal', 'default' => true, 'rules' => ['boolean']],

            // ── clm (Hợp đồng) ───────────────────────────────────────
            ['group' => 'clm', 'name' => 'alert_enabled', 'type' => 'bool', 'label' => 'Bật cảnh báo gia hạn',
                'help' => 'Tắt sẽ không quét và không tạo thông báo hợp đồng sắp hết hạn.',
                'config' => 'clm.alert_enabled', 'default' => true, 'rules' => ['boolean']],
            ['group' => 'clm', 'name' => 'renewal_alert_days', 'type' => 'string', 'label' => 'Mốc nhắc gia hạn (ngày)',
                'help' => 'Các mốc trước hạn để nhắc, phân tách bằng dấu phẩy (vd 90,60,30,7). Mốc lớn nhất cũng là cửa sổ "sắp hết hạn".',
                'config' => 'clm.renewal_alert_days', 'default' => '90,60,30,7',
                'rules' => ['required', 'string', 'regex:/^\s*\d{1,4}(\s*,\s*\d{1,4})*\s*$/']],
            ['group' => 'clm', 'name' => 'alert_telegram', 'type' => 'bool', 'label' => 'Gửi cảnh báo qua Telegram',
                'help' => 'Gửi kèm Telegram khi có hợp đồng sắp/đã hết hạn (cần bật Telegram ở tab Thông báo Telegram).',
                'config' => 'clm.alert_telegram', 'default' => false, 'rules' => ['boolean']],

            // ── ai (LLM báo cáo tuần) ────────────────────────────────
            ['group' => 'ai', 'name' => 'enabled', 'type' => 'bool', 'label' => 'Bật tổng hợp báo cáo tuần bằng AI',
                'help' => 'Khi bật và có API key, nút Tạo báo cáo / Tạo lại sẽ nhờ LLM viết lại tóm tắt điều hành. KPI vẫn tính từ dữ liệu Sprint. Tắt hoặc thiếu key → tổng hợp nội bộ (heuristic).',
                'config' => 'weekly_report.llm.enabled', 'default' => false, 'rules' => ['boolean']],
            ['group' => 'ai', 'name' => 'provider', 'type' => 'select', 'label' => 'Nhà cung cấp',
                'help' => 'OpenAI (GPT), Anthropic (Claude), Google Gemini, NVIDIA NIM, hoặc endpoint tương thích OpenAI (OpenRouter, Groq, Azure, Ollama).',
                'config' => 'weekly_report.llm.provider', 'default' => 'openai',
                'rules' => ['required', 'string', Rule::in(['openai', 'anthropic', 'gemini', 'nvidia', 'openai_compatible'])],
                'options' => [
                    ['value' => 'openai', 'label' => 'OpenAI (GPT)'],
                    ['value' => 'anthropic', 'label' => 'Anthropic (Claude)'],
                    ['value' => 'gemini', 'label' => 'Google Gemini'],
                    ['value' => 'nvidia', 'label' => 'NVIDIA NIM (Nemotron)'],
                    ['value' => 'openai_compatible', 'label' => 'Tương thích OpenAI (OpenRouter, Groq, Azure…)'],
                ]],
            ['group' => 'ai', 'name' => 'api_key', 'type' => 'secret', 'label' => 'API key',
                'help' => 'Khóa bí mật của nhà cung cấp. Không hiện lại sau khi lưu. Để trống nếu không muốn thay đổi.',
                'config' => 'weekly_report.llm.api_key', 'default' => null, 'rules' => ['nullable', 'string', 'max:500']],
            ['group' => 'ai', 'name' => 'model', 'type' => 'string', 'label' => 'Mô hình',
                'help' => 'Ví dụ: gpt-4o-mini, claude-3-5-haiku-latest, gemini-2.0-flash, nvidia/nemotron-3.5-lightning-30b-a3b. Phải khớp nhà cung cấp đã chọn.',
                'config' => 'weekly_report.llm.model', 'default' => 'gpt-4o-mini', 'rules' => ['required', 'string', 'max:120']],
            ['group' => 'ai', 'name' => 'base_url', 'type' => 'string', 'label' => 'Base URL (tuỳ chọn)',
                'help' => 'NVIDIA mặc định https://integrate.api.nvidia.com/v1. Bắt buộc với «Tương thích OpenAI». Để trống = URL mặc định của nhà cung cấp.',
                'config' => 'weekly_report.llm.base_url', 'default' => null, 'rules' => ['nullable', 'string', 'max:255']],
            ['group' => 'ai', 'name' => 'system_prompt', 'type' => 'textarea', 'label' => 'Prompt tùy chỉnh báo cáo tuần',
                'help' => 'Để trống = dùng prompt mặc định (tổng hợp kết quả nghiệp vụ cho cấp quản lý, không liệt kê task). Dán prompt riêng nếu muốn đổi giọng/nguyên tắc. Hệ thống luôn ép đầu ra JSON và gỡ markdown/kí hiệu AI trước khi lưu.',
                'config' => 'weekly_report.llm.system_prompt', 'default' => '',
                'rules' => ['nullable', 'string', 'max:20000']],

            // ── menu (Tùy chỉnh menu) ────────────────────────────────
            // Stores the group keys hidden system-wide from the sidebar. The UI
            // (MenuTab) renders a labelled toggle per group; only the key list is
            // persisted. Applied in Navigation::for() via config('va.menu_hidden_groups').
            ['group' => 'menu', 'name' => 'hidden_groups', 'type' => 'list', 'label' => 'Nhóm menu đang ẩn',
                'help' => 'Các nhóm menu bị ẩn khỏi thanh điều hướng cho toàn hệ thống.',
                'config' => 'va.menu_hidden_groups', 'default' => [], 'rules' => ['array'], 'itemRules' => ['string', 'max:64']],

            // ── onboarding (Chào mừng nhân viên) ────────────────────
            ['group' => 'onboarding', 'name' => 'welcome_enabled', 'type' => 'bool', 'label' => 'Bật màn hình chào mừng',
                'help' => 'Hiển thị màn hình chào mừng toàn màn hình cho tài khoản đăng nhập lần đầu (phòng ban, vai trò, đồng nghiệp).',
                'config' => 'va.onboarding_welcome_enabled', 'default' => true, 'rules' => ['boolean']],
        ];
    }

    /**
     * Scalar fields for a group, with the full namespaced key attached.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fields(string $group): array
    {
        $out = [];
        foreach (self::fieldDefs() as $def) {
            if ($def['group'] !== $group) {
                continue;
            }
            $def['key'] = "{$def['group']}.{$def['name']}";
            $def['secret'] = $def['type'] === 'secret';
            unset($def['rules'], $def['itemRules'], $def['config']);
            $out[] = $def;
        }

        return $out;
    }

    /**
     * full key → config() dot-path (scalar fields + the matrix).
     *
     * @return array<string, string>
     */
    public static function configMap(): array
    {
        $map = [];
        foreach (self::fieldDefs() as $def) {
            $map["{$def['group']}.{$def['name']}"] = $def['config'];
        }
        $map[self::MATRIX_KEY] = 'va_permissions.role_grants';

        return $map;
    }

    /**
     * full key → literal default (scalar fields + the matrix).
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $out = [];
        foreach (self::fieldDefs() as $def) {
            $out["{$def['group']}.{$def['name']}"] = $def['default'];
        }
        $out[self::MATRIX_KEY] = config('va_permissions.role_grants', []);

        return $out;
    }

    /** @return array<int, string> full keys of secret fields. */
    public static function secretKeys(): array
    {
        return array_values(array_map(
            fn (array $d) => "{$d['group']}.{$d['name']}",
            array_filter(self::fieldDefs(), fn (array $d) => $d['type'] === 'secret'),
        ));
    }

    public static function type(string $key): ?string
    {
        foreach (self::fieldDefs() as $def) {
            if ("{$def['group']}.{$def['name']}" === $key) {
                return $def['type'];
            }
        }

        return $key === self::MATRIX_KEY ? 'matrix' : null;
    }

    /**
     * Validation rules for an update, keyed by the request payload shape
     * (scalar groups use short field names; permissions uses "grants.*").
     *
     * @return array<string, mixed>
     */
    public static function rules(string $group): array
    {
        if ($group === 'permissions') {
            $rules = ['grants' => ['required', 'array']];
            foreach (self::EDITABLE_ROLES as $role) {
                $rules["grants.{$role}"] = ['nullable', 'array'];
                $rules["grants.{$role}.*"] = ['string', Rule::in(Permissions::permissionKeys())];
            }

            return $rules;
        }

        if ($group === 'menu') {
            return [
                'hidden_groups' => ['present', 'array'],
                'hidden_groups.*' => ['string', Rule::in(Navigation::toggleableGroupKeys())],
            ];
        }

        $rules = [];
        foreach (self::fieldDefs() as $def) {
            if ($def['group'] !== $group) {
                continue;
            }
            $rules[$def['name']] = $def['rules'];
            if (isset($def['itemRules'])) {
                $rules["{$def['name']}.*"] = $def['itemRules'];
            }
        }

        return $rules;
    }
}
