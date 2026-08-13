# Daily Report — Liên kết dự án

> **Module đầy đủ:** luồng nghiệp vụ, routes, phân quyền, UI — xem [`DAILY_REPORT.md`](./DAILY_REPORT.md).  
> Tài liệu này tập trung **đồng bộ dự án**, filter `project_id` và **export lịch sử**.

## Source of truth

| Field | Vai trò |
|-------|---------|
| **`projects` (JSON)** | **Nguồn chính** — danh sách dự án được tag trong báo cáo (multi-select UI). |
| **`project_id` (nullable int)** | **Legacy / denormalized** — ID dự án **thật** đầu tiên trong `projects` (bỏ qua sentinel việc thường xuyên `id: -1`). Vẫn ghi để giữ tương thích báo cáo cũ, nhưng filter lịch sử **không còn dựa vào riêng cột này** (xem dưới). |

### Công việc thường xuyên (không gắn dự án)

- UI form tách **Dự án** và **Công việc thường xuyên**; lưu trữ vẫn nằm trong `projects` JSON với một phần tử ảo `{ "id": -1, "name": "Công việc thường xuyên", "tasks": [...] }` (chỉ xuất hiện khi có ít nhất một task).
- `ReportProjectSync::ROUTINE_PROJECT_ID` / `isLinkableProjectId()` — **không** map sang `project_id`, **không** sync sang bảng `tasks` (`SyncDailyReportSpawnedTasksUseCase` bỏ qua `project_id <= 0`).
- **Persist riêng:** `SyncDailyReportRoutineTasksUseCase` ghi vào `va_prd_routine_tasks` và thay id tạm / `_localKey` bằng UUID thật trong JSON. Trang quản lý: `/routine-tasks` (nhật ký list + modal form).
- Frontend: `resources/js/modules/daily-report/constants/routineWork.js`, `ProjectSelect.vue`; module UI: `modules/routine-task/`.

Migration ghi chú: `database/migrations/2024_01_01_000008_create_daily_reports_table.php` · routine: `2026_08_13_140000_create_routine_tasks_table.php`, `2026_08_13_160000_add_daily_log_fields_to_routine_tasks.php`, `2026_08_13_160100_create_routine_task_attachments_table.php`.

## Đồng bộ

Mọi create/update qua Use Case phải gọi `ReportProjectSync::applyToPayload()` khi có `projects` (gộp trùng `id` dự án / task trước khi lưu):

- `CreateDailyReportUseCase`
- `UpdateDailyReportUseCase`

```php
$data = ReportProjectSync::applyToPayload($data);
```

## API / query

- **Đọc danh sách dự án của báo cáo:** dùng `$report->projects` (cast array).
- **Filter theo một dự án** (`historyReportsQuery`): khớp **bất kỳ** dự án được tag trong báo cáo, không chỉ dự án đầu:

  ```php
  $query->where(function ($q) use ($projectId) {
      $q->whereJsonContains('projects', ['id' => (int) $projectId])
          ->orWhere('project_id', (int) $projectId); // bao báo cáo legacy có projects = []
  });
  ```

  > MySQL (production) dùng `JSON_CONTAINS` semantics "chứa một phần" → khớp object `{id}` bên trong phần tử đầy đủ. SQLite (test) so khớp nguyên phần tử nên chỉ trùng qua nhánh `orWhere('project_id')`; production không bị ảnh hưởng.
- **Không** ghi `project_id` trực tiếp từ form mà không có `projects`.

## Model helpers

`DailyReport::primaryProjectId()` và `DailyReport::linkedProjectIds()` — xem `app/Domain/DailyReport/Models/DailyReport.php`.

## Tương lai (Option B)

Filter **đã** chuyển sang `whereJsonContains('projects', …)` (phần đọc của Option B xong). Bước còn lại nếu muốn bỏ hẳn cột: migration drop `project_id` + bỏ `ReportProjectSync::applyToPayload` ghi legacy — chỉ khi product yêu cầu.

---

## Lịch sử báo cáo — Dashboard & Export (redesign 2026-06)

Trang `/daily-reports` ([History.vue](../resources/js/Pages/DailyReport/History.vue)) là dashboard quản trị. Chi tiết wiring: xem memory `daily-report-history-redesign`.

### Quyết định phạm vi (quan trọng)

Thẻ báo cáo chỉ **render dữ liệu hiện có** — KHÔNG đổi data model / form nhập. Báo cáo chỉ lưu 5 trường HTML tự do (`goals_today`, `progress_update`, `blockers`, `improvement_suggestions`, `plan_tomorrow`) + `projects` JSON (mỗi dự án có `tasks:[{id,title,status}]`). Vì vậy **không có** và **không hiển thị**: checklist hoàn thành per-item, Progress %, Deadline, Ưu tiên, loại báo cáo, kế hoạch tuần/tháng. Nhân sự **không có phòng ban** (`department_id` chỉ ở bảng `projects`) → chỉ dùng `employee.role_title`. "Checklist + trạng thái" thật duy nhất trên thẻ là task của dự án liên kết.

### Backend

- `DailyReportController@index` filters: `q`, `status`, `project_id` (JSON-contains, xem trên), `employee_ids[]` (multi, cap 100, vẫn nhận legacy `employee_id`), `grade`, `from`, `to`, `late`, `group`.
- **Self-scoping member:** nếu account role `member` mà **không có `employee_id`** → query trả rỗng (`whereRaw('1 = 0')`), không leak báo cáo có `employee_id IS NULL`.
- `summary` trả thêm `completion_rate` + `trend{}` (±% mỗi chỉ số so với kỳ trước) + `period{}`. Cửa sổ trend: có `from`/`to` → kỳ liền trước cùng độ dài; nếu không → 30 ngày gần nhất vs 30 ngày trước đó.
- `DailyReportResource.employee` thêm `role_title`.

### Export Excel (toàn bộ kết quả lọc)

- `GET /daily-reports/export-data` (`exportData`, route `daily-reports.export-data`, đặt **trước** `/{report}`) dùng lại `historyReportsQuery` (khớp màn hình + giữ self-scoping member), giới hạn `EXPORT_LIMIT = 5000`, trả `DailyReportResource::collection` (JSON) **kèm `meta: { total, limit, truncated }`** — `total` đếm trước khi cắt nên client biết bị cắt.
- Composable client [useDailyReportHistoryExport.js](../resources/js/modules/daily-report/composables/useDailyReportHistoryExport.js) (async) trả `{ filename, truncated, total, limit }`; dựng 7 sheet `xlsx-js-style`: Tổng quan · Đối soát ngày · Chi tiết công việc · Mục tiêu · Kế hoạch · Theo tháng · Theo thành viên. Không có chart gốc → bảng tổng hợp + conditional formatting.
- **Cảnh báo cắt:** khi `meta.truncated`, [History.vue](../resources/js/Pages/DailyReport/History.vue) toast cảnh báo "Chỉ xuất {limit}/{total}…" để người dùng thu hẹp bộ lọc, tránh xuất thiếu âm thầm.
