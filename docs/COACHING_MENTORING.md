# COACHING / MENTORING — Module Đào tạo & Mentoring

> Module **độc lập** với Knowledge Base / Blog — lưu khóa học, buổi học, tài liệu, bài tập, tiến độ và dashboard tài chính.
> **Trạng thái:** 📋 Thiết kế — chưa triển khai code. Roadmap: `docs/NEXT_STEPS.md` (LT-08).

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

- **CRUD & materials:** MVC (Controller → Model / FormRequest / Policy / Resource).
- **Dashboard aggregation:** `app/Application/Coaching/` (Use Cases) — `BuildMonthlyStats`, `BuildFinancialSummary`, `BuildOverviewDashboard` (tránh query nặng trong Controller).

```
routes/web.php
    → CoachingCourseController, CoachingSessionController,
      CoachingAssignmentController, CoachingDashboardController
    → Policies: CoachingCoursePolicy, …
    → Application/Coaching/*UseCase

resources/js/
    → Pages/Coaching/Dashboard.vue
    → Pages/Coaching/Courses/Index.vue, Show.vue, Edit.vue
    → Pages/Coaching/Sessions/Show.vue (materials + assignments)
    → modules/coaching/
    → composables/useCoachingProgress.js, useCoachingFinance.js
```

**Charts:** Chart.js + vue-chartjs (đã có trong dự án).

**Editor nội dung buổi học:** TipTap cho `content` (HTML).

---

## 3. Phân quyền

Module dùng **vai trò mở rộng** (lưu trên `system_accounts` hoặc pivot — thiết kế chi tiết khi implement). Ánh xạ logic với yêu cầu nghiệp vụ:

| Vai trò nghiệp vụ | Map gợi ý VA-QLDA | Xem | Tạo | Sửa | Xóa | Xuất báo cáo |
|---|---|---|---|---|---|---|
| Super Admin | `admin` | ✅ | ✅ | ✅ | ✅ | ✅ |
| Coach | `lead` + flag `is_coach` hoặc role mới | Khóa của mình + được gán | ✅ | ✅ | ✅ (giới hạn) | ✅ (phạm vi mình) |
| Mentor | `lead` / `member` + flag | Tương tự coach (read-only finance tùy policy) | Hạn chế | Hạn chế | ❌ | ❌ |
| Student | `member` / học viên (`employee`) | Khóa được gán | ❌ | Bài tập của mình | ❌ | ❌ |

**v1 đơn giản hóa:** `admin` full; `lead` = coach (mọi khóa); `member` = student (chỉ khóa có `student_id` = employee của account); `viewer` = không vào module hoặc chỉ dashboard read-only admin.

Quyền xuất: Excel styled qua composable `useCoachingExport.js` (pattern `useRiskExport.js`).

---

## 4. Quản lý khóa học

### 4.1 Trường dữ liệu

| Trường | Ghi chú |
|---|---|
| Tên khóa học | `name` |
| Mô tả | `description` (text) |
| Mục tiêu | `objectives` (text) |
| Học viên | `student_id` → `employees` (v1: một học viên chính; v2: pivot nhiều học viên) |
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

---

## 7. Bài tập

Bảng `coaching_assignments` gắn `session_id`.

| Trường | Ghi chú |
|---|---|
| Tiêu đề / mô tả | `title`, `description` |
| Deadline | `deadline` (datetime) |
| Độ ưu tiên | `high` \| `medium` \| `low` |
| Trạng thái | `todo` \| `doing` \| `review` \| `done` |
| File nộp | `submission_path` |
| Link GitHub | `github_url` |
| Ghi chú | `notes` |

Học viên (`member`) cập nhật trạng thái + upload nộp; coach chuyển `review` → `done`.

---

## 8. Theo dõi học tập

Bảng `coaching_progress` — một bản ghi / `(course_id, session_id, account_id)`:

| Cờ | Ý nghĩa |
|---|---|
| `is_viewed` | Đã xem bài giảng / tài liệu buổi |
| `is_in_progress` | Đang học buổi này |
| `is_completed` | Hoàn thành buổi (đồng bộ khi session → `completed` hoặc manual) |

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
- Biểu đồ: doanh thu theo tháng (12 tháng)
- Biểu đồ: số giờ giảng dạy theo tháng
- Biểu đồ: tiến độ từng khóa `active` (bar %)

Page: `Pages/Coaching/Dashboard.vue`.

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

## 11. Route map (đề xuất)

| Method | URI | Name |
|---|---|---|
| GET | `/coaching` | `coaching.dashboard` |
| GET | `/coaching/courses` | `coaching.courses.index` |
| GET | `/coaching/courses/create` | `coaching.courses.create` |
| POST | `/coaching/courses` | `coaching.courses.store` |
| GET | `/coaching/courses/{course}` | `coaching.courses.show` |
| GET | `/coaching/courses/{course}/edit` | `coaching.courses.edit` |
| PUT | `/coaching/courses/{course}` | `coaching.courses.update` |
| DELETE | `/coaching/courses/{course}` | `coaching.courses.destroy` |
| POST | `/coaching/courses/{course}/sessions` | `coaching.sessions.store` |
| GET | `/coaching/sessions/{session}` | `coaching.sessions.show` |
| PATCH | `/coaching/sessions/{session}` | `coaching.sessions.update` |
| POST | `/coaching/sessions/{session}/materials` | `coaching.materials.store` |
| POST | `/coaching/sessions/{session}/assignments` | `coaching.assignments.store` |
| PATCH | `/coaching/assignments/{assignment}` | `coaching.assignments.update` |
| POST | `/coaching/progress` | `coaching.progress.upsert` |
| GET | `/coaching/reports/monthly` | `coaching.reports.monthly` | Inertia props / export |
| GET | `/coaching/materials/{material}/file` | `coaching.materials.file` |

Nav: «Coaching / Mentoring» — `admin`, `lead`; `member` nếu là học viên có khóa.

---

## 12. Frontend components map

| Component | Vai trò |
|---|---|
| `CoachingKpiCards.vue` | Widget tổng quan |
| `CoachingRevenueChart.vue` | Line/bar Chart.js |
| `CoachingHoursChart.vue` | Giờ dạy theo tháng |
| `CourseProgressChart.vue` | % từng khóa active |
| `SessionTimeline.vue` | Danh sách buổi theo khóa |
| `SessionMaterialsPanel.vue` | Tabs Canva/Docs/Video/File |
| `AssignmentBoard.vue` | Kanban todo→done |
| `useCoachingFinance.js` | Tính avg/giờ, avg/buổi |
| `useCoachingProgress.js` | % khóa, mark viewed |
| `useCoachingExport.js` | Xuất báo cáo tháng Excel |

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

Use Case `BuildFinancialSummary` nhận `year`, `month`, trả DTO cho Dashboard + export.

---

## 14. Definition of Done (triển khai)

- [x] Migrations 5 bảng + code auto `COACH-001`
- [x] CRUD khóa + buổi + materials + assignments
- [x] Progress flags + % hiển thị trên Show khóa
- [x] Dashboard KPI + biểu đồ doanh thu + giờ dạy (Chart.js)
- [x] Báo cáo tháng + xuất Excel (`useCoachingExport.js`)
- [x] Policy theo admin/coach/student
- [x] Embed an toàn (`SafeEmbedUrl` + sandbox iframe)
- [x] Feature tests + E2E smoke

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
