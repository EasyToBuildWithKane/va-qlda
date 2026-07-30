# WORKSPACE CONFIG — Cấu hình workspace

> Module lớn (super-admin): hub **`/workspace-config`** liệt kê các mục cấu hình nghiệp vụ workspace.
> Nav: **Cấu hình workspace** (`settings_workspace`, `superOnly`).
>
> Khác **`/settings`** (SYSTEM_CONFIG — nhận diện, auth, email, RBAC, CLM thresholds).

---

## 1. Mục tiêu

| Có | Không |
|----|-------|
| Hub danh mục mục cấu hình | Trộn với `/settings` system |
| Đăng ký item qua `WorkspaceConfigCatalog` | Hard-code card chỉ trên FE |
| Mỗi item = domain riêng (route + pages + policy) | Một controller khổng lồ cho mọi domain |

**Item hiện có:** [Cấu hình tiêu chí đánh giá](EVALUATION_CONFIG.md) → `/workspace-config/evaluation`.

**Mở rộng sau:** thêm phần tử trong `WorkspaceConfigCatalog::definition()`, route con trong `routes/web/workspace-config.php`, nav item trong `Navigation.php` nhóm `settings_workspace`, ability reserved trong `PermissionCatalog`.

---

## 2. Kiến trúc

```
/workspace-config                 → Hub (WorkspaceConfigController)
/workspace-config/evaluation/*    → Evaluation (EVALUATION_CONFIG.md)
/workspace-config/{domain}/*      → (tương lai)
```

| Lớp | Path |
|-----|------|
| Catalog | `app/Support/WorkspaceConfig/WorkspaceConfigCatalog.php` |
| Hub controller | `app/Http/Controllers/WorkspaceConfig/WorkspaceConfigController.php` |
| Routes | `routes/web/workspace-config.php` (`workspace.config.index`, `workspace.evaluation.*`) |
| Pages | `Pages/WorkspaceConfig/Hub.vue` + `Pages/WorkspaceConfig/{Domain}/` |
| FE module | `modules/workspace-config/` (grid hub) · domain con giữ `modules/{domain}/` |

Pattern: **MVC** (giống Settings / Evaluation).

---

## 3. Routes

| Method | URI | Name |
|--------|-----|------|
| GET | `/workspace-config` | `workspace.config.index` |

Child routes: xem doc domain (vd. `EVALUATION_CONFIG.md`).

Transport: **Inertia**.

---

## 4. Phân quyền

- Hub: chỉ hiện item mà user `allows(permission)` (hoặc `.manage` tương ứng). Rỗng → 403.
- Nav group `settings_workspace` trong `PROTECTED_GROUP_KEYS`, `superOnly`.
- Reserved keys theo domain (vd. `workspace.evaluation.view` / `.manage`).

---

## 5. Frontend

| Path | Vai trò |
|------|---------|
| `Pages/WorkspaceConfig/Hub.vue` | `#header` + PageHeader `system-config` + lưới mục |
| `modules/workspace-config/components/WorkspaceConfigItemGrid.vue` | Thẻ link tới từng domain |

---

## 6. Checklist thêm item mới

1. Ability reserved + label trong `PermissionCatalog` module `workspace`.
2. Entry trong `WorkspaceConfigCatalog::definition()` (`key`, `href`, `permission`, …).
3. Route group dưới `workspace-config/{slug}` trong `workspace-config.php`.
4. Pages `WorkspaceConfig/{Domain}/` + `modules/{domain}/` nếu cần.
5. Nav item trong `settings_workspace`.
6. Doc domain + cập nhật bảng item trong file này + `API_STRUCTURE` / `FRONTEND_STRUCTURE`.
