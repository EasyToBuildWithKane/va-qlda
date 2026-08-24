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
| Insights + ma trận phủ + bulk ensure + export | Mẫu thông báo workspace (planned) |
| Drawer xem nhanh, notes, archive/restore | |

**Domain live:** [Cấu hình tiêu chí đánh giá](EVALUATION_CONFIG.md) → `/workspace-config/evaluation` (scoped); [Mẫu đánh giá](EVALUATION_CONFIG.md#10-mẫu-đánh-giá-evaluation_templates) → `/workspace-config/evaluation-templates`; [Phiếu đánh giá](EVALUATION_CONFIG.md#11-phiếu-đánh-giá-evaluation_forms--mvp-quản-trị) → `/workspace-config/evaluation-forms`; **Trọng số báo cáo ngày** → `/workspace-config/daily-report-scoring` (xem [DAILY_REPORT.md](DAILY_REPORT.md) §3.3).

---

## 2. Kiến trúc

```
/workspace-config                      → Hub (command center)
/workspace-config/ensure-bulk          → POST bulk ensure
/workspace-config/w/{departmentCode}   → Shell workspace PB + checklist
/workspace-config/w/{code}             → PATCH notes / status
/workspace-config/w/{code}/ensure      → POST kích hoạt profile
/workspace-config/daily-report-scoring → Trọng số chấm BC ngày theo PB
/workspace-config/evaluation/*         → Evaluation (EVALUATION_CONFIG.md)
/workspace-config/evaluation-templates/* → Evaluation templates (EVALUATION_CONFIG.md §10)
```

| Lớp | Path |
|-----|------|
| Catalog modules | `app/Support/WorkspaceConfig/WorkspaceConfigCatalog.php` (+ onboard_steps) |
| Hub cards | `WorkspaceHubAssembler` — status profile + readiness + checklist |
| Insights / coverage | `WorkspaceHubInsights` |
| Scope | `WorkspaceScopeResolver` — PB của tôi: `meta.department_code` → pivot `department_member` → tên HRM / danh mục → `unit_code` (parent) |
| Provision | `WorkspaceProfileProvisioner` — ensure + ensureMany |
| Model | `App\Models\WorkspaceConfig\WorkspaceProfile`, `App\Models\DailyReport\DailyReportScoringConfig` |
| Controllers | `WorkspaceConfigController`, `WorkspaceProfileController`, `DailyReportScoringConfigController` |
| Routes | `routes/web/workspace-config.php` |
| Pages | `Pages/WorkspaceConfig/Hub.vue`, `Workspace/Show.vue`, `Evaluation/`, `DailyReportScoring/Edit.vue` |
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
| `enabled_nav_groups` | JSON nullable — allow-list key nhóm sidebar (`Navigation` toggleable). `null` = không giới hạn PB (chỉ còn lớp ẩn toàn cục `/settings/menu`) |
| `created_by` | SystemAccount |
| SoftDeletes | |

Tiêu chí đánh giá vẫn ở `evaluation_criteria` (`scope` + `department_code`) — không FK bắt buộc tới profile.  
Trọng số BC ngày: `daily_report_scoring_configs` (keyed `department_code`) — giữ 4 weight + `kaizen_bonus_max`; không đổi số chiều điểm.

### Menu sidebar — 2 lớp

| Lớp | Nguồn | Ai áp dụng |
|-----|--------|------------|
| Toàn hệ thống | `menu.hidden_groups` → `config('va.menu_hidden_groups')` (`/settings/menu`) | Mọi role |
| Theo phòng ban | `workspace_profiles.enabled_nav_groups` (shell PB) | User thường có PB + profile; **`super_admin` bypass** |
| Không PB / chưa profile | — | Chỉ lớp toàn cục |

**Đồng bộ hub module:** `WorkspaceNavModuleMap` — ẩn nhóm `daily` ⇒ ẩn `daily_report_scoring`; ẩn `performance` ⇒ ẩn `evaluation` / `evaluation_templates` / `evaluation_forms`. Áp dụng trong `WorkspaceConfigCatalog::forUser()`.

---

## 4. Routes

| Method | URI | Name |
|--------|-----|------|
| GET | `/workspace-config` | `workspace.config.index` |
| POST | `/workspace-config/ensure-bulk` | `workspace.profiles.ensure-bulk` |
| GET | `/workspace-config/w/{departmentCode}` | `workspace.profiles.show` |
| PATCH | `/workspace-config/w/{departmentCode}` | `workspace.profiles.update` |
| POST | `/workspace-config/w/{departmentCode}/ensure` | `workspace.profiles.ensure` |
| GET | `/workspace-config/daily-report-scoring` | `workspace.daily-report-scoring.edit` |
| PUT | `/workspace-config/daily-report-scoring` | `workspace.daily-report-scoring.update` |

Query hub: `include_archived=1` (chỉ `hub.manage`) — hiện profile `archived` trên lưới.

Child: xem `EVALUATION_CONFIG.md`. Transport: **Inertia**.

---

## 5. Phân quyền

| Key | Reserved? | Ai |
|-----|-----------|-----|
| `workspace.hub.view` | Không | admin (default), lead, member, viewer — xem hub/workspace PB mình |
| `workspace.hub.manage` | Có | chỉ super_admin — mọi PB + ensure/bulk + notes/archive |
| `workspace.evaluation.view` / `.manage` | Có | chỉ super_admin — CRUD tiêu chí |
| `workspace.daily_report_scoring.view` / `.manage` | Có | chỉ super_admin — xem/sửa trọng số BC ngày theo PB |

**Scope:** `WorkspaceScopeResolver::canAccess($user, $departmentCode)`.

---

## 6. Frontend

| Path | Vai trò |
|------|---------|
| `Pages/WorkspaceConfig/Hub.vue` | Header hiện **phòng ban của bạn**; toolbar **Lọc** / **Cột** / Xuất + lưới thẻ + bulk + drawer + phân trang (không KPI strip) |
| `WorkspaceInsightsBanner.vue` | Gợi ý vận hành (CTA bulk / lọc) |
| `WorkspaceProfileGrid.vue` + `WorkspaceProfileCard.vue` | Thẻ phẳng (nền slate, không border/gradient), vạch accent + số metric; nhãn **Phòng của bạn**; trường qua **Cột**; bấm thẻ = xem nhanh |
| `WorkspaceProfileDrawer.vue` | Xem nhanh + notes + archive/restore / kích hoạt |
| `useWorkspaceHubExport.js` | Excel Tong quan / Phong ban / Ma tran |
| `Pages/WorkspaceConfig/Workspace/Show.vue` | Header gọn (mã PB + Kích hoạt/Tiêu chí) · strip KPI · checklist · **Menu sidebar PB** (`WorkspaceNavMenuPanel`) · notes · phân trang module khi > 5 |
| `WorkspaceNavMenuPanel.vue` | Toggle `enabled_nav_groups` (hub.manage); «Hiện tất cả» = lưu `null` |
| `WorkspaceConfigItemGrid.vue` | Danh sách module phẳng (mô tả đầy đủ; trạng thái planned/dev/maintenance dạng chữ, không badge viền) |
| `WorkspaceNavModuleMap.php` | Map nav group → catalog module (đồng bộ ẩn toàn cục / PB) |

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
