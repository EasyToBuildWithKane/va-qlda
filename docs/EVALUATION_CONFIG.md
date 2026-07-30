# EVALUATION CONFIG — Cấu hình tiêu chí đánh giá

> Danh mục tiêu chí đánh giá **chung** và **theo phòng ban** (siêu admin).
> Đường dẫn: **`/workspace-config/evaluation`** · Nav: **Cấu hình workspace → Cấu hình tiêu chí đánh giá** (`superOnly`).
>
> Module cha: [`WORKSPACE_CONFIG.md`](WORKSPACE_CONFIG.md) (hub `/workspace-config` — workspace theo phòng ban).
>
> **Không** tạo phiếu đánh giá thực tế / chấm điểm nhân sự trong phase này.

**Phạm vi xem:** super_admin / `evaluation.*` — mọi PB. User chỉ có `workspace.hub.view` — tiêu chí `general` + `department_code` trùng HRM của mình; query lệch PB → 403.

---

## 1. Mục tiêu

| Có | Không (phase sau) |
|----|-------------------|
| CRUD tiêu chí standalone (chung \| theo phòng ban) | Phiếu đánh giá kỳ (`evaluation_form`) |
| Thang điểm đánh giá (`score_levels`: **2–10 mức**, mỗi mức = nhãn + trọng số, chọn 1 mức/kỳ) | Gán nhân sự / nhập điểm / chốt / tổng điểm tích lũy theo tháng |
| Loại tiêu chí (chọn + thêm mới có confirm) | Báo cáo kết quả |
| Chấm 0.5 | |
| Đồng bộ phòng ban từ HRM `GET /org-units` | Đồng bộ live webhook `org_unit.changed` (cache 24h) |
| Mã tự động dạng `TCVA001` (khoá) / mở khoá sửa thủ công | |
| Lịch sử hoạt động từ `security_audit_logs` | |

Đã **gỡ** engine `point_system` / lớp `evaluation_configs` / mẫu phiếu hệ thống / kiểu thang `scoring_type` (scale|points) / field UI **Cách chấm điểm** (`scoring_mode` scale|event + `event_points` / `event_max_per_period` — migration `2026_07_30_190000_drop_event_scoring…`). Mỗi tiêu chí chỉ còn **thang điểm `score_levels`** (chọn đúng 1 mức khi chấm kỳ — không chọn cách chấm trên form).

Seeder mẫu HCNS: `database/seeders/EvaluationCriterionHrRulesSeeder.php` (31 tiêu chí `TCVA-A1…`, phòng `HCNS`) — mỗi quy tắc seed thành thang 2 mức (Không ghi nhận / Ghi nhận = điểm).

---

## 2. Schema (`va_prd_*`)

| Bảng | Vai trò |
|------|---------|
| `evaluation_criteria` | Tiêu chí standalone; SoftDeletes |

Enum: `App\Support\Enums\EvaluationCriterionScope` — `general` | `department`.

**Phạm vi trên form:** field **Phòng ban** (autocomplete). Để trống → `scope=general` (chỉ `workspace.hub.manage` / siêu quản trị). Có mã PB → `scope=department`. Backend `prepareForValidation` tự suy ra `scope` từ `department_code`.

Cột chính: `scope`, `department_*` (nullable khi chung), `criteria_code` (unique toàn cục, gợi ý `TCVA###`), `criteria_name`, `category`, `description`, `allow_half_score`, `score_levels` (JSON: `[{code, label, description, weight}, …]`), `sort_order`, `is_active`, `created_by`.

Migrations: `…130000_reshape_evaluation_criteria_catalog` → `…140000_add_scoring_type…` → `…170000_reshape_evaluation_criteria_score_levels` (JSON thang điểm) → `…180000_add_scoring_mode…` (tạm) → `2026_07_30_190000_drop_event_scoring_from_evaluation_criteria` (gỡ event).

---

## 3. Routes (`routes/web/workspace-config.php` — nhóm `evaluation`)

| Method | URI | Name |
|--------|-----|------|
| GET | `/workspace-config/evaluation` | `workspace.evaluation.index` |
| POST | `/workspace-config/evaluation` | `workspace.evaluation.store` |
| GET | `/workspace-config/evaluation/{evaluationCriterion}` | `workspace.evaluation.show` |
| PUT | `/workspace-config/evaluation/{evaluationCriterion}` | `workspace.evaluation.update` |
| DELETE | `/workspace-config/evaluation/{evaluationCriterion}` | `workspace.evaluation.destroy` |

Transport: **Inertia**. Hub: `workspace.config.index` — xem `WORKSPACE_CONFIG.md`.

Tạo/sửa qua **modal** trên Index/Show (không trang Create/Edit riêng).

`store` / `update` → `back()` + flash `success` (toast góc trên phải kiểu VA-HRM + âm 2 nốt qua `useToast` / `AppLayout`). `destroy` → redirect Index + flash.

---

## 4. Phân quyền

Reserved (chỉ `super_admin` qua `Gate::before` + catalog):

- `workspace.evaluation.view`
- `workspace.evaluation.manage`

Đọc danh mục (scoped): `workspace.hub.view` (không reserved) — xem policy `EvaluationCriterionPolicy`.

Policy: `EvaluationCriterionPolicy`. Nav group `settings_workspace` filter theo `permission` (không `superOnly`).

---

## 5. Phòng ban

`App\Support\Evaluation\HrmDepartmentDirectory` (cache `evaluation.department_directory.v2`, 24h):

1. **HRM API** `GET /api/v1/org-units?type=department|unit` (ability `org:read`, cursor-paginate) — nguồn chính.
2. Merge `Department::active()` local (giữ `local_department_id` khi trùng mã).
3. Bổ sung distinct `meta.department_code` / `department_name` từ nhân sự active (fallback).

Yêu cầu env: `HRM_API_BASE_URL`, `HRM_API_TOKEN` (client có `org:read`). Token thiếu → chỉ local + meta nhân sự.

Khóa ổn định khi `scope=department`: **`department_code`**.

---

## 6. Frontend

| Path | Ghi chú |
|------|---------|
| `Pages/WorkspaceConfig/Evaluation/{Index,Show}.vue` | AppLayout `#header` + PageHeader |
| `Index.vue` | KPI strip + datagrid; nhóm collapse theo PB/chung; tab loại lọc danh sách trong nhóm (không dòng ngang theo loại); action dropdown; modal tạo/sửa |
| `Show.vue` | Chi tiết tiêu chí: meta ở PageHeader (mã · phạm vi · loại + trạng thái), stats/mô tả, thang điểm, lịch sử audit — không lặp tên/icon hero |
| `modules/evaluation/components/EvaluationCriterionFormModal.vue` | Modal `max-w-4xl`: phòng ban autocomplete (để trống = tiêu chí chung, chỉ siêu QT); toggle Chấm 0.5 / Hoạt động; mã `TCVA###` khoá/mở khoá; thang điểm động 2–10 mức (**không** còn field «Cách chấm điểm») |
| `modules/evaluation/components/EvaluationCriterionRowActions.vue` | Dropdown thao tác dòng (Xem / Sửa / Xoá) |
| `modules/evaluation/components/EvaluationCategoryTabs.vue` | Tab loại trong nhóm PB: ẩn scrollbar, kéo ngang bằng chuột, truncate label mobile |
| `modules/evaluation/components/DepartmentAutocomplete.vue` | Input autocomplete phòng ban (HRM directory) |
| `modules/evaluation/components/EvaluationSummaryBar.vue` | KPI tổng / chung / PB / hoạt động |
| `modules/evaluation/config/columns.js` | Cột bảng + `useVisibleColumns` |
| `modules/evaluation/composables/useEvaluationExport.js` | Xuất Excel/CSV trang hiện tại |

**Index filters (query):** `q`, `scope`, `department_code`, `category`, `status` (`active`\|`inactive`). Index luôn trả **toàn bộ** tiêu chí (không phân trang); nhóm PB + tab loại lọc trong nhóm.

---

## 7. Audit

`SecurityAuditLogger::evaluation()` → `subject_type=evaluation_criterion` · actions `evaluation.criteria_created|updated|deleted` (xem `AuditActionCatalog`).
