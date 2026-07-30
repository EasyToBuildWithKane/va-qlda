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
| Thang điểm: **nhãn 1–5** hoặc **điểm cộng / điểm trừ** (số) | Gán nhân sự / nhập điểm / chốt |
| Loại tiêu chí (chọn + thêm mới có confirm) | Báo cáo kết quả |
| Chấm 0.5 (chỉ kiểu nhãn 1–5) | |
| Đồng bộ phòng ban từ HRM `GET /org-units` | Đồng bộ live webhook `org_unit.changed` (cache 24h) |
| Mã tự động (khoá) / mở khoá sửa thủ công | |
| Lịch sử hoạt động từ `security_audit_logs` | |

Đã **gỡ** engine `point_system` / lớp `evaluation_configs` / mẫu phiếu hệ thống.

---

## 2. Schema (`va_prd_*`)

| Bảng | Vai trò |
|------|---------|
| `evaluation_criteria` | Tiêu chí standalone; SoftDeletes |

Enum: `App\Support\Enums\EvaluationCriterionScope` — `general` | `department`.
Enum: `App\Support\Enums\EvaluationScoringType` — `scale` (nhãn 1–5) | `points` (điểm cộng / trừ).

Cột chính: `scope`, `department_*` (nullable khi chung), `criteria_code` (unique toàn cục), `criteria_name`, `category`, `scoring_type`, `description`, `allow_half_score`, `point_bonus`, `point_penalty`, `score_1`…`score_5`, `sort_order`, `is_active`, `created_by`.

Migrations: `…130000_reshape_evaluation_criteria_catalog` → `2026_07_30_140000_add_scoring_type_to_evaluation_criteria`.

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
| `Index.vue` | KPI strip + datagrid; tab **Tiêu chí chung** / **Theo phòng ban**; nhóm collapse; modal tạo/sửa |
| `Show.vue` | Chi tiết tiêu chí + thang điểm + lịch sử audit |
| `modules/evaluation/components/EvaluationCriterionFormModal.vue` | Modal wide `max-w-5xl`: mã khoá/mở khoá; tab phạm vi; form trái + thang điểm phải (`scale` nhãn \| `points` số cộng/trừ); loại `+` có confirm |
| `modules/evaluation/components/EvaluationSummaryBar.vue` | KPI tổng / chung / PB / hoạt động |
| `modules/evaluation/config/columns.js` | Cột bảng + `useVisibleColumns` |
| `modules/evaluation/composables/useEvaluationExport.js` | Xuất Excel/CSV trang hiện tại |

**Index filters (query):** `q`, `scope`, `department_code`, `category`, `status` (`active`\|`inactive`), `per_page`.

---

## 7. Audit

`SecurityAuditLogger::evaluation()` → `subject_type=evaluation_criterion` · actions `evaluation.criteria_created|updated|deleted` (xem `AuditActionCatalog`).
