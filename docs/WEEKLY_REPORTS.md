# Báo cáo tuần (Weekly Reports)

Tab **"Báo cáo tuần"** trên `/projects/{project}?tab=weekly` — Executive Weekly Dashboard
tự tổng hợp công việc **theo khoảng ngày trên toàn dự án** (mọi Sprint + backlog), không kẹp
phạm vi Sprint đang chạy. Người dùng kiểm tra → chỉnh sửa → gửi duyệt → duyệt.

Trên tab **Tổng quan** (`?tab=overview`), cùng `WeeklyReportWorkspace` được nhúng **full width ngay dưới
khối «Hồ sơ dự án»** (`embedded`) — thao tác lưu / duyệt giữ `tab=overview` (prop `activeTab` +
`tab` trên request redirect). **Chưa có báo cáo** (`?wr` trống hoặc kỳ không có bản ghi): empty thuần
(không khoảng ngày, không nút Tạo báo cáo). Tạo báo cáo chỉ trên tab **Báo cáo tuần**.

UI tab: **một hàng toolbar** (desktop `lg:flex-nowrap`) — Từ ngày · Đến ngày · trạng thái · Gửi duyệt / Duyệt · Cập nhật · Xuất · Tạo lại — rồi 3 thẻ (kết quả / hiện tại / tiếp theo) → rủi ro → lịch sử phiên bản.
Tóm tắt điều hành, nhận định và KPI vẫn được engine sinh và đưa vào file xuất PDF/DOCX, không hiện trên tab.

LLM (và bản heuristic) đọc **mô tả + ghi chú hoàn thành** của task **và thành viên làm**
(assignee, assignees, người ghi giờ trong kỳ) — không chỉ tiêu đề.

## Engine (heuristic + LLM tuỳ chọn)

Hệ thống **luôn** tính KPI / rủi ro / phân loại phản hồi bằng engine heuristic (rule-based)
đọc dữ liệu kỳ (khoảng ngày). Phần văn bản (tóm tắt điều hành, nhận định, 6 thẻ) được
**viết lại bằng LLM** khi Super Admin bật AI và lưu API key tại **`/settings/ai`**.

Thiếu key, tắt AI, hoặc API lỗi → giữ bản heuristic (không chặn tạo báo cáo).
Thanh công cụ hiện nhãn **AI đã tổng hợp** / **Tổng hợp nội bộ** / **AI lỗi — bản nội bộ**.

Prompt viết báo cáo chỉnh tại **`/settings/ai`** (ô «Prompt tùy chỉnh báo cáo tuần»). Để trống = prompt mặc định: tổng hợp **kết quả nghiệp vụ** cho cấp quản lý, không liệt kê task kỹ thuật. Prompt tùy chỉnh vẫn bị ép đầu ra JSON để gắn vào 3 thẻ.

LLM **không** nhận bản draft heuristic (tránh copy danh sách task). Chỉ nhận KPI + dữ liệu thô (mô tả, ghi chú, thành viên, vướng mắc, phản hồi).

Văn bản LLM được gỡ markdown / thinking token / emoji (`WeeklyReportPlainText`) trước khi lưu và khi trả ra UI / PDF / DOCX — 3 thẻ không hiện `**`, `###`, `→`, `` ` ``.

Nút **Tạo lại** ghi đè toàn bộ thẻ (không giữ bản đã sửa) để prompt mới có hiệu lực.

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
| `WeeklyReportDataCollector` | Gom Task/Worklog/Activity/Blocker/Feedback **giao với khoảng ngày** (không lọc `sprint_id`) |
| `WeeklyReportKpiBuilder` | KPI: tiến độ kỳ, **hoàn thành trong kỳ**, story points, giờ công, overdue… |
| `WeeklyReportTaskFacts` | Đọc mô tả + ghi chú hoàn thành + thành viên làm; việc done trong kỳ; digest cho LLM |
| `WeeklyReportNarrator` | Sinh văn bản tiếng Việt; kết quả kỳ kèm giá trị và người làm |
| `WeeklyReportRiskAssessor` | Rủi ro High/Medium/Low + nguyên nhân |
| `WeeklyReportFeedbackClassifier` | Phân loại phản hồi: Tích cực/Góp ý/Phàn nàn/Lỗi/Yêu cầu thay đổi |
| `WeeklyReportDataHasher` | Hash dữ liệu → phát hiện "dữ liệu kỳ đã đổi" |
| `SprintWeekResolver` | Kỳ mặc định = tuần lịch T2–CN (`calendarWeek`); `weekByNumber` còn dùng bucket Sprint khi POST `week_number` |
| `WeeklyReportLlmClient` | LLM đọc nội dung task + thành viên rồi viết lại kết quả/outcomes; không bịa số liệu |
| `WeeklyReportPlainText` | Gỡ markdown, thinking token, emoji khỏi văn bản LLM trước khi lưu / xuất / hiển thị |
| `LlmWeeklyReportGenerator` | Ghép heuristic + LLM; fallback khi API lỗi |

## Kỳ báo cáo (khoảng ngày)

Người dùng chọn **Từ ngày → Đến ngày** (`FilterDatePicker`, hiển thị `dd/MM/yyyy`) khi tạo báo cáo (tối đa 31 ngày). Kỳ mặc định trên UI
là tuần T2–CN chứa hôm nay (`SprintWeekResolver::calendarWeek`) — **không** kẹp ngày bắt đầu/kết thúc Sprint.
Cùng khoảng ngày trên dự án đã có báo cáo → mở lại / tạo lại nội dung, không nhân bản (lookup theo
`project_id` + `week_start` + `week_end`, không theo Sprint).

Task được gom khi giao với kỳ: đang làm / review / bị chặn, hoàn thành hoặc cập nhật trong kỳ,
có hạn hoặc ngày bắt đầu trong kỳ, quá hạn, hoặc có giờ công trong kỳ — gồm việc **ngoài Sprint**
và việc thuộc Sprint khác.

`week_number` vẫn lưu nội bộ (số thứ tự kỳ) cho mã `WR-…-Wn`; giao diện và
thông báo dùng nhãn khoảng ngày.

POST `store` nhận `week_start` + `week_end` (bắt buộc). `week_number` vẫn được chấp nhận
để tương thích (dẫn xuất bucket Sprint rồi tạo theo ngày).

### Nội dung chi tiết (task-centric + meta)

Mỗi dòng gắn thực thể cụ thể kèm meta khi có: **thành viên làm · Epic · story points · ưu tiên · hạn · ngày hoàn thành**.

| Phần | Nguồn dữ liệu |
|---|---|
| **Tóm tắt điều hành** | Khoảng ngày, % tiến độ kỳ, số hoàn thành trong kỳ, giờ công, **thành viên tham gia**, blocked / critical / overdue, rủi ro cao |
| **Nhận định** | Tín hiệu cụ thể (tên task quá hạn / bị chặn, test case, yêu cầu thay đổi) — không chỉ đếm số |
| **Kết quả thực hiện** | Task `done` trong **cửa sổ ngày** + mô tả/ghi chú hoàn thành + người làm; worklog; không đổ full Sprint khi kỳ trống |
| **Outcomes (AI)** | `meta.outcomes`: title + 1 câu giá trị rút từ nội dung task (+ members) |
| **Tình hình hiện tại** | `in_progress` / `in_review` / `blocked` (+ ƯT, hạn, thành viên); test case (mức · task · phụ trách); quá hạn (+ số ngày); dòng tổng hợp trạng thái |
| **Kế hoạch tiếp theo** | Tháo chặn trước → tiếp tục/bắt đầu theo ưu tiên → test case còn mở → yêu cầu thay đổi → sắp tới hạn 7 ngày |
| **Rủi ro** | Test case theo severity + task gắn; quá hạn / bị chặn kèm tên mẫu và người phụ trách; tổng hợp high/medium/low |
| **Phản hồi** | Đếm theo nhóm + tối đa 3 tiêu đề mẫu / nhóm; điểm TB nếu có |
| **Hoạt động** | Sự kiện kỳ kèm `d/m H:i`, tối đa 10 dòng |

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

Tab nạp dữ liệu qua props của `ProjectController@show`: `weeklyReports` (kỳ mặc định lịch + danh sách báo cáo theo khoảng ngày của **cả dự án**)
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

- `weekly_reports` — unique DB `(project, sprint, week_number)`; tạo mới tìm theo khoảng ngày trên dự án rồi gán `week_number` tuần tự. Chứa executive_summary, ai_summary,
  kpi_snapshot (json), meta (json: risk + feedback), data_hash, các mốc `*_at`/`*_by`.
- `weekly_report_sections` — 6 section (result/current/next/risk/feedback/activity); thẻ editable có `is_edited`.
- `weekly_report_versions` — snapshot mỗi lần chuyển trạng thái.

## Seed / kiểm thử

- `php artisan db:seed --class=WeeklyReportSeeder` — tạo báo cáo demo cho dự án đầu tiên có Sprint.
- Tests: `tests/Feature/WeeklyReportTest.php`, `tests/Unit/WeeklyReportEngineTest.php` (gồm LLM fake HTTP + fallback).
