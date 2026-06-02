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
