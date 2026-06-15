# COACHING / MENTORING — Module Đào tạo & Mentoring

> Module **độc lập** với Knowledge Base / Blog — lưu khóa học, buổi học, tài liệu, bài tập, tiến độ và dashboard tài chính.
> **Trạng thái:** ✅ Triển khai v1 (2026-06-14) — migrations, CRUD, dashboard, lịch & danh sách buổi. Chi tiết route/UI bên dưới khớp `routes/web.php`.

---

## 1. Mục tiêu & phạm vi

| Mục tiêu | Mô tả |
|---|---|
| Quản lý khóa học | Coaching cá nhân / doanh nghiệp: học viên, coach, học phí, lịch |
| Buổi học | Lịch từng buổi, nội dung, trạng thái, giờ dạy |
| Tài liệu đa dạng | Canva, Google Docs, slide, video, file đính kèm |
| Bài tập | Deadline, ưu tiên, nộp file / GitHub |
| Theo dõi học tập | Đã xem / đang học / hoàn thành, % tiến độ khóa |
| Dashboard | KPI tháng, doanh thu, giờ dạy, biểu đồ Chart.js |

**Ngoài phạm vi v1:** thanh toán online, calendar sync Google/Outlook, chứng chỉ PDF.

---

## 2. Kiến trúc

- **CRUD, materials, assignments, lịch:** MVC — `Http/Controllers/Coaching/*` → Model / FormRequest / `CoachingCoursePolicy` / `CoachingSessionResource`.
- **Aggregation tài chính & KPI:** `app/Support/Coaching/` — `CoachingFinancialSummary`, `CoachingStudentMetrics`, `CoachingSessionIndexQuery`, `CoachingSessionScope`, `SafeEmbedUrl` (không có `app/Application/Coaching/`).
- **Tài khoản chỉ Coaching:** `App\Support\Auth\CoachingOnlyAccess` + middleware `RestrictCoachingOnlyUsers` — email trong `config/va.php` → `google_allowed_emails`; nav chỉ nhóm Coaching.

```
routes/web.php (prefix coaching.)
    → CoachingDashboardController (__invoke)
    → CoachingCourseController (courses + storeSession)
    → CoachingSessionController (index, schedule, calendar JSON, CRUD session, materials, assignments, progress)

resources/js/
    → Pages/Coaching/Dashboard.vue
    → Pages/Coaching/Courses/Index.vue, Show.vue, Edit.vue
    → Pages/Coaching/Sessions/Index.vue, Schedule.vue, Show.vue
    → modules/coaching/components/*, composables/useCoachingCalendar.js
    → composables/useCoachingExport.js, useCoachingSessionList.js, coachingSessionDisplay.js
```

**Charts:** Chart.js + vue-chartjs (đã có trong dự án).

**Editor nội dung buổi học:** TipTap (`KbRichTextField`) cho `content` (HTML). Toolbar **Sheet** / **Docs** chèn block embed Google Workspace (`preview?rm=minimal`) — cùng pattern preview link Google Docs/Sheets như tài liệu dự án (`GoogleWorkspaceUrl` / `ProjectDocumentsPanel`).

---

## 3. Phân quyền

Module dùng **vai trò mở rộng** (lưu trên `system_accounts` hoặc pivot — thiết kế chi tiết khi implement). Ánh xạ logic với yêu cầu nghiệp vụ:

| Vai trò nghiệp vụ | Map gợi ý VA-QLDA | Xem | Tạo | Sửa | Xóa | Xuất báo cáo |
|---|---|---|---|---|---|---|
| Super Admin | `admin` | ✅ | ✅ | ✅ | ✅ | ✅ |
| Coach | `lead` + flag `is_coach` hoặc role mới | Khóa của mình + được gán | ✅ | ✅ | ✅ (giới hạn) | ✅ (phạm vi mình) |
| Mentor | `lead` / `member` + flag | Tương tự coach (read-only finance tùy policy) | Hạn chế | Hạn chế | ❌ | ❌ |
| Student | `member` / học viên (`employee`) | Khóa được gán | ❌ | Bài tập của mình | ❌ | ❌ |

**v1 đơn giản hóa:** `admin` full; `lead` = coach (mọi khóa); `member` = student (chỉ khóa có `student_id` = employee của account); `viewer` = không vào module.

**Khách Google (whitelist):** email trong `va.google_allowed_emails` → chỉ thấy nav Coaching, home `/coaching` (`CoachingOnlyAccess`).

Quyền xuất: Excel styled qua composable `useCoachingExport.js` — **dashboard** (`exportCoachingMonthlyWorkbook`): sheet *Hướng dẫn*, *Tổng quan*, *Chi tiết tháng*, *Theo ngày*, *Theo tuần*, *12 tháng*, *Tiến độ khóa* (dữ liệu `dailySeries`, `weeklySeries`, `revenueSeries` từ `CoachingFinancialSummary`); danh sách buổi — CSV/Excel client qua `GET coaching.sessions.export` (JSON ≤500 dòng, kèm tài liệu/bài tập chi tiết) + `useCoachingSessionList` / `useCoachingExport` (workbook: Tổng quan, Tóm tắt tháng, Theo tháng, Theo ngày, Danh sách, Theo khóa).

---

## 4. Quản lý khóa học

### 4.1 Trường dữ liệu

| Trường | Ghi chú |
|---|---|
| Tên khóa học | `name` |
| Mô tả | `description` (text) |
| Mục tiêu | `objectives` (text) |
| Học viên | `student_id` → `employees` (v1: một học viên chính; v2: pivot nhiều học viên) |
| Tên học viên / coach (text) | `student_name`, `coach_name` — khi không map employee (guest Google) |
| Coach | `coach_id` → `employees` |
| Trạng thái | `planning` \| `active` \| `completed` \| `cancelled` |
| Ngày bắt đầu / kết thúc | `start_date`, `end_date` |
| Học phí tổng | `total_fee` (VNĐ, decimal) |
| Giá theo giờ | `hourly_rate` (VNĐ/giờ) |
| Mã khóa | `code` auto `COACH-001` |
| Tổng giờ kế hoạch | `total_hours` (optional, có thể derive từ sessions) |

### 4.2 Luồng trạng thái khóa

```
planning → active → completed
              ↘ cancelled
```

---

## 5. Quản lý buổi học

Mỗi khóa có nhiều buổi (`coaching_sessions`), `session_number` unique per course.

| Trường | Ghi chú |
|---|---|
| Tên buổi học | `title` |
| Số thứ tự | `session_number` |
| Ngày học | `date` |
| Giờ bắt đầu / kết thúc | `start_time`, `end_time` (time) |
| Tổng số giờ | `total_hours` — auto từ giờ hoặc nhập tay |
| Chủ đề | `topic` |
| Nội dung chi tiết | `content` (HTML) |
| Ghi chú | `notes` |

### 5.1 Trạng thái buổi học

| Status | Nhãn VI |
|---|---|
| `pending` | Chưa học |
| `in_progress` | Đang học |
| `completed` | Hoàn thành |
| `cancelled` | Hủy |

**Tiến độ khóa (%):**

```
progress_pct = round(100 * completed_sessions / total_sessions)
```

Ví dụ: Laravel 30 buổi, 18 `completed` → 60%.

---

## 6. Tài liệu buổi học

Bảng `coaching_session_materials`, cột `type`:

| type | Nguồn | UI |
|---|---|---|
| `canva` | Link Canva | Embed preview (iframe nếu policy cho phép) + link mở tab |
| `google_docs` | Link Google Docs | Embed / preview link |
| `pdf` | Upload hoặc URL | Viewer inline / tải |
| `pptx` | Upload | Tải; preview optional |
| `youtube` | URL | Embed iframe |
| `loom` | URL | Embed |
| `gdrive` | Link Drive | Embed preview |
| `file` | Upload | PDF, DOCX, XLSX, ZIP — lưu `path` + `mime_type` |

| Cột | Mô tả |
|---|---|
| `title` | Tên hiển thị |
| `url` | Link ngoài |
| `path` | File local `public` disk |
| `sort_order` | Thứ tự trong buổi |

**Host URL được phép** (`SafeEmbedUrl`): YouTube (`youtube.com`, `youtu.be`), Loom, Canva (`canva.com`, `canva.link`), Google Docs / Drive. Link rút gọn `canva.link` được lưu và mở tab; embed iframe ưu tiên URL dạng `canva.com/design/…`.

---

## 7. Bài tập

Bảng `coaching_assignments` gắn `session_id`.

| Trường | Ghi chú |
|---|---|
| Tiêu đề / mô tả | `title`, `description` |
| Deadline | `deadline` (datetime) |
| Độ ưu tiên | `high` \| `medium` \| `low` |
| Trạng thái | `todo` \| `doing` \| `review` \| `done` — UI tab **Bài tập** dùng checklist `todo` ↔ `done` |
| File nộp | `submission_path` |
| Link GitHub | `github_url` |
| Ghi chú | `notes` — **bắt buộc** khi đánh dấu `done` |

**UI (`Sessions/Show` → tab Bài tập):** `CoachingSessionAssignmentsTab.vue` — thanh tiến độ, checklist từng mục. Coach (`manageAssignments`) giao bài không cần bật «Chỉnh sửa» toàn trang. Học viên / coach (`completeAssignments`) tick hoàn thành → nhập «Nội dung hoàn thành» → `PATCH /coaching/assignments/{id}`. Tick lại để mở lại (`status=todo`).

**UI (`Sessions/Show` → tab Tài liệu):** `CoachingSessionMaterialsTab.vue` — danh sách gọn bên trái, xem trước một mục bên phải; form «Thêm tài liệu» khi bật «Chỉnh sửa» (flash server sau `POST …/materials`, không toast trùng).

---

## 8. Theo dõi học tập

Bảng `coaching_progress` — một bản ghi / `(course_id, session_id, system_account_id)`:

| Cờ | Ý nghĩa |
|---|---|
| `is_viewed` | Đã xem bài giảng / tài liệu buổi |
| `is_in_progress` | Đang học buổi này |
| `is_completed` | Hoàn thành buổi (đồng bộ khi session → `completed` hoặc manual) |

**UI:** Trang `Sessions/Show` không còn sidebar «Trạng thái buổi» / «Theo dõi học tập»; cập nhật trạng thái buổi qua danh sách & lịch. API `POST /coaching/progress` vẫn giữ cho tích hợp sau.

**% tiến độ:** ưu tiên đếm `session.status = completed`; fallback đếm `is_completed` trên progress nếu chưa có session status.

---

## 9. Dashboard

### 9.1 Thống kê theo tháng (filter `YYYY-MM`)

| KPI | Công thức |
|---|---|
| Tổng số buổi học | Count sessions trong tháng (theo `date`) |
| Tổng số giờ dạy | Sum `total_hours` sessions `completed` trong tháng |
| Tổng số học viên | Distinct `student_id` khóa có buổi trong tháng |
| Tổng doanh thu | Sum phân bổ học phí theo buổi hoàn thành (xem §13) |
| Số buổi hoàn thành | Count `status = completed` |
| Số buổi hủy | Count `status = cancelled` |

### 9.2 Thống kê tài chính (tháng)

| KPI | Công thức |
|---|---|
| Tổng doanh thu | `revenue_month` |
| Tổng giờ giảng dạy | `hours_month` |
| Giá trung bình / giờ | `revenue_month / hours_month` (nếu hours > 0) |
| Giá trung bình / buổi | `revenue_month / completed_sessions` |

Ví dụ tháng 06/2026: 20 buổi, 48 giờ, 24.000.000 VNĐ → 500.000 VNĐ/giờ, 1.200.000 VNĐ/buổi.

### 9.3 Dashboard tổng quan (widget)

- Tổng khóa học (active / all)
- Tổng học viên (distinct)
- Tổng buổi học / tổng giờ đào tạo (lifetime hoặc YTD)
- Tổng doanh thu / doanh thu tháng hiện tại
- Biểu đồ: doanh thu (mặc định **theo tháng** — 12 tháng; có thể chuyển **theo ngày** trong kỳ)
- Biểu đồ: giờ giảng dạy (mặc định **theo ngày** trong kỳ; có thể chuyển **theo tháng**)
- Biểu đồ: tiến độ từng khóa `active` (bar %)

Page: `Pages/Coaching/Dashboard.vue` (Chart.js inline + `CoachingWorkspace.vue`).

### 9.4 Danh sách & lịch buổi (v1.1)

| Page | Route | Mô tả |
|---|---|---|
| `Sessions/Index.vue` | `coaching.sessions.index` | Datagrid: tìm kiếm, lọc server (`CoachingSessionIndexQuery`), bảng/nhóm, drawer chi tiết, xuất |
| `Sessions/Schedule.vue` | `coaching.sessions.schedule` | Lịch tuần + mini calendar; tạo/sửa qua calendar API |

JSON phụ: `coaching.sessions.calendar.feed`, `coaching.sessions.export` (export index).

---

## 10. Database schema

Prefix: `va_prd_`. Chi tiết: `docs/DATABASE_STRUCTURE.md` §8.

| Bảng | Mô tả |
|---|---|
| `coaching_courses` | Khóa học |
| `coaching_sessions` | Buổi học |
| `coaching_session_materials` | Tài liệu |
| `coaching_assignments` | Bài tập |
| `coaching_progress` | Tiến độ theo account |

---

## 11. Route map (thực tế — `routes/web.php`)

| Method | URI | Name | Ghi chú |
|---|---|---|---|
| GET | `/coaching` | `coaching.dashboard` | KPI + biểu đồ |
| GET | `/coaching/courses` | `coaching.courses.index` | |
| GET | `/coaching/courses/create` | `coaching.courses.create` | |
| POST | `/coaching/courses` | `coaching.courses.store` | |
| GET | `/coaching/courses/{course}` | `coaching.courses.show` | |
| GET | `/coaching/courses/{course}/edit` | `coaching.courses.edit` | |
| PUT | `/coaching/courses/{course}` | `coaching.courses.update` | |
| DELETE | `/coaching/courses/{course}` | `coaching.courses.destroy` | |
| POST | `/coaching/courses/{course}/sessions` | `coaching.courses.sessions.store` | Tạo buổi từ khóa |
| GET | `/coaching/sessions/schedule` | `coaching.sessions.schedule` | Inertia lịch |
| GET | `/coaching/sessions/calendar/feed` | `coaching.sessions.calendar.feed` | JSON feed |
| POST | `/coaching/sessions/calendar` | `coaching.sessions.calendar.store` | Tạo từ lịch |
| GET | `/coaching/sessions` | `coaching.sessions.index` | Danh sách + filter |
| GET | `/coaching/sessions/export` | `coaching.sessions.export` | JSON export (≤500) |
| GET | `/coaching/sessions/{session}` | `coaching.sessions.show` | Materials + assignments |
| PATCH | `/coaching/sessions/{session}/calendar` | `coaching.sessions.calendar.update` | |
| PATCH | `/coaching/sessions/{session}` | `coaching.sessions.update` | |
| DELETE | `/coaching/sessions/{session}` | `coaching.sessions.destroy` | |
| POST | `/coaching/sessions/{session}/materials` | `coaching.sessions.materials.store` | |
| POST | `/coaching/sessions/{session}/assignments` | `coaching.sessions.assignments.store` | |
| PATCH | `/coaching/assignments/{assignment}` | `coaching.assignments.update` | |
| DELETE | `/coaching/assignments/{assignment}` | `coaching.assignments.destroy` | Coach (quản lý khóa) |
| POST | `/coaching/progress` | `coaching.progress.upsert` | |
| GET | `/coaching/materials/{material}/file` | `coaching.materials.file` | Download public disk |

Nav (`Navigation.php`): Dashboard, Khóa học, Lịch buổi (`sessions/schedule`), Danh sách buổi (`sessions`) — `admin`, `lead`; `member` nếu là học viên; coaching-only users chỉ nhóm này.

**Chưa có route:** `coaching.reports.monthly` — báo cáo tháng qua props dashboard + `exportCoachingMonthlyWorkbook` client-side.

---

## 12. Frontend components map

| File | Vai trò |
|---|---|
| `CoachingWorkspace.vue` | Shell dashboard (KPI + charts) |
| `CoachingSessionsSummaryBar.vue` | Toolbar + KPI dòng 2 danh sách buổi (datagrid pattern) |
| `CoachingSessionsTableView.vue` / `CoachingSessionsGroupView.vue` | Bảng / nhóm theo ngày |
| `CoachingSessionCard.vue` | Card buổi (group view) |
| `SessionDrawer.vue` | Chi tiết nhanh buổi |
| `CoachingSessionRowActions.vue` | Hành động hàng buổi (menu ⋯) |
| `CoachingCourseRowActions.vue` | Hành động hàng khóa (chi tiết / sửa / xóa) |
| `CalendarSidebar.vue`, `MiniCalendar.vue` | Lịch |
| `CoachingSessionFormModal.vue`, `QuickSessionModal.vue` | Tạo/sửa buổi |
| `CoachingCourseFormModal.vue` | Modal khóa |
| `CoachingMaterialEmbed.vue` | Embed an toàn |
| `useCoachingCalendar.js` | Feed + drag lịch |
| `useCoachingSessionList.js` | Filter, cột, export danh sách |
| `coachingSessionDisplay.js` | Label/format hiển thị buổi |
| `coachingCourseDisplay.js` | Label/format ô trống khóa học |
| `useCoachingExport.js` | Xuất Excel báo cáo tháng + export sessions |

**Planned / chưa tách component:** Kanban bài tập (`AssignmentBoard`), timeline riêng — logic chủ yếu trên `Sessions/Show.vue` (tab chi tiết; form chỉ khi bấm «Chỉnh sửa» trên header).

---

## 13. Luồng tính tài chính tự động

**v1 (đơn giản):** Doanh thu tháng = tổng `hourly_rate * total_hours` của mọi session `completed` trong tháng (lấy `hourly_rate` từ course tại thời điểm buổi học — snapshot optional v2).

**v1 thay thế:** Phân bổ `total_fee` đều theo số buổi đã hoàn thành của khóa:

```
revenue_per_completed_session = course.total_fee / max(1, total_sessions_planned)
month_revenue += revenue_per_completed_session * completed_in_month
```

**Giá trung bình:**

```
avg_per_hour = month_revenue / month_teaching_hours
avg_per_session = month_revenue / completed_sessions_in_month
```

Use Case `CoachingFinancialSummary::forMonth()` nhận `year`, `month`, trả DTO cho Dashboard + export.

---

## 14. Definition of Done (triển khai)

- [x] Migrations 5 bảng + code auto `COACH-001`
- [x] CRUD khóa + buổi + materials + assignments
- [x] Progress flags + % hiển thị trên Show khóa
- [x] Dashboard KPI + biểu đồ doanh thu + giờ dạy (Chart.js)
- [x] Báo cáo tháng + xuất Excel (`useCoachingExport.js`)
- [x] Policy theo admin/coach/student
- [x] Embed an toàn (`SafeEmbedUrl` + sandbox iframe)
- [x] Danh sách buổi + lịch + export JSON index + feature tests (`CoachingTest.php`)
- [ ] Route Inertia riêng báo cáo tháng (optional — hiện export từ dashboard)
- [ ] Application Use Cases tách aggregation (optional refactor từ Support)

---

## 15. Công nghệ (điều chỉnh theo VA-QLDA)

| Đề xuất ban đầu | Trong VA-QLDA |
|---|---|
| Laravel 12 | Laravel 10 |
| Laravel Permission | Role `system` + policy; mở rộng flag coach khi cần |
| Vue / Nuxt | Vue 3 + Inertia |
| ApexCharts / ECharts | Chart.js |
| Scout | Không bắt buộc cho module này |

**Tách biệt Knowledge Base:** không dùng chung bảng `kb_articles`; chỉ chia sẻ TipTap, Comment (nếu cần thảo luận buổi học — follow-up morph `CoachingSession`).
