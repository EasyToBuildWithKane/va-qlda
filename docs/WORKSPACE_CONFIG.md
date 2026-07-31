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
| Insights + ma trận phủ + bulk ensure + export | CRUD thật chu kỳ / mẫu thông báo (planned) |
| Drawer xem nhanh, notes, archive/restore | |

**Domain live:** [Cấu hình tiêu chí đánh giá](EVALUATION_CONFIG.md) → `/workspace-config/evaluation` (scoped).

---

## 2. Kiến trúc

```
/workspace-config                      → Hub (command center)
/workspace-config/ensure-bulk          → POST bulk ensure
/workspace-config/w/{departmentCode}   → Shell workspace PB + checklist
/workspace-config/w/{code}             → PATCH notes / status
/workspace-config/w/{code}/ensure      → POST kích hoạt profile
/workspace-config/evaluation/*         → Evaluation (EVALUATION_CONFIG.md)
```

| Lớp | Path |
|-----|------|
| Catalog modules | `app/Support/WorkspaceConfig/WorkspaceConfigCatalog.php` (+ onboard_steps) |
| Hub cards | `WorkspaceHubAssembler` — status profile + readiness + checklist |
| Insights / coverage | `WorkspaceHubInsights` |
| Scope | `WorkspaceScopeResolver` — `department_code` từ `employee.meta` |
| Provision | `WorkspaceProfileProvisioner` — ensure + ensureMany |
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
| `notes` | nullable (max 2000) |
| `created_by` | SystemAccount |
| SoftDeletes | |

Tiêu chí đánh giá vẫn ở `evaluation_criteria` (`scope` + `department_code`) — không FK bắt buộc tới profile.

---

## 4. Routes

| Method | URI | Name |
|--------|-----|------|
| GET | `/workspace-config` | `workspace.config.index` |
| POST | `/workspace-config/ensure-bulk` | `workspace.profiles.ensure-bulk` |
| GET | `/workspace-config/w/{departmentCode}` | `workspace.profiles.show` |
| PATCH | `/workspace-config/w/{departmentCode}` | `workspace.profiles.update` |
| POST | `/workspace-config/w/{departmentCode}/ensure` | `workspace.profiles.ensure` |

Query hub: `include_archived=1` (chỉ `hub.manage`) — hiện profile `archived` trên lưới.

Child: xem `EVALUATION_CONFIG.md`. Transport: **Inertia**.

---

## 5. Phân quyền

| Key | Reserved? | Ai |
|-----|-----------|-----|
| `workspace.hub.view` | Không | admin (default), lead, member, viewer — xem hub/workspace PB mình |
| `workspace.hub.manage` | Có | chỉ super_admin — mọi PB + ensure/bulk + notes/archive |
| `workspace.evaluation.view` / `.manage` | Có | chỉ super_admin — CRUD tiêu chí |

**Scope:** `WorkspaceScopeResolver::canAccess($user, $departmentCode)`.

---

## 6. Frontend

| Path | Vai trò |
|------|---------|
| `Pages/WorkspaceConfig/Hub.vue` | KPI + insights + ma trận + chọn nhiều + bulk + export + drawer + lưới/list + density + phân trang |
| `WorkspaceConfigSummaryBar.vue` | KPI tổng / active / chưa kích hoạt / sẵn sàng / đang cấu hình / tiêu chí |
| `WorkspaceInsightsBanner.vue` | Gợi ý vận hành (CTA bulk / lọc) |
| `WorkspaceCoverageMatrix.vue` | Ma trận PB × module live |
| `WorkspaceProfileGrid.vue` + `WorkspaceProfileCard.vue` | Card đa tone, select, compact, preview |
| `WorkspaceProfileDrawer.vue` | Xem nhanh + notes + archive/restore |
| `useWorkspaceHubExport.js` | Excel Tong quan / Phong ban / Ma tran |
| `Pages/WorkspaceConfig/Workspace/Show.vue` | Strip sẵn sàng + checklist onboard + notes + archive |
| `WorkspaceConfigItemGrid.vue` | Thẻ module (live + planned) + đã/chưa cấu hình |

### Trạng thái trên thẻ

| Badge | Ý nghĩa |
|-------|---------|
| **Chưa kích hoạt** (`status=missing`) | Chưa có bản ghi `workspace_profiles` (hoặc chỉ còn bản archived khi không `include_archived`) |
| **Đang dùng / Nháp / Lưu trữ** | Profile đã ensure |
| **Đã sẵn sàng / Đang cấu hình / Chưa có nội dung** | Readiness theo module live (vd. evaluation: `criteria_count > 0`) |

---

## 7. Checklist thêm domain module

1. Ability (+ reserved nếu cần) trong `PermissionCatalog` module `workspace`.
2. Entry `WorkspaceConfigCatalog::definition()` (`applies_to`, `href`, `onboard_steps`, `empty_cta` / `configured_cta`).
3. Route group dưới `workspace-config/{slug}`.
4. Pages + enforce `WorkspaceScopeResolver` trên đọc/ghi.
5. Doc domain + cập nhật file này + `API_STRUCTURE` / `FRONTEND_STRUCTURE` / `DATABASE_STRUCTURE`.
