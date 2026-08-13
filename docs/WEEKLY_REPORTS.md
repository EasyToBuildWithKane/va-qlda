# Báo cáo tuần (Weekly Reports)

Tab **"Báo cáo tuần"** trên `/projects/{project}?tab=weekly` — Executive Weekly Dashboard
tự tổng hợp Sprint thành báo cáo quản trị. Người dùng kiểm tra → chỉnh sửa → gửi duyệt → duyệt.

Trên tab **Tổng quan** (`?tab=overview`), cùng `WeeklyReportWorkspace` được nhúng **full width ngay dưới
khối «Hồ sơ dự án»** (`embedded`) — chọn khoảng ngày / tạo / lưu giữ `tab=overview` (prop `activeTab` +
`tab` trên request redirect).

UI tab: chọn từ ngày–đến ngày → **KPI hoàn thành kỳ** (số hạng mục, story points, giờ công) → tóm tắt điều hành + giá trị giao được (`outcomes`) → 3 thẻ → rủi ro → lịch sử phiên bản.

LLM đọc **mô tả + ghi chú hoàn thành** của task, không chỉ tiêu đề.

## Engine (heuristic + LLM tuỳ chọn)

Hệ thống **luôn** tính KPI / rủi ro / phân loại phản hồi bằng engine heuristic (rule-based)
đọc trực tiếp dữ liệu Sprint. Phần văn bản (tóm tắt điều hành, nhận định, 6 thẻ) được
**viết lại bằng LLM** khi Super Admin bật AI và lưu API key tại **`/settings/ai`**.

Thiếu key, tắt AI, hoặc API lỗi → giữ bản heuristic (không chặn tạo báo cáo).

```
App\Support\WeeklyReport\Contracts\WeeklyReportGenerator
  └─ LlmWeeklyReportGenerator          ← binding mặc định (AppServiceProvider)
       ├─ HeuristicWeeklyReportGenerator  (KPI + draft tiếng Việt)
       └─ WeeklyReportLlmClient           (OpenAI / Anthropic / Gemini / NVIDIA NIM / OpenAI-compatible)
```

Cấu hình overlay `config('weekly_report.llm.*')` — secret `api_key` không gửi ra client.
Xem [`SYSTEM_CONFIG.md`](SYSTEM_CONFIG.md) tab **Trí tuệ nhân tạo**.

| Lớp engine (`app/Support/WeeklyReport/`) | Vai trò |
|---|---|
| `WeeklyReportDataCollector` | Gom Task/Worklog/Activity/Blocker/Feedback trong cửa sổ tuần |
| `WeeklyReportKpiBuilder` | KPI: tiến độ Sprint, **hoàn thành tuần**, story points, giờ công, overdue… |
| `WeeklyReportTaskFacts` | Đọc mô tả + ghi chú hoàn thành; việc done trong tuần; digest cho LLM |
| `WeeklyReportNarrator` | Sinh văn bản tiếng Việt; kết quả tuần kèm giá trị từ mô tả/ghi chú |
| `WeeklyReportRiskAssessor` | Rủi ro High/Medium/Low + nguyên nhân |
| `WeeklyReportFeedbackClassifier` | Phân loại phản hồi: Tích cực/Góp ý/Phàn nàn/Lỗi/Yêu cầu thay đổi |
| `WeeklyReportDataHasher` | Hash dữ liệu → phát hiện "dữ liệu Sprint đã đổi" |
| `SprintWeekResolver` | Kỳ mặc định (bucket 7 ngày T2–CN) khi chưa chọn khoảng ngày |
| `WeeklyReportLlmClient` | LLM đọc nội dung task rồi viết lại kết quả/outcomes; không bịa số liệu |
| `LlmWeeklyReportGenerator` | Ghép heuristic + LLM; fallback khi API lỗi |

## Kỳ báo cáo (khoảng ngày)

Người dùng chọn **Từ ngày → Đến ngày** khi tạo báo cáo (tối đa 31 ngày). Kỳ mặc định trên UI
là tuần T2–CN chứa hôm nay (`SprintWeekResolver::currentWeek`). Cùng khoảng ngày + Sprint
đã có báo cáo → mở lại / tạo lại nội dung, không nhân bản.

`week_number` vẫn lưu nội bộ (số thứ tự kỳ trong Sprint) cho mã `WR-…-Wn`; giao diện và
thông báo dùng nhãn khoảng ngày.

POST `store` nhận `week_start` + `week_end` (bắt buộc). `week_number` vẫn được chấp nhận
để tương thích (dẫn xuất bucket Sprint rồi tạo theo ngày).

### Nội dung chi tiết (task-centric + meta)

Mỗi dòng gắn thực thể cụ thể kèm meta khi có: **assignee · Epic · story points · ưu tiên · hạn · ngày hoàn thành**.

| Phần | Nguồn dữ liệu |
|---|---|
| **Tóm tắt điều hành** | Sprint + khoảng ngày, % tiến độ, số hoàn thành trong kỳ, giờ công, blocked / critical / overdue, rủi ro cao |
| **Nhận định** | Tín hiệu cụ thể (tên task quá hạn / bị chặn, test case, yêu cầu thay đổi) — không chỉ đếm số |
| **Kết quả thực hiện** | Task `done` trong **cửa sổ tuần** + mô tả/ghi chú hoàn thành (giá trị giao được); worklog; không đổ full Sprint khi tuần trống |
| **Outcomes (AI)** | `meta.outcomes`: title + 1 câu giá trị rút từ nội dung task |
| **Tình hình hiện tại** | `in_progress` / `in_review` / `blocked` (+ ƯT, hạn, assignee); test case (mức · task · phụ trách); quá hạn (+ số ngày); dòng tổng hợp trạng thái |
| **Kế hoạch tiếp theo** | Tháo chặn trước → tiếp tục/bắt đầu theo ưu tiên → test case còn mở → yêu cầu thay đổi → sắp tới hạn 7 ngày |
| **Rủi ro** | Test case theo severity + task gắn; quá hạn / bị chặn kèm tên mẫu; tổng hợp high/medium/low |
| **Phản hồi** | Đếm theo nhóm + tối đa 3 tiêu đề mẫu / nhóm; điểm TB nếu có |
| **Hoạt động** | Sự kiện tuần kèm `d/m H:i`, tối đa 10 dòng |

Sau khi sửa engine, bấm **Tạo lại** (hoặc **Dữ liệu đã đổi — Tạo lại**) trên báo cáo tuần để áp dụng.

## Luồng phê duyệt

```
draft → generated → edited → submitted → approved
                                   └────→ rejected → (sửa lại) → submitted
```

- `generate` tạo lại toàn bộ; `regenerate` giữ nội dung các thẻ người dùng đã sửa (`is_edited`).
- `approved` khoá nội dung (không cho sửa).
- Mỗi lần submit/approve/reject lưu một bản `weekly_report_versions` (xem lại ở panel "Lịch sử phiên bản").

## RBAC (module `weekly_report`)

| Ability | super_admin | admin | lead | member | viewer | PM dự án |
|---|---|---|---|---|---|---|
| view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| generate / update | ✓ | ✓ | ✓ | — | — | ✓ |
| submit | ✓ | ✓ | — | — | — | ✓ |
| approve | ✓ | ✓ | — | — | — | ✓ |
| export | ✓ | ✓ | ✓ | — | ✓ | ✓ |

Policy `WeeklyReportPolicy` = ma trận (`$account->allows('weekly_report.x')`) **OR** là PM dự án.
Chỉnh quyền ở `/settings` (tab phân quyền).

## Endpoints (`routes/web/projects.php`)

```
POST   /projects/{project}/weekly-reports                          store (tạo + generate; week_start + week_end)
PUT    /projects/{project}/weekly-reports/{weeklyReport}           update (lưu chỉnh sửa)
POST   /projects/{project}/weekly-reports/{weeklyReport}/generate  tạo lại toàn bộ
POST   /projects/{project}/weekly-reports/{weeklyReport}/regenerate tạo lại, giữ nội dung đã sửa
POST   .../submit · /approve · /reject                             chuyển trạng thái
GET    .../export/pdf · /export/docx                               xuất file
```

Tab nạp dữ liệu qua props của `ProjectController@show`: `weeklyReports` (sprint + `default_start`/`default_end` + danh sách báo cáo theo khoảng ngày)
luôn có; `weeklyReport` (chi tiết) nạp khi URL có `?wr={id}` (partial reload Inertia).

## Thông báo & Audit

- Notifications: `WeeklyReportSubmitted/Approved/Rejected` (+ Generated, RegenerationAvailable) gửi
  cho PM + thành viên dự án qua `NotificationDispatcher::weeklyReport*`.
- Audit: `weekly_report.submitted/approved/rejected` ghi vào sổ cái `security_audit_logs`
  (xem `/audit`), nhãn trong `AuditActionCatalog`.

## Export

Server-side: `barryvdh/laravel-dompdf` (PDF, Blade `resources/views/exports/weekly-report.blade.php`,
brand `#9A0036`) + `phpoffice/phpword` (DOCX). Dữ liệu chuẩn hoá bởi `WeeklyReportExportPresenter`.

## Cơ sở dữ liệu

- `weekly_reports` — unique DB `(project, sprint, week_number)`; tạo mới tìm theo khoảng ngày rồi gán `week_number` tuần tự. Chứa executive_summary, ai_summary,
  kpi_snapshot (json), meta (json: risk + feedback), data_hash, các mốc `*_at`/`*_by`.
- `weekly_report_sections` — 6 section (result/current/next/risk/feedback/activity); thẻ editable có `is_edited`.
- `weekly_report_versions` — snapshot mỗi lần chuyển trạng thái.

## Seed / kiểm thử

- `php artisan db:seed --class=WeeklyReportSeeder` — tạo báo cáo demo cho dự án đầu tiên có Sprint.
- Tests: `tests/Feature/WeeklyReportTest.php`, `tests/Unit/WeeklyReportEngineTest.php` (gồm LLM fake HTTP + fallback).
