# Daily Report — Liên kết dự án

## Source of truth

| Field | Vai trò |
|-------|---------|
| **`projects` (JSON)** | **Nguồn chính** — danh sách dự án được tag trong báo cáo (multi-select UI). |
| **`project_id` (nullable int)** | **Legacy / denormalized** — ID dự án đầu tiên trong `projects`, dùng cho filter lịch sử báo cáo (`DailyReportController@index?project_id=`). |

Migration ghi chú: `database/migrations/2024_01_01_000008_create_daily_reports_table.php`.

## Đồng bộ

Mọi create/update qua Use Case phải gọi `ReportProjectSync::applyToPayload()` khi có `projects`:

- `CreateDailyReportUseCase`
- `UpdateDailyReportUseCase`

```php
$data = ReportProjectSync::applyToPayload($data);
```

## API / query

- **Đọc danh sách dự án của báo cáo:** dùng `$report->projects` (cast array).
- **Filter theo một dự án:** `where('project_id', $id)` — khớp với dự án *đầu tiên* trong multi-select.
- **Không** ghi `project_id` trực tiếp từ form mà không có `projects`.

## Model helpers

`DailyReport::primaryProjectId()` và `DailyReport::linkedProjectIds()` — xem `app/Domain/DailyReport/Models/DailyReport.php`.

## Tương lai (Option B)

Nếu bỏ `project_id`: migration backfill + đổi filter sang JSON (`whereJsonContains`) — chỉ khi product yêu cầu.

---

## Lịch sử báo cáo — Dashboard & Export (redesign 2026-06)

Trang `/daily-reports` ([History.vue](../resources/js/Pages/DailyReport/History.vue)) là dashboard quản trị. Chi tiết wiring: xem memory `daily-report-history-redesign`.

### Quyết định phạm vi (quan trọng)

Thẻ báo cáo chỉ **render dữ liệu hiện có** — KHÔNG đổi data model / form nhập. Báo cáo chỉ lưu 5 trường HTML tự do (`goals_today`, `progress_update`, `blockers`, `improvement_suggestions`, `plan_tomorrow`) + `projects` JSON (mỗi dự án có `tasks:[{id,title,status}]`). Vì vậy **không có** và **không hiển thị**: checklist hoàn thành per-item, Progress %, Deadline, Ưu tiên, loại báo cáo, kế hoạch tuần/tháng. Nhân sự **không có phòng ban** (`department_id` chỉ ở bảng `projects`) → chỉ dùng `employee.role_title`. "Checklist + trạng thái" thật duy nhất trên thẻ là task của dự án liên kết.

### Backend

- `DailyReportController@index` filters: `q`, `status`, `project_id`, `employee_ids[]` (multi, cap 100, vẫn nhận legacy `employee_id`), `grade`, `from`, `to`, `late`, `group`.
- `summary` trả thêm `completion_rate` + `trend{}` (±% mỗi chỉ số so với kỳ trước) + `period{}`. Cửa sổ trend: có `from`/`to` → kỳ liền trước cùng độ dài; nếu không → 30 ngày gần nhất vs 30 ngày trước đó.
- `DailyReportResource.employee` thêm `role_title`.

### Export Excel (toàn bộ kết quả lọc)

- `GET /daily-reports/export-data` (`exportData`, route `daily-reports.export-data`, đặt **trước** `/{report}`) dùng lại `historyReportsQuery` (khớp màn hình + giữ self-scoping member), `->limit(5000)`, trả `DailyReportResource::collection` (JSON).
- Composable client [useDailyReportHistoryExport.js](../resources/js/modules/daily-report/composables/useDailyReportHistoryExport.js) (async) dựng 7 sheet `xlsx-js-style`: Tổng quan · Đối soát ngày · Chi tiết công việc · Mục tiêu · Kế hoạch · Theo tháng · Theo thành viên. Không có chart gốc → bảng tổng hợp + conditional formatting.
