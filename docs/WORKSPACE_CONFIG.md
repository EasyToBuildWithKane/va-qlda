# WORKSPACE CONFIG — Cấu hình workspace theo phòng ban

> Module: hub **`/workspace-config`** liệt kê **workspace theo phòng ban** (profile keyed `department_code`).
> Nav: **Cấu hình workspace** (`settings_workspace`) — filter theo `workspace.hub.view` (không còn `superOnly`).
>
> Khác **`/settings`** (SYSTEM_CONFIG — nhận diện, auth, email, RBAC, CLM thresholds).

---

## 1. Mục tiêu

| Có | Không |
|----|-------|
| Hub danh sách workspace theo PB | Trộn với `/settings` system |
| Profile `workspace_profiles` lazy-ensure | Tenant / app riêng |
| Superadmin thấy / quản trị mọi PB | Lead PB CRUD tiêu chí (phase sau) |
| User HRM chỉ thấy đúng PB của mình | Clone template PB → PB / versioning |
| Domain con (evaluation, …) gắn `department_code` | Hard-code card chỉ trên FE |

**Domain live:** [Cấu hình tiêu chí đánh giá](EVALUATION_CONFIG.md) → `/workspace-config/evaluation` (scoped).

---

## 2. Kiến trúc

```
/workspace-config                      → Hub (danh sách PB)
/workspace-config/w/{departmentCode}   → Shell workspace PB + module catalog
/workspace-config/w/{code}/ensure      → POST kích hoạt profile (hub.manage)
/workspace-config/evaluation/*         → Evaluation (EVALUATION_CONFIG.md)
```

| Lớp | Path |
|-----|------|
| Catalog modules | `app/Support/WorkspaceConfig/WorkspaceConfigCatalog.php` |
| Scope | `WorkspaceScopeResolver` — `department_code` từ `employee.meta` |
| Provision | `WorkspaceProfileProvisioner` — lazy create/activate |
| Model | `App\Models\WorkspaceConfig\WorkspaceProfile` |
| Controllers | `WorkspaceConfigController`, `WorkspaceProfileController` |
| Routes | `routes/web/workspace-config.php` |
| Pages | `Pages/WorkspaceConfig/Hub.vue`, `Workspace/Show.vue`, `Evaluation/` |
| FE module | `modules/workspace-config/` |

Pattern: **MVC**.

---

## 3. Schema

Bảng `workspace_profiles` (`va_prd_workspace_profiles`):

| Cột | Ý nghĩa |
|-----|---------|
| `department_code` | Unique — khóa ổn định (cùng evaluation) |
| `department_name` | Denormalized |
| `local_department_id` | FK nullable → `departments` |
| `status` | `draft` \| `active` \| `archived` |
| `notes` | nullable |
| `created_by` | SystemAccount |
| SoftDeletes | |

Tiêu chí đánh giá vẫn ở `evaluation_criteria` (`scope` + `department_code`) — không FK bắt buộc tới profile.

---

## 4. Routes

| Method | URI | Name |
|--------|-----|------|
| GET | `/workspace-config` | `workspace.config.index` |
| GET | `/workspace-config/w/{departmentCode}` | `workspace.profiles.show` |
| POST | `/workspace-config/w/{departmentCode}/ensure` | `workspace.profiles.ensure` |

Child: xem `EVALUATION_CONFIG.md`. Transport: **Inertia**.

---

## 5. Phân quyền

| Key | Reserved? | Ai |
|-----|-----------|-----|
| `workspace.hub.view` | Không | admin (default), lead, member, viewer — xem hub/workspace PB mình |
| `workspace.hub.manage` | Có | chỉ super_admin — mọi PB + ensure |
| `workspace.evaluation.view` / `.manage` | Có | chỉ super_admin — CRUD tiêu chí |

**Scope:** `WorkspaceScopeResolver::canAccess($user, $departmentCode)`.

- Superadmin / `hub.manage`: mọi code.
- Còn lại: chỉ `ownDepartmentCode` từ `SystemAccount→employee→meta.department_code` (fallback `ProfileOrgRelations::departmentCode`).

Evaluation index: user không manage → chỉ `scope=general` **hoặc** `department_code = own`; query lệch PB → 403.

---

## 6. Frontend

| Path | Vai trò |
|------|---------|
| `Pages/WorkspaceConfig/Hub.vue` | KPI strip + tìm/lọc + lưới PB |
| `WorkspaceConfigSummaryBar.vue` | KPI tổng / active / draft / tiêu chí / missing |
| `WorkspaceProfileGrid.vue` | Card PB + Mở / Kích hoạt |
| `Pages/WorkspaceConfig/Workspace/Show.vue` | Module catalog theo PB |
| `WorkspaceConfigItemGrid.vue` | Thẻ module (live + planned) |

---

## 7. Checklist thêm domain module

1. Ability (+ reserved nếu cần) trong `PermissionCatalog` module `workspace`.
2. Entry `WorkspaceConfigCatalog::definition()` (`applies_to`, `href`, …).
3. Route group dưới `workspace-config/{slug}`.
4. Pages + enforce `WorkspaceScopeResolver` trên đọc/ghi.
5. Doc domain + cập nhật file này + `API_STRUCTURE` / `FRONTEND_STRUCTURE` / `DATABASE_STRUCTURE`.
