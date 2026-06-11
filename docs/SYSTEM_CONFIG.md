# SYSTEM CONFIG — Cấu hình hệ thống

> Module quản trị (admin-only) cho phép chỉnh cấu hình runtime mà không cần sửa `.env` / deploy lại.
> Đường dẫn: **`/settings`** · Nav: **Quản trị → Cấu hình hệ thống**.

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
                               config('va_permissions.role_grants')
                                   ↓
                               Code hiện hữu (TelegramService, routes,
                               Permissions::roleAllows) — KHÔNG đổi
```

**Thứ tự provider** (`config/app.php`): `SettingsServiceProvider` đặt **ngay sau `AppServiceProvider`** để boot **trước `RouteServiceProvider`** — vì `routes/web.php` đọc `va.password_login_enabled` lúc đăng ký route.

**Cache:** `SettingsRepository` cache override (`Cache::rememberForever('system_settings.overrides')`); `setMany()` tự `forget`. Bảng chưa tồn tại (lúc migrate) ⇒ trả `[]`, overlay bỏ qua.

---

## 3. Các nhóm cài đặt (tabs)

| Tab | Group key | Field | Kiểu | Overlay → config |
|-----|-----------|-------|------|------------------|
| **Chung** | `general` | `app_name` | string | `va.app_name` |
| | | `app_short_name` | string | `va.app_short_name` |
| | | `support_email` | string(email) | `va.support_email` |
| | | `app_version` | string | `va.app_version` |
| **Đăng nhập & Bảo mật** | `auth` | `password_login_enabled` | bool | `va.password_login_enabled` |
| | | `google_allowed_domains` | list | `va.google_allowed_domains` |
| **Thông báo Telegram** | `telegram` | `enabled` | bool | `telegram.enabled` |
| | | `bot_token` | **secret** | `telegram.bot_token` |
| | | `chat_id` | string | `telegram.chat_id` |
| | | `blocker_chat_id` | string | `telegram.blocker_chat_id` |
| | | `daily_report_review` | bool | `telegram.daily_report_review` |
| | | `blocker_resolved` | bool | `telegram.blocker_resolved` |
| **Phân quyền** | `permissions` | `role_grants` | matrix | `va_permissions.role_grants` |

`general.app_*` còn được chia sẻ ra Inertia qua prop `app` (`HandleInertiaRequests`) → `AppLayout` dùng cho ô thương hiệu (rail), tiêu đề, chân thanh bên.

---

## 4. Tab Phân quyền (editable matrix)

- **Catalog quyền** (`va_permissions.permissions`) giữ ở config — gắn với code, không tạo từ UI.
- **Grants** (`role_grants`) editable trong DB: admin bật/tắt quyền cho `lead` / `member` / `viewer`.
- **`admin` bị khóa**: UI hiển thị full quyền, disabled; backend ép `admin => ['*']` ở cả `normalizedGrants()` (controller) và `SettingsServiceProvider` (overlay) ⇒ không thể tự khóa quyền admin.
- `Permissions::roleAllows()` **không đổi** (vẫn đọc `config('va_permissions.role_grants')`); overlay cấp giá trị DB nên grant mới có hiệu lực ngay (vd. `notifications.manage`).
- Tab còn hiển thị **"Menu theo vai trò"** (read-only) suy từ `Navigation::for()` — biết mỗi role thấy menu gì.

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

---

## 8. Files

| Lớp | File |
|-----|------|
| DB | `database/migrations/*_create_system_settings_table.php`, `App\Models\SystemSetting` |
| Domain | `App\Support\Settings\SettingsSchema`, `App\Support\Settings\SettingsRepository` |
| Overlay | `App\Providers\SettingsServiceProvider` (đăng ký ở `config/app.php`) |
| Quyền | `App\Policies\SystemSettingPolicy` (map ở `AuthServiceProvider`) |
| HTTP | `App\Http\Controllers\Settings\SystemSettingController`, `App\Http\Requests\Settings\UpdateSettingsRequest`, routes `settings.*` |
| Shared | `App\Http\Middleware\HandleInertiaRequests` (prop `app`), `config/va.php` (defaults) |
| Vue | `resources/js/Pages/Settings/Index.vue` + `partials/{FieldsTab,PermissionsTab}.vue`, `resources/js/shared/ui/form/ToggleSwitch.vue` |
