# EVALUATION CONFIG — Cấu hình tiêu chí & mẫu đánh giá

> Danh mục tiêu chí đánh giá **chung** và **theo phòng ban** (siêu admin), cùng **danh sách mẫu đánh giá** gắn tiêu chí theo vị trí.
> Đường dẫn: **`/workspace-config/evaluation`** · **`/workspace-config/evaluation-templates`**
> Nav: **Cấu hình workspace** (`superOnly` / `workspace.hub.view`).
>
> Module cha: [`WORKSPACE_CONFIG.md`](WORKSPACE_CONFIG.md) (hub `/workspace-config` — workspace theo phòng ban).
>
> **Không** tạo phiếu đánh giá thực tế / chấm điểm nhân sự trong phase này (UI stub trên trang chi tiết mẫu).

**Phạm vi xem:** super_admin / `evaluation.*` — mọi PB. User chỉ có `workspace.hub.view` — tiêu chí `general` + `department_code` trùng HRM của mình; query lệch PB → 403. Mẫu đánh giá: mọi user có `hub.view` / `evaluation.view` đều xem được danh mục mẫu.

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
| POST | `/workspace-config/evaluation/import` | `workspace.evaluation.import` |
| GET | `/workspace-config/evaluation/{evaluationCriterion}` | `workspace.evaluation.show` |
| PUT | `/workspace-config/evaluation/{evaluationCriterion}` | `workspace.evaluation.update` |
| DELETE | `/workspace-config/evaluation/{evaluationCriterion}` | `workspace.evaluation.destroy` |

Transport: **Inertia**. Hub: `workspace.config.index` — xem `WORKSPACE_CONFIG.md`.

Tạo/sửa qua **modal** trên Index/Show (không trang Create/Edit riêng). Nhập hàng loạt từ Excel qua **modal Nhập** (`EvaluationImportModal.vue`, cùng quyền `workspace.evaluation.manage` với tạo thủ công).

`store` / `update` / `import` → `back()` + flash `success` (toast góc trên phải kiểu VA-HRM + âm 2 nốt qua `useToast` / `AppLayout` — flash được clear sau khi hiện để toast vẫn chạy khi lưu liên tiếp cùng message). `destroy` → redirect Index + flash.

**Import** (`EvaluationCriterionController@import` + `ImportEvaluationCriterionRequest`): body `{ rows: [...] }`, tối đa 200 dòng/lần; mỗi dòng validate mirror `StoreEvaluationCriterionRequest` (kèm kiểm tra `criteria_code` trùng nhau trong cùng lô). Toàn bộ lô chạy trong 1 `DB::transaction` — 1 dòng lỗi thì không dòng nào được tạo. Mỗi tiêu chí tạo ra được ghi audit `evaluation.criteria_created` riêng (không dùng action gộp) để lịch sử trên trang Show của từng tiêu chí nhất quán với tạo thủ công. Sau khi nhập xong, bắn 1 thông báo `NotificationType::SystemImport` duy nhất (không phải N thông báo).

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
| `Index.vue` | KPI strip + datagrid; **cột mức điểm động** (header `Mức 1…N`; ô = nhãn/mô tả/`+điểm`); cột max 150px, kéo ngang ẩn scrollbar; nhóm collapse theo PB/chung; tab loại lọc danh sách trong nhóm; action dropdown; modal tạo/sửa |
| `Show.vue` | Header gọn «Chi tiết tiêu chí»; hero (mã copy, badge phạm vi/loại/trạng thái, KPI, mô tả, người tạo + avatar); thang điểm; `EvaluationActivityTimeline` (diff rõ · lọc · phân trang 5) |
| `modules/evaluation/components/EvaluationCriterionFormModal.vue` | Modal `max-w-4xl`: phòng ban autocomplete (để trống = tiêu chí chung, chỉ siêu QT); toggle Chấm 0.5 / Hoạt động; mã `TCVA###` khoá/mở khoá; thang điểm động 2–10 mức (**không** còn field «Cách chấm điểm») |
| `modules/evaluation/components/EvaluationCriterionRowActions.vue` | Dropdown thao tác dòng (Xem / Sửa / Xoá) |
| `modules/evaluation/components/EvaluationActivityTimeline.vue` | Timeline audit: avatar, badge, diff from→to, lọc Tạo/Sửa/Xóa, phân trang client 5 mục/trang |
| `modules/evaluation/components/EvaluationScoreLevelCell.vue` | Ô mức Index: nhãn mức, mô tả ngắn, trọng số `+n` |
| `modules/evaluation/components/EvaluationCategoryTabs.vue` | Tab loại trong nhóm PB: ẩn scrollbar, kéo ngang bằng chuột, truncate label mobile |
| `modules/evaluation/components/DepartmentAutocomplete.vue` | Input autocomplete phòng ban (HRM directory) |
| `modules/evaluation/components/EvaluationSummaryBar.vue` | KPI tổng / chung / PB / hoạt động |
| `modules/evaluation/config/columns.js` | Cột tuỳ chọn (`useVisibleColumns`); mức điểm render động ngoài danh sách này |
| `modules/evaluation/composables/useEvaluationExport.js` | Xuất Excel/CSV — hỗ trợ tuỳ chọn `{ columns, scopeLabel }`; cột tuỳ chọn khớp `EVALUATION_TABLE_COLUMNS` |
| `modules/evaluation/composables/useEvaluationImport.js` | Nhập Excel: template, parse, validate, preview, payload — xem mục 8 |
| `modules/evaluation/components/EvaluationImportModal.vue` | Modal `max-w-7xl`, bố cục ngang: hướng dẫn + tham chiếu bên trái/phải, bảng xem trước có hàng con mở rộng để sửa thang điểm |
| `modules/evaluation/components/EvaluationExportModal.vue` | Modal tuỳ chọn xuất: phạm vi (theo lọc/toàn bộ), định dạng, checklist cột |

**Index filters (query):** `q`, `scope`, `department_code`, `category`, `status` (`active`\|`inactive`). Index luôn trả **toàn bộ** tiêu chí (không phân trang); nhóm PB + tab loại lọc trong nhóm.

---

## 8. Nhập Excel — quy ước file mẫu

Sheet duy nhất **"Nhap lieu"** (không có sheet "Huong dan" — hướng dẫn sử dụng nằm ở modal UI, không nằm trong file) + sheet **"Tham chieu"** (danh sách phòng ban/loại tiêu chí để tra cứu khi gõ).

Mỗi dòng có tối đa **5 bộ cột `Mức N` cố định** (N=1..5), mỗi bộ gồm `Mức N - Nhãn`, `Mức N - Mô tả`, `Mức N - Điểm`. Mức 1 và 2 bắt buộc (khớp `MIN_SCORE_LEVELS=2`); Mức 3–5 để trống nếu không dùng. Cần trên 5 mức → sửa bổ sung qua modal "Thêm tiêu chí"/"Sửa" sau khi nhập.

Cột `Phòng ban` nhận tên hoặc mã, khớp mờ qua `normalizeSearchKey` (bỏ dấu, lowercase) với danh sách `HrmDepartmentDirectory::all()`; để trống = tiêu chí chung (chỉ hợp lệ nếu tài khoản là siêu quản trị). Marker ẩn `VA_EVAL_IMPORT_V1` định vị dòng tiêu đề khi đọc lại, đọc bằng `!ref` matrix (không `sheet_to_json`) — cùng kỹ thuật với `useRiskImport.js`.

---

## 9. Audit

`SecurityAuditLogger::evaluation()` → `subject_type=evaluation_criterion` · actions `evaluation.criteria_created|updated|deleted` (xem `AuditActionCatalog`). Mỗi tiêu chí tạo qua **import** cũng ghi `criteria_created` riêng (không action gộp) — xem mục 3.

Meta audit gồm `criteria_code/name`, `score_summary` (chuỗi mức · điểm), và khi **cập nhật** thêm `changes[]` (`label` / `from` / `to`). Show hydrate `actor_avatar` qua `PublicMediaUrl` từ `actor.employee.avatar_path`.

---

## 10. Mẫu đánh giá (`evaluation_templates`)

| Method | URI | Name |
|--------|-----|------|
| GET | `/workspace-config/evaluation-templates` | `workspace.evaluation-templates.index` |
| GET | `…/create` | `workspace.evaluation-templates.create` |
| POST | `/workspace-config/evaluation-templates` | `workspace.evaluation-templates.store` |
| POST | `…/import` | `workspace.evaluation-templates.import` |
| GET/POST | `…/export-logs` | `workspace.evaluation-templates.export-logs` / `.store` |
| POST | `…/{id}/duplicate` | `workspace.evaluation-templates.duplicate` |
| GET/PUT/DELETE | `…/{evaluationTemplate}` | show / update / destroy |

**Schema:** `evaluation_templates` (`template_code` `MDG###`, `name`, `position_code`/`position_name` legacy, SoftDeletes) + pivot `evaluation_template_criteria` + `evaluation_template_targets` (multi chức danh `title` / cấp bậc `rank`) + `evaluation_template_custom_criteria` (tiêu chí gắn trực tiếp trên mẫu) + `evaluation_template_fields` (trường form phụ: text/textarea/number/select/date/checkbox) + `evaluation_template_export_logs`.

**Đối tượng áp dụng:** chọn **XOR** chức danh **hoặc** cấp bậc (không cả hai) qua `HrmJobCatalogDirectory` (HRM Public API `listJobTitles` / `listRanks`). Khi API ranks trống, suy cấp bậc từ chức danh: ưu tiên `rank_name` / `level_name` / nested `rank`, fallback `level` → `Cấp N` (`L{n}`). Tên cấp chỉ là số (`1`/`2`/…) được chuẩn hoá thành «Cấp N». Fallback thêm distinct từ `employees` meta. Legacy `position_*` vẫn được điền từ chức danh đầu tiên để tương thích cột Index/export. FormRequest reject khi gửi đồng thời `titles` + `ranks`.

**Frontend:** `Pages/WorkspaceConfig/EvaluationTemplates/{Index,Create,Show}.vue` + `modules/evaluation-template/`. **Thêm mẫu** → trang `Create` (zoom ~90%, form full page: thông tin+đối tượng [segment Chức danh|Cấp bậc] → tiêu chí [picker hẹp | danh sách rộng] → trường bổ sung). Form: picker danh mục tiêu chí, tiêu chí tuỳ chỉnh, `MultiCatalogSelect` theo mode XOR, editor trường bổ sung (gợi ý/hướng dẫn thu gọn). **Điểm yêu cầu** trên tiêu chí danh mục = chọn từ `score_levels` (mặc định «Đạt yêu cầu» nếu có, badge điểm mức kế bên); tiêu chí tuỳ chỉnh nhập tự do. **Trọng số** = select 10%…100% (tổng gợi ý 100%). Show: chips chức danh/cấp bậc, bảng tiêu chí, trường bổ sung, stub phiếu; sửa vẫn qua modal. **Một nút «Dữ liệu»** → `EvaluationTemplateDataModal` (tab Nhập | Xuất | Lịch sử export).

**Audit mẫu:** `SecurityAuditLogger::evaluationTemplate()` → `subject_type=evaluation_template` · `template_created|updated|deleted|duplicated|exported`.

**Import Excel:** marker `VA_EVAL_TPL_IMPORT_V1`, sheets `Nhap lieu` + `Tham chieu`; cột mã tiêu chí cách nhau bởi `;`.