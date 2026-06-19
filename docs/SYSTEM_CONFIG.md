# SYSTEM CONFIG — Cấu hình hệ thống

> Module quản trị (**super-admin-only**) cho phép chỉnh cấu hình runtime mà không cần sửa `.env` / deploy lại.
> Đường dẫn: **`/settings`** · Nav: **Cấu hình hệ thống** (chỉ Super Admin thấy).
>
> ⚠️ Từ bản nâng cấp RBAC: chỉ vai trò **`super_admin`** truy cập `/settings` (gồm tab Phân quyền & Tài khoản). `admin` **không** còn vào được. Chi tiết ma trận phân quyền & vai trò xem **[PERMISSIONS.md](PERMISSIONS.md)**.

---

## 1. Mục tiêu

Trước đây mọi cấu hình (Telegram, đăng nhập Google, ma trận phân quyền) nằm rải trong `config/*.php` + `.env`, chỉ đổi được khi sửa file và deploy. Module này gom chúng về một nơi, lưu **override** vào DB và **overlay lên `config()` lúc boot** — nên **không phải sửa nơi đọc config**, code cũ tự nhận giá trị mới.

Nguyên tắc:

- **Schema là single source of truth** (`App\Support\Settings\SettingsSchema`): khai báo field, kiểu, default, validation, và `config()` path để overlay.
- **DB chỉ lưu override.** Bảng trống ⇒ app vẫn chạy đúng bằng default (đọc từ config/env).
- **Admin-only.** Policy chặn ở backend; nav chỉ hiện cho admin.
- **An toàn:** secret (bot token) không gửi ra client; vai trò `admin` luôn full quyền (không thể tự khóa).

---

## 2. Kiến trúc & luồng

```
Giao diện (Vue)                Tính năng (Laravel)                 Phân quyền
─────────────                  ──────────────────                  ──────────
Pages/Settings/Index.vue       SystemSettingController             SystemSettingPolicy
  ├─ partials/FieldsTab.vue  →   ├─ index()  → render payload        viewAny / manage = admin
  │   (general/auth/telegram)    └─ update() → DB::transaction       (map ở AuthServiceProvider)
  └─ partials/PermissionsTab     ↓                                   ↑
      (ma trận role×quyền)     SettingsRepository (singleton)      UpdateSettingsRequest
                                 ├─ get / all / overrides            authorize() + rules()
                                 └─ setMany() → cache forget         (rules từ SettingsSchema)
                                   ↓
                               system_settings (DB, chỉ override)
                                   ↓ (boot)
                               SettingsServiceProvider
                                 overlay overrides → config()
                                   ↓
                               config('telegram.*'), config('va.*'),
                               config('task_email.*'), config('ai_accounts.reminder.*'),
                               config('va_permissions.role_grants')
                                   ↓
                               Code hiện hữu (TelegramService, routes,
                               Permissions::roleAllows) — KHÔNG đổi
```

**Thứ tự provider** (`config/app.php`): `SettingsServiceProvider` đặt **ngay sau `AppServiceProvider`** để boot **trước `RouteServiceProvider`** — vì `routes/web/auth.php` đọc `va.password_login_enabled` lúc đăng ký route.

**Cache:** `SettingsRepository` cache override (`Cache::rememberForever('system_settings.overrides')`); `setMany()` tự `forget`. Bảng chưa tồn tại (lúc migrate) ⇒ trả `[]`, overlay bỏ qua.

---

## 3. Các nhóm cài đặt (tabs)

| Tab | Group key | Field | Kiểu | Overlay → config |
|-----|-----------|-------|------|------------------|
| **Chung** | `general` | `app_name` | string | `va.app_name` |
| | | `app_short_name` | string | `va.app_short_name` |
| | | `support_email` | string(email) | `va.support_email` |
| | | `app_version` | string | `va.app_version` |
| | | `congnghe_proposal_email` | string(email) | `va.congnghe_proposal_email` |
| | | `dashboard_personnel_pattern` | string | `va.dashboard_personnel_department_pattern` |
| **Đăng nhập & Bảo mật** | `auth` | `password_login_enabled` | bool | `va.password_login_enabled` |
| | | `google_allowed_domains` | list | `va.google_allowed_domains` |
| | | `google_allowed_emails` | list | `va.google_allowed_emails` |
| | | `tech_login_allowed_emails` | list | `va.tech_login_allowed_emails` |
| **Thông báo Telegram** | `telegram` | `enabled` | bool | `telegram.enabled` |
| | | `bot_token` | **secret** | `telegram.bot_token` |
| | | `chat_id` | string | `telegram.chat_id` |
| | | `blocker_chat_id` | string | `telegram.blocker_chat_id` |
| | | `daily_report_review` | bool | `telegram.daily_report_review` |
| | | `blocker_resolved` | bool | `telegram.blocker_resolved` |
| **Email & Thông báo** | `email` | `enabled` | bool | `task_email.enabled` |
| | | `from_name` | string | `task_email.from_name` |
| | | `notify_on_assign` | bool | `task_email.notify_on_assign` |
| | | `notify_daily_at` | string (HH:MM) | `task_email.notify_daily_at` |
| | | `ai_reminder_enabled` | bool | `ai_accounts.reminder.send_email` |
| | | `ai_reminder_extra_emails` | list | `ai_accounts.reminder.extra_recipients` |
| | | `ai_reminder_include_expired` | bool | `ai_accounts.reminder.include_expired` |
| | | `ai_reminder_unpaid_renewal` | bool | `ai_accounts.reminder.include_unpaid_renewal` |
| | | *(mẫu DB)* | `email_templates` | `task_assigned`, `daily_summary`, `sprint_summary`, `congnghe_proposal_submitted`, `congnghe_proposal_rejected` — chỉnh tại `/settings/email` |
| **Hợp đồng (CLM)** | `clm` | `alert_enabled` | bool | `clm.alert_enabled` |
| | | `renewal_alert_days` | string | `clm.renewal_alert_days` |
| | | `alert_telegram` | bool | `clm.alert_telegram` |
| **Phân quyền** | `permissions` | `role_grants` | matrix | `va_permissions.role_grants` |
| **Tài khoản & Vai trò** | `accounts` | — | (runtime) | gán `system_accounts.role` (PUT `/settings/accounts/{id}/role`) |

`general.app_*` còn được chia sẻ ra Inertia qua prop `app` (`HandleInertiaRequests`) → `AppLayout` dùng cho ô thương hiệu (rail), tiêu đề, chân thanh bên.

---

## 4. Tab Phân quyền (RBAC matrix) — xem [PERMISSIONS.md](PERMISSIONS.md)

- **Catalog quyền** = `App\Support\Auth\PermissionCatalog` (module → abilities), gắn với code, không tạo từ UI. UI nhóm theo module, cột = vai trò.
- **Grants** (`role_grants`) editable trong DB: **super_admin** bật/tắt quyền cho `admin` / `lead` / `member` / `viewer`.
- **`super_admin` bị khóa**: UI hiển thị full quyền, disabled; backend ép `super_admin => ['*']` ở cả `normalizedGrants()` (controller) và `SettingsServiceProvider` (overlay) ⇒ không tự khóa được.
- **Reserved keys** (cấu hình hệ thống, ma trận, gán role) bị **strip** khỏi mọi role ≠ super_admin ở cả controller lẫn overlay.
- `Permissions::roleAllows()` đọc `config('va_permissions.role_grants')` đã overlay (hierarchy `*` → `{module}.*` → exact). Policy gọi `$account->allows()` → ma trận điều khiển **thật** quyền edit/delete.
- Tab **Tài khoản & Vai trò** (`accounts`): super_admin gán role runtime (`PUT /settings/accounts/{id}/role`), không hạ được Super Admin cuối cùng.
- Tab còn hiển thị **"Menu theo vai trò"** (read-only) suy từ `Navigation::for()`.

---

## 5. Bảng `va_prd_system_settings`

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| key | varchar(255) | NO | Unique, namespaced `{group}.{name}` (vd. `telegram.enabled`); matrix dùng `permissions.role_grants` |
| value | longtext | YES | JSON-encoded (scalar / list / matrix) |
| updated_by | bigint UNSIGNED | YES | FK `system_accounts`, `nullOnDelete` |
| created_at / updated_at | timestamp | YES | |

Chỉ lưu **những key đã được admin sửa** (override). Decode/typing do `SettingsRepository` đảm nhiệm theo schema.

---

## 6. Bảo mật

- **Secret** (`bot_token`): `index()` gửi `value=''` + cờ `has_value`; ô nhập để trống ⇒ controller **giữ token cũ** (không ghi đè bằng rỗng). Token không bao giờ lộ ra props.
- **Phân quyền**: route admin-only (policy trong controller + nav lọc theo role). FormRequest `authorize()` chặn non-admin.
- **Không khóa admin**: xem mục 4.

---

## 7. Thêm một setting mới

1. Khai báo field trong `SettingsSchema::fieldDefs()` (group, name, type, label, help, `config` path, default, rules).
2. Nếu cần overlay: `config` path đã tự vào `configMap()` ⇒ `SettingsServiceProvider` overlay tự động.
3. Nếu cần dùng ở frontend toàn cục: thêm vào prop `app` trong `HandleInertiaRequests`.
4. UI tự render (FieldsTab theo `type`). Type mới ⇒ thêm nhánh trong `FieldsTab.vue`.
5. Không cần migration (key mới chỉ là row override khi admin lưu).
6. Thêm key mới vào `ENV_BACKED_KEYS` trong `SettingsImportFromEnv` nếu có `.env` fallback tương ứng.

## 8. Bootstrap settings lên server mới

Khi deploy lên server mới (bảng `system_settings` trống), chạy:

```bash
# Xem trước — không ghi gì
php artisan settings:import-from-env --dry-run

# Seed từ .env hiện tại (bỏ qua key đã có trong DB)
php artisan settings:import-from-env

# Overwrite toàn bộ (kể cả key đã có trong DB)
php artisan settings:import-from-env --force
```

Sau khi seed xong, các key `TELEGRAM_*`, `GOOGLE_ALLOWED_*`, `AI_ACCOUNT_REMINDER_*` có thể xóa khỏi `.env` — giá trị sẽ đọc từ DB. Khi đổi cấu hình, vào `/settings` thay vì sửa file.

---

## 9. Files

| Lớp | File |
|-----|------|
| DB | `database/migrations/*_create_system_settings_table.php`, `App\Models\SystemSetting` |
| Domain | `App\Support\Settings\SettingsSchema`, `App\Support\Settings\SettingsRepository` |
| Overlay | `App\Providers\SettingsServiceProvider` (đăng ký ở `config/app.php`) |
| Quyền | `App\Policies\SystemSettingPolicy` (map ở `AuthServiceProvider`) |
| HTTP | `App\Http\Controllers\Settings\SystemSettingController`, `App\Http\Requests\Settings\UpdateSettingsRequest`, routes `settings.*` |
| Shared | `App\Http\Middleware\HandleInertiaRequests` (prop `app`), `config/va.php` (defaults) |
| Vue | `resources/js/Pages/Settings/Index.vue` + `partials/{FieldsTab,PermissionsTab,EmailTemplateTab}.vue` |
| CLI | `App\Console\Commands\SettingsImportFromEnv` (`settings:import-from-env`) |
