# EVALUATION CONFIG — Cấu hình tiêu chí đánh giá

> Danh mục tiêu chí đánh giá **chung** và **theo phòng ban** (siêu admin).
> Đường dẫn: **`/workspace-config/evaluation`** · Nav: **Cấu hình workspace → Cấu hình tiêu chí đánh giá** (`superOnly`).
>
> Module cha: [`WORKSPACE_CONFIG.md`](WORKSPACE_CONFIG.md) (hub `/workspace-config`).
>
> **Không** tạo phiếu đánh giá thực tế / chấm điểm nhân sự trong phase này.

---

## 1. Mục tiêu

| Có | Không (phase sau) |
|----|-------------------|
| CRUD tiêu chí standalone (chung \| theo phòng ban) | Phiếu đánh giá kỳ (`evaluation_form`) |
| Thang điểm 1–5 + nhãn trên từng tiêu chí | Gán nhân sự / nhập điểm / chốt |
| Loại tiêu chí (autocomplete + thêm mới) | Báo cáo kết quả |
| Chấm điểm chính xác 0.5 (checkbox) | Đồng bộ live HRM `/departments` (chưa có API) |
| Lịch sử hoạt động từ `security_audit_logs` | |

Đã **gỡ** engine `point_system` / lớp `evaluation_configs` / mẫu phiếu hệ thống.

---

## 2. Schema (`va_prd_*`)

| Bảng | Vai trò |
|------|---------|
| `evaluation_criteria` | Tiêu chí standalone; SoftDeletes |

Enum: `App\Support\Enums\EvaluationCriterionScope` — `general` | `department`.

Cột chính: `scope`, `department_*` (nullable khi chung), `criteria_code` (unique toàn cục), `criteria_name`, `category`, `description`, `allow_half_score`, `score_1`…`score_5`, `sort_order`, `is_active`, `created_by`.

Migrations: `2026_07_29_160000_create_evaluation_config_tables` → `2026_07_30_120000_drop_evaluation_templates` → `2026_07_30_130000_reshape_evaluation_criteria_catalog`.

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

Policy: `EvaluationCriterionPolicy`. Nav group `settings_workspace` trong `PROTECTED_GROUP_KEYS`.

---

## 5. Phòng ban

`App\Support\Evaluation\HrmDepartmentDirectory`:

1. Merge `Department::active()` (local).
2. Distinct `meta.department_code` / `department_name` từ nhân sự active.
3. Cache 24h (`evaluation.department_directory.v1`).

Khóa ổn định khi `scope=department`: **`department_code`**.

---

## 6. Frontend

| Path | Ghi chú |
|------|---------|
| `Pages/WorkspaceConfig/Evaluation/{Index,Show}.vue` | AppLayout `#header` + PageHeader |
| `Index.vue` | KPI strip + datagrid; nhóm collapse **Tiêu chí chung** rồi từng PB; modal tạo/sửa |
| `Show.vue` | Chi tiết tiêu chí + thang điểm + lịch sử audit |
| `modules/evaluation/components/EvaluationCriterionFormModal.vue` | Modal wide 2 cột |
| `modules/evaluation/components/EvaluationSummaryBar.vue` | KPI tổng / chung / PB / hoạt động |
| `modules/evaluation/config/columns.js` | Cột bảng + `useVisibleColumns` |
| `modules/evaluation/composables/useEvaluationExport.js` | Xuất Excel/CSV trang hiện tại |

**Index filters (query):** `q`, `scope`, `department_code`, `category`, `status` (`active`\|`inactive`), `per_page`.

---

## 7. Audit

`SecurityAuditLogger::evaluation()` → `subject_type=evaluation_criterion` · actions `evaluation.criteria_created|updated|deleted` (xem `AuditActionCatalog`).
