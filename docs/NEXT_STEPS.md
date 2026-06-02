# NEXT STEPS — VA QLDA Roadmap

---

## Trạng Thái Hiện Tại (Stage 0 — MVP Complete)

Hệ thống đã có đầy đủ nền tảng:
- ✅ Authentication & Authorization (4 roles)
- ✅ Project Management (Sprint, Task, Gantt, Worklog)
- ✅ Daily Report (Create → Submit → Review → Score)
- ✅ Issue Tracking (Blocker, Bug, Feedback)
- ✅ Comments & Reactions (Polymorphic)
- ✅ Department Management
- ✅ File Attachments

---

## Quick Wins (1-3 ngày mỗi item)

> Các tính năng nhỏ, ảnh hưởng cao, ít rủi ro.

### QW-01 — Notifications System (In-App)
- **Mục tiêu:** Người dùng nhận thông báo khi có task giao cho mình, khi blocker được giải quyết, khi daily report bị reject
- **Approach:** Database notifications + bell icon + badge counter
- **Dependencies:** Laravel Notifications (built-in)
- **Ước tính:** 2-3 ngày

### QW-02 — Export to Excel
- **Mục tiêu:** Export danh sách tasks, blockers, worklog ra Excel
- **Approach:** Laravel Excel package hoặc dùng XLSX đã có ở frontend
- **Dependencies:** XLSX (đã cài)
- **Ước tính:** 1-2 ngày

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

### LT-02 — Knowledge Base (Wiki)
- **Mục tiêu:** Hệ thống tài liệu nội bộ — procedures, onboarding guides, HOWTOs
- **Scope:**
  - Page model với parent/child hierarchy
  - Rich text editor (TipTap, đã có)
  - Search, tags, versioning
- **Ước tính:** 2-3 tuần

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
Stage 1 (Tháng 1-2):
    Refactor Phase 1-2 (Code cleanup + Folder structure)
    + QW-01 (Notifications)
    + QW-02 (Export Excel)
    + MI-01 (Team Dashboard)
    + MI-02 (My Work Page)

Stage 2 (Tháng 3-4):
    Refactor Phase 3-4 (Architecture + UI)
    + QW-03 (Global Search)
    + MI-05 (Email Notifications)
    + MI-07 (Worklog Reports)
    + MI-08 (Member Profile)

Stage 3 (Tháng 5-6):
    Refactor Phase 5 (Performance)
    + LT-01 (REST API)
    + LT-02 (Knowledge Base)
    + LT-03 (Performance Review)

Stage 4+ (6 tháng+):
    LT-04 (Advanced Analytics)
    LT-05 (Real-Time)
    LT-06 (SSO)
    LT-07 (Admin Config)
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
