# PERMISSIONS — Phân quyền RBAC

> Hệ thống phân quyền dựa trên **ma trận vai trò × quyền** (RBAC) điều khiển thật sự các thao tác xem/tạo/sửa/xóa/… của mọi module.
> Cấu hình tại **`/settings`** tab **Phân quyền** & **Tài khoản & Vai trò** (chỉ **Super Admin**).

---

## 1. Vai trò

| Role | Mô tả | Cấu hình `/settings`? |
|------|-------|-----------------------|
| `super_admin` | Toàn quyền tuyệt đối (god-mode). **Độc quyền** chỉnh cấu hình hệ thống, ma trận phân quyền, gán vai trò, thao tác nguy hiểm. | ✅ (duy nhất) |
| `admin` | Full nghiệp vụ (mặc định: mọi quyền **trừ** reserved). **Không** vào `/settings`. | ❌ |
| `lead` | Quản lý nhóm: dự án, duyệt báo cáo, hợp đồng, NCC, KB… | ❌ |
| `member` | Tạo báo cáo/đề xuất/test case/KB của mình; thao tác trên bản ghi sở hữu. | ❌ |
| `viewer` | Chỉ xem (dashboard, hiệu suất, hợp đồng, dự án…). | ❌ |

`SystemRole` ([app/Support/Enums/SystemRole.php](../app/Support/Enums/SystemRole.php)) — cột `system_accounts.role` là string, **không cần migration** khi thêm role.

---

## 2. Kiến trúc

```
PermissionCatalog (module → abilities, reserved, default grants)
        ▼
config('va_permissions.role_grants')  ←overlay DB→  /settings (Phân quyền)
        ▼
SystemAccount::allows('module.action')  →  Permissions::roleAllows()
        │   hierarchy: '*'  →  '{module}.*'  →  exact key
        ├── Gate::before: super_admin ⇒ luôn true (AuthServiceProvider)
        ▼
Policy: nhánh role = $account->allows('x.action')  OR  nhánh ownership/entity (giữ nguyên)
        ▼
Frontend: auth.user.permissions[] + is_super_admin → usePermission().can('x.action')
```

**Nguyên tắc bất biến:** quyền cuối = **(matrix-grant) OR (ownership/entity-grant)**. Ma trận cấp quyền “toàn cục”; người dùng vẫn luôn sửa được bản ghi của chính mình (project manager/member, người tạo test case, chủ credential, người tạo phiếu AI…).

### Phân cấp grant
- `*` — mọi quyền (chỉ `super_admin`).
- `{module}.*` — mọi ability trong module (vd `contract.*`).
- `{module}.{action}` — đúng một ability.
- ⚠️ `{module}.manage` **không** phải wildcard — nó là một ability cụ thể (vd `ProjectPolicy::manage` = sprint/thành viên), **không** ngụ ý `delete`. Nút “Bật/Tắt toàn module” trên UI chỉ tick tất cả ability (không lưu wildcard).

---

## 3. Catalog quyền

Nguồn sự thật: [app/Support/Auth/PermissionCatalog.php](../app/Support/Auth/PermissionCatalog.php).

- `modules()` — module → `{label, icon, group, abilities}` (key = `{module}.{ability}`).
- Hành động chuẩn: `view, create, update, delete, review(Duyệt), import, export, assign, manage` + quyền nghiệp vụ đặc thù (vd `credential.view_password`, `ai_account.manage_password_viewers`, `contract.import`, `daily_report.review`…).
- `defaultGrants()` — grant mặc định mỗi role, **mirror hành vi cũ** để không hồi quy.
- `reservedKeys()` — chỉ `super_admin`: `system.settings.view/manage`, `permissions.manage`, `roles.assign`, `workspace.hub.manage`, `workspace.evaluation.view/manage`, `workspace.daily_report_scoring.view/manage`. Bị **strip** khỏi mọi role khác cả khi lưu ([SystemSettingController](../app/Http/Controllers/Settings/SystemSettingController.php)) lẫn overlay ([SettingsServiceProvider](../app/Providers/SettingsServiceProvider.php)).

### Hardening cố ý (khác hành vi cũ)
- `ai_account.create/update/delete/renew`: trước đây **mọi** tài khoản đăng nhập đều CRUD được tài khoản AI (policy trả `true`). Mặc định mới chỉ cấp cho `admin`/`lead`. Super Admin có thể nới lại qua ma trận nếu cần.

---

## 4. Thêm quyền / module mới

1. Khai báo ability trong `PermissionCatalog::modules()` (`{module}.{ability}` + label).
2. Trong Policy: nhánh role dùng `$account->allows('module.ability')`; giữ nhánh ownership/entity.
3. Cập nhật `defaultGrants()` cho các role cần.
4. (Reserved) thêm key vào `PermissionCatalog::RESERVED` nếu chỉ super được giữ.
5. Frontend gate: `usePermission().can('module.ability')` (hoặc prop `can` từ Resource).
6. UI ma trận tự render theo catalog — không cần sửa.

---

## 5. Gán vai trò (Tài khoản & Vai trò)

- Tab `/settings/accounts` (super-admin-only) → đổi `role` tài khoản runtime, route `PUT /settings/accounts/{account}/role` ([SystemAccountRoleController](../app/Http/Controllers/Settings/SystemAccountRoleController.php)).
- Guard: **không** hạ quyền Super Admin **cuối cùng** (tránh khóa cứng cấu hình).
- Bootstrap ban đầu: `config/va_permissions.php['bootstrap_accounts']` (đặt ≥1 `super_admin`) → `php artisan va:bootstrap-admins`.

---

## 6. Lưu ý vận hành

- ⚠️ Trước khi deploy bản này: đảm bảo có **ít nhất 1 `super_admin`** (bootstrap_accounts) — nếu không sẽ **không ai** vào được `/settings`.
- `admin` mất quyền `/settings` là **chủ ý** — thông báo cho team.
- `Permissions::roleAllows()` đọc `config()` đã overlay — **không sửa nơi check**, chỉ chỉnh ma trận ở UI.

## 7. Files

| Lớp | File |
|-----|------|
| Enum | `App\Support\Enums\SystemRole` |
| Catalog | `App\Support\Auth\PermissionCatalog` |
| Check | `App\Support\Auth\Permissions`, `App\Models\SystemAccount::allows/isSuperAdmin/isAdminTier` |
| Gate | `App\Providers\AuthServiceProvider` (Gate::before god-mode) |
| Policy | `app/Policies/*` (18 policy đọc matrix + ownership) |
| Overlay | `App\Providers\SettingsServiceProvider`, `App\Support\Settings\SettingsSchema` |
| HTTP | `SystemSettingController`, `SystemAccountRoleController`, `AssignAccountRoleRequest` |
| Nav | `App\Support\Navigation` (super-as-admin superset, group `settings` superOnly) |
| Shared | `HandleInertiaRequests` (prop `auth.user.permissions`) |
| Vue | `Pages/Settings/partials/{PermissionsTab,AccountsTab}.vue`, `shared/composables/usePermission.js` |
| Test | `tests/Feature/Auth/PermissionMatrixTest.php`, `tests/Feature/Settings/SystemSettingTest.php`, `tests/Unit/BootstrapAdminRolesTest.php` |
