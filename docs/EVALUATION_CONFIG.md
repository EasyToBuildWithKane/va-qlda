# EVALUATION CONFIG — Cấu hình đánh giá Workspace

> Phase 1: siêu admin cấu hình bộ quy tắc đánh giá **theo phòng ban** (2 engine: điểm cộng/trừ · phiếu tiêu chí).
> Đường dẫn: **`/workspace-config/evaluation`** · Nav: **Cấu hình workspace → Cấu hình đánh giá** (`superOnly`).
>
> Module cha: [`WORKSPACE_CONFIG.md`](WORKSPACE_CONFIG.md) (hub `/workspace-config`).
>
> **Không** tạo phiếu đánh giá thực tế / chấm điểm nhân sự trong phase này.

---

## 1. Mục tiêu

| Có | Không (phase sau) |
|----|-------------------|
| CRUD cấu hình theo `department_code` + hiệu lực ngày | Phiếu đánh giá kỳ (`evaluation_form`) |
| 2 engine: `point_system` · `scorecard` | Gán nhân sự / nhập điểm / chốt |
| Tiêu chí nhập thủ công trên form (không mẫu phiếu hệ thống) | Báo cáo kết quả |
| Danh mục phòng ban từ `employees.meta` + `departments` local | Đồng bộ live HRM `/departments` (chưa có API) |

---

## 2. Schema (`va_prd_*`)

| Bảng | Vai trò |
|------|---------|
| `evaluation_configs` | Cấu hình theo phòng ban + hiệu lực; SoftDeletes |
| `evaluation_criteria` | Tiêu chí thuộc một config |

Unique config: `(department_code, template_type, effective_from)`.

Enum: `App\Support\Enums\EvaluationTemplateType` — `point_system` | `scorecard` (loại engine, không còn catalog mẫu HCNS/CNTT).

---

## 3. Routes (`routes/web/workspace-config.php` — nhóm `evaluation`)

| Method | URI | Name |
|--------|-----|------|
| GET | `/workspace-config/evaluation` | `workspace.evaluation.index` |
| GET | `/workspace-config/evaluation/create` | `workspace.evaluation.create` |
| POST | `/workspace-config/evaluation` | `workspace.evaluation.store` |
| GET | `/workspace-config/evaluation/{evaluationConfig}` | `workspace.evaluation.show` |
| GET | `/workspace-config/evaluation/{evaluationConfig}/edit` | `workspace.evaluation.edit` |
| PUT | `/workspace-config/evaluation/{evaluationConfig}` | `workspace.evaluation.update` |
| DELETE | `/workspace-config/evaluation/{evaluationConfig}` | `workspace.evaluation.destroy` |
| POST/PUT/DELETE | `.../criteria[/{criterion}]` | `workspace.evaluation.criteria.*` |
| POST | `.../criteria/reorder` | `workspace.evaluation.criteria.reorder` |

Transport: **Inertia** (không REST `/api/admin`). Hub: `workspace.config.index` — xem `WORKSPACE_CONFIG.md`.

---

## 4. Phân quyền

Reserved (chỉ `super_admin` qua `Gate::before` + catalog):

- `workspace.evaluation.view`
- `workspace.evaluation.manage`

Policy: `EvaluationConfigPolicy`. Nav group `settings_workspace` trong `PROTECTED_GROUP_KEYS`.

---

## 5. Phòng ban

`App\Support\Evaluation\HrmDepartmentDirectory`:

1. Merge `Department::active()` (local).
2. Distinct `meta.department_code` / `department_name` từ nhân sự active.
3. Cache 24h (`evaluation.department_directory.v1`).

Khóa ổn định trên config: **`department_code`** (không phải numeric HRM id).

---

## 6. Frontend

| Path | Ghi chú |
|------|---------|
| `Pages/WorkspaceConfig/Evaluation/{Index,Create,Edit,Show}.vue` | AppLayout `#header` + PageHeader |
| `Index.vue` | KPI strip + datagrid: Lọc / **Cột** / Xuất; nhóm collapse theo **phòng ban**; nút Mở/Thu nhóm; **Thêm mới** trong PageHeader |
| `modules/evaluation/config/columns.js` | Cột bảng + `useVisibleColumns` |
| `modules/evaluation/composables/useEvaluationExport.js` | Xuất Excel trang hiện tại |
| `modules/evaluation/components/*` | SummaryBar, ConfigForm, CriteriaEditor |

**Index filters (query):** `q`, `department_code`, `template_type`, `status` (`active`\|`inactive`\|`effective`), `effective_from` / `effective_to` (overlap hiệu lực), `per_page`.

---

## 7. Audit

`SecurityAuditLogger::evaluation()` → `evaluation.config_*` / `criteria_*` (xem `AuditActionCatalog`).
