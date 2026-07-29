# EVALUATION CONFIG — Cấu hình đánh giá Workspace

> Phase 1: siêu admin cấu hình bộ quy tắc đánh giá **theo phòng ban** (mẫu điểm cộng/trừ HCNS + phiếu tiêu chí CNTT).
> Đường dẫn: **`/workspace-config/evaluation`** · Nav: **Cấu hình workspace → Cấu hình đánh giá** (`superOnly`).
>
> **Không** tạo phiếu đánh giá thực tế / chấm điểm nhân sự trong phase này.

---

## 1. Mục tiêu

| Có | Không (phase sau) |
|----|-------------------|
| CRUD cấu hình theo `department_code` + hiệu lực ngày | Phiếu đánh giá kỳ (`evaluation_form`) |
| 2 engine: `point_system` · `scorecard` | Gán nhân sự / nhập điểm / chốt |
| Mẫu phiếu hệ thống (seed HCNS, CNTT) + copy tiêu chí | Báo cáo kết quả |
| Danh mục phòng ban từ `employees.meta` + `departments` local | Đồng bộ live HRM `/departments` (chưa có API) |

---

## 2. Schema (`va_prd_*`)

| Bảng | Vai trò |
|------|---------|
| `evaluation_templates` | Mẫu chung (`is_system`) |
| `evaluation_template_criteria` | Catalog tiêu chí của mẫu (auto-fill) |
| `evaluation_configs` | Cấu hình theo phòng ban + hiệu lực; SoftDeletes |
| `evaluation_criteria` | Tiêu chí thuộc một config (bản sao có thể sửa) |

Unique config: `(department_code, template_type, effective_from)`.

Enum: `App\Support\Enums\EvaluationTemplateType` — `point_system` | `scorecard`.

---

## 3. Routes (`routes/web/evaluation.php`)

| Method | URI | Name |
|--------|-----|------|
| GET | `/workspace-config/evaluation` | `workspace.evaluation.index` |
| GET | `/workspace-config/evaluation/create` | `workspace.evaluation.create` |
| POST | `/workspace-config/evaluation` | `workspace.evaluation.store` |
| GET | `/workspace-config/evaluation/{evaluationConfig}` | `workspace.evaluation.show` |
| GET | `/workspace-config/evaluation/{evaluationConfig}/edit` | `workspace.evaluation.edit` |
| PUT | `/workspace-config/evaluation/{evaluationConfig}` | `workspace.evaluation.update` |
| DELETE | `/workspace-config/evaluation/{evaluationConfig}` | `workspace.evaluation.destroy` |
| POST | `.../apply-template` | `workspace.evaluation.apply-template` |
| POST/PUT/DELETE | `.../criteria[/{criterion}]` | `workspace.evaluation.criteria.*` |
| POST | `.../criteria/reorder` | `workspace.evaluation.criteria.reorder` |

Transport: **Inertia** (không REST `/api/admin`).

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
| `modules/evaluation/components/*` | SummaryBar, ConfigForm, CriteriaEditor |

---

## 7. Audit

`SecurityAuditLogger::evaluation()` → `evaluation.config_*` / `criteria_*` / `template_applied` (xem `AuditActionCatalog`).

---

## 8. Seed

`EvaluationTemplateSeeder` — gọi từ migration tạo bảng + `DatabaseSeeder`:

1. **Mẫu Điểm Cộng/Trừ HCNS** — A1–A13, B1–B6, C1–C6, D1–D4, E1–E2.
2. **Phiếu tiêu chí CNTT** — nhóm Thái độ / Năng lực chuyên môn (TCMS*, 94–99).
