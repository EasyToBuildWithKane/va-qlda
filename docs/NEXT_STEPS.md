# NEXT STEPS — VA QLDA Roadmap

---

## Trạng Thái Hiện Tại (Stage 2 — Đang Bắt Đầu)

> Cập nhật 2026-06-03. Stage 1 hoàn thành. Chuyển sang Stage 2.

**Stage 1 — HOÀN THÀNH ✅**
- ✅ Authentication & Authorization (4 roles)
- ✅ Project Management (Sprint, Task, Gantt, Worklog, Attachments, Documents)
- ✅ Sprint Workspace (list/calendar view, drag-drop, bulk import)
- ✅ Project Dashboard (overview, workload, activity feed, risk panel)
- ✅ Timeline + Burndown Chart
- ✅ Task Detail (rich editor, attachments, subtasks, collaboration)
- ✅ Daily Report (Create → Submit → Review → Score)
- ✅ Issue Tracking (Blocker + attachments + import/export, Bug, Feedback)
- ✅ Comments & Reactions (Polymorphic)
- ✅ Department Management (full overhaul)
- ✅ **Notification System** (bell + drawer + preferences + admin feed)
- ✅ Export to Excel (risk/blocker, sprint, project list)

**Stage 2 — Đang Tiến Hành 🔄**
- 🔄 Team Dashboard (MI-01) — in progress
- 📋 My Work Page (MI-02)
- 📋 Global Search (QW-03)
- 📋 Worklog Reports (MI-07)

**Refactor Phase 1–5 — HOÀN THÀNH ✅ (2026-06-03)**
- ✅ Code cleanup, constants, enums, feature tests
- ✅ `modules/project/`, `shared/ui/`, xóa `Components/Project/`
- ✅ Project/Task Use Cases, Options services, Pinia stores
- ✅ Shared UI library, lazy routes, Vite code splitting
- 📋 Còn lại: N+1 optimization, visual regression, DailyReport module migrate

---

## Quick Wins (1-3 ngày mỗi item)

> Các tính năng nhỏ, ảnh hưởng cao, ít rủi ro.

### ~~QW-01 — Notifications System~~ ✅ HOÀN THÀNH (2026-06-03)
- In-app bell icon + drawer với cursor pagination
- NotificationService + NotificationDispatcher
- 40+ notification types (Task, Sprint, Project, Document, Comment, System, Admin)
- User preferences per notification type
- Admin feed (admin-only notifications)
- ScanNotificationAlerts artisan command

### QW-02 — Export to Excel ✅ HOÀN THÀNH (2026-06-03)
- Risk/Blocker export: `useRiskExport.js`
- Sprint export: `useSprintExport.js`
- Project list export: `useProjectListExport.js`
- Single project export: `useProjectExport.js`

### QW-03 — Search Toàn Cục
- **Mục tiêu:** Tìm kiếm nhanh tasks, projects, bugs theo keyword
- **Approach:** Search bar trong header, query Projects + Tasks + Bugs
- **Dependencies:** None
- **Ước tính:** 1-2 ngày

### QW-04 — Dark Mode
- **Mục tiêu:** Hỗ trợ chế độ tối
- **Approach:** Tailwind dark: classes + `darkMode: 'class'` config
- **Dependencies:** None
- **Ước tính:** 1-2 ngày (styling only)

### QW-05 — Print / PDF Daily Report
- **Mục tiêu:** In hoặc xuất báo cáo ngày ra PDF
- **Approach:** Browser print CSS + print button
- **Dependencies:** None
- **Ước tính:** 0.5-1 ngày

### QW-06 — Keyboard Shortcuts
- **Mục tiêu:** Phím tắt cho thao tác thường dùng (N = new task, / = search)
- **Approach:** useKeyboard composable
- **Dependencies:** None
- **Ước tính:** 1 ngày

---

## Medium Improvements (1-2 tuần mỗi item)

> Tính năng trung bình, cần design & planning.

### MI-01 — Team Dashboard
- **Mục tiêu:** Tổng quan KPI theo nhóm: số tasks hoàn thành, worklog hours, blocker tồn đọng, điểm báo cáo trung bình
- **Components cần tạo:**
  - Team KPI cards
  - Member performance table
  - Sprint velocity chart
  - Daily report compliance rate
- **Ước tính:** 5-7 ngày

### MI-02 — My Work Page
- **Mục tiêu:** Page cá nhân hiển thị tất cả tasks được giao, deadlines hôm nay, worklogs tuần này
- **Components cần tạo:**
  - Personal task list (cross-project)
  - Calendar view cho deadlines
  - Weekly worklog summary
- **Ước tính:** 3-5 ngày

### MI-03 — Project Templates
- **Mục tiêu:** Tạo project mới từ template có sẵn (e.g. "Web Project Template" với tasks mặc định)
- **Approach:** Template model → clone khi tạo project
- **Ước tính:** 3-4 ngày

### MI-04 — Recurring Tasks
- **Mục tiêu:** Tasks tự động tạo lại theo chu kỳ (daily standup, weekly review)
- **Approach:** Cron job + task recurrence settings
- **Dependencies:** Laravel Queue + Scheduler
- **Ước tính:** 4-5 ngày

### MI-05 — Email Notifications
- **Mục tiêu:** Gửi email khi deadline gần, khi task được giao, khi report bị reject
- **Approach:** Laravel Mail + Queue
- **Dependencies:** Mail server config, Queue (MI prerequisite)
- **Ước tính:** 3-4 ngày

### MI-06 — Advanced Filters & Saved Views
- **Mục tiêu:** Lưu filter preset cho project list, task board (e.g. "My overdue tasks")
- **Approach:** JSON filter config stored in user preferences
- **Ước tính:** 3-4 ngày

### MI-07 — Worklog Reports
- **Mục tiêu:** Báo cáo giờ làm theo tuần/tháng, theo người, theo dự án
- **Components cần tạo:**
  - Worklog summary table
  - Cost by project chart
  - Hours by member chart
- **Ước tính:** 4-5 ngày

### MI-08 — Member Profile Page
- **Mục tiêu:** Trang cá nhân hiển thị thông tin, kỹ năng, lịch sử dự án, điểm báo cáo
- **Ước tính:** 3-4 ngày

---

## Long-Term Improvements (1 tháng+)

> Tính năng lớn, cần architecture planning riêng.

### LT-01 — REST API + Mobile App Support
- **Mục tiêu:** Expose JSON API để mobile app hoặc third-party tích hợp
- **Scope:**
  - Tạo `routes/api.php` với versioned endpoints
  - Bearer Token auth via Sanctum
  - API Resource documentation (Swagger/OpenAPI)
- **Ước tính:** 2-3 tuần

### LT-02 — Knowledge Base (Tri Thức)
- **Mục tiêu:** Wiki nội bộ kiểu Viblo/Notion/Confluence (đơn giản) — procedures, onboarding, HOWTO, kinh nghiệm thực tế
- **Tài liệu thiết kế:** [`docs/KNOWLEDGE_BASE.md`](KNOWLEDGE_BASE.md)
- **Scope:**
  - 8 chuyên mục seed + tags; bài viết (title, slug, excerpt, TipTap, ảnh, đính kèm)
  - Trạng thái draft / published / archived; full-text search; lọc tag & danh mục
  - UI: sidebar danh mục, list, chi tiết + TOC, breadcrumb, bài liên quan, responsive
  - Yêu thích, đã đọc, lượt xem; bình luận (polymorphic Comment)
  - Phân quyền xem theo role; MVC + Policy; lưu file `public` disk
- **Dependencies:** TipTap (đã có), Comment morph (đã có); tùy chọn Scout phase 2
- **Ước tính:** 2-3 tuần

### LT-08 — Coaching / Mentoring
- **Mục tiêu:** Module độc lập — khóa học, buổi học, tài liệu (Canva/Docs/Video/File), bài tập, tiến độ %, dashboard tài chính
- **Tài liệu thiết kế:** [`docs/COACHING_MENTORING.md`](COACHING_MENTORING.md)
- **Scope:**
  - CRUD khóa + buổi; materials đa loại; assignments (todo→done)
  - Progress per session; KPI tháng (buổi, giờ, học viên, doanh thu, hủy)
  - Dashboard tổng quan + Chart.js (doanh thu, giờ dạy, tiến độ khóa)
  - Roles: admin/coach/student (map `system` roles + policy); xuất báo cáo Excel
  - Application Use Cases cho aggregation tài chính
- **Dependencies:** TipTap, Chart.js, file storage (đã có)
- **Ước tính:** 3-4 tuần

### LT-03 — Performance Review Module
- **Mục tiêu:** Đánh giá nhân sự định kỳ (quarterly/annual)
- **Scope:**
  - Review cycles
  - 360-degree feedback
  - KPI tracking over time
  - Grade history
- **Ước tính:** 3-4 tuần

### LT-04 — Advanced Reporting & Analytics
- **Mục tiêu:** Dashboard phân tích dữ liệu tổng hợp
- **Scope:**
  - Sprint velocity trends
  - Bug density by project
  - Daily report compliance by department
  - Cost vs budget variance
- **Ước tính:** 2-3 tuần

### LT-05 — Real-Time Collaboration
- **Mục tiêu:** Live updates khi người khác sửa cùng task/project
- **Approach:** Laravel Echo + WebSockets (Pusher hoặc Soketi self-hosted)
- **Dependencies:** WebSocket server setup
- **Ước tính:** 2-3 tuần

### LT-06 — SSO / OAuth Integration
- **Mục tiêu:** Đăng nhập bằng Google Workspace hoặc Microsoft 365 (phù hợp tổ chức giáo dục)
- **Approach:** Laravel Socialite
- **Ước tính:** 1-2 tuần

### LT-07 — System Configuration UI
- **Mục tiêu:** Admin panel để cấu hình hệ thống (email settings, scoring weights, role permissions)
- **Scope:**
  - Settings model + UI
  - Audit log cho config changes
- **Ước tính:** 2-3 tuần

---

## Recommended Sequence

```
Stage 1 (Tháng 1-2): ✅ HOÀN THÀNH
    ✅ QW-01 (Notifications System)
    ✅ QW-02 (Export Excel)
    ✅ Department Management Overhaul

Stage 2 (Tháng 3-4): 🔄 HIỆN TẠI
    🔄 MI-01 (Team Dashboard) — in progress
    📋 MI-02 (My Work Page)
    📋 QW-03 (Global Search)
    📋 MI-07 (Worklog Reports)
    📋 MI-08 (Member Profile)
    ~~Refactor Phase 1-2~~ ✅ Hoàn thành (2026-06-03)
    ~~Refactor Phase 3-5~~ ✅ Hoàn thành (2026-06-03)

Stage 3 (Tháng 5-6):
    + QW-05 (Print/PDF Daily Report)
    + MI-05 (Email Notifications — cần setup Queue trước)
    + MI-06 (Advanced Filters)
    ~~Refactor Phase 3-4~~ ✅ Done
    TD-002 Controllers query extraction (follow-up)

Stage 4 (6 tháng+):
    + LT-01 (REST API)
    + LT-02 (Knowledge Base — docs/KNOWLEDGE_BASE.md)
    + LT-08 (Coaching/Mentoring — docs/COACHING_MENTORING.md)
    + LT-03 (Performance Review)
    + LT-04 (Advanced Analytics)
    + LT-05 (Real-Time — cần WebSocket)
    + LT-06 (SSO)
    + LT-07 (Admin Config)
```

---

## Dependencies Map

```
Queue/Background Jobs (cần setup trước)
    ↓
Email Notifications (MI-05)
    ↓
Real-Time (LT-05)

REST API (LT-01)
    ↓
Mobile App (future)

Team Dashboard (MI-01)
    ↓
Performance Review (LT-03)
    ↓
Advanced Analytics (LT-04)
```
