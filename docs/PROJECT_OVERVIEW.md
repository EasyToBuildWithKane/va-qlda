# PROJECT OVERVIEW — VA QLDA

> **VA QLDA** (VAschools Quản Lý Dự Án) — Hệ thống quản lý dự án nội bộ dành cho tổ chức giáo dục VAschools.

---

## 1. Mục Tiêu Dự Án

VA QLDA là nền tảng quản lý công việc và đánh giá hiệu suất nhân sự nội bộ, được xây dựng dành riêng cho VAschools. Hệ thống giải quyết các bài toán thực tế của tổ chức:

| Bài toán | Giải pháp |
|---|---|
| Theo dõi tiến độ dự án phân tán | Quản lý dự án tập trung với Sprint, Task, Gantt |
| Không có kênh báo cáo ngày chuẩn hóa | Module Daily Report với chấm điểm và xếp loại |
| Khó quản lý rủi ro / vướng mắc | Module Blocker với tracking mức độ nghiêm trọng |
| Thiếu luồng phản hồi từ nhân viên | Module Feedback & Bug Report |
| Không đo được chi phí nhân công thực tế | Worklog gắn rate theo từng dự án |
| Quản lý nhân sự theo phòng ban | Module Department + Employee linking |

---

## 2. Các Module Chính

```
VA QLDA
├── [AUTH]          Xác thực người dùng (custom guard "system")
├── [NOTIFICATION]  Hệ thống thông báo in-app (bell icon, drawer, preferences)
├── [PROJECT]       Quản lý dự án, sprint, task, epics
│   ├── Sprint      Lập kế hoạch theo vòng lặp Agile
│   ├── Task        Công việc chi tiết (có subtask, dependency, attachment)
│   ├── Worklog     Ghi giờ làm & chi phí nhân công
│   ├── Gantt       Timeline trực quan
│   ├── Documents   Tài liệu dự án đính kèm
│   └── Members     Quản lý thành viên & role trong dự án
├── [DAILY REPORT]  Báo cáo ngày (tạo → nộp → chấm điểm → xếp loại)
├── [BLOCKER]       Quản lý vướng mắc / rủi ro (RSK-001)
├── [BUG]           Báo cáo & theo dõi lỗi (BUG-0001)
├── [FEEDBACK]      Góp ý & đề xuất từ nhân viên (FB-0001)
├── [COMMENT]       Thảo luận đa hình (Task, Bug, Blocker, Feedback)
├── [DEPARTMENT]    Quản lý phòng ban
├── [SYSTEM CONFIG] Cấu hình hệ thống (admin: nhận diện, đăng nhập, Telegram, phân quyền) → docs/SYSTEM_CONFIG.md
├── [DASHBOARD]     Tổng quan hệ thống (đang phát triển)
├── [KNOWLEDGE BASE] Tri thức nội bộ (bài viết, danh mục, tags, tìm kiếm, yêu thích) → docs/KNOWLEDGE_BASE.md
└── [COACHING]      Coaching/Mentoring (khóa học, buổi học, bài tập, tiến độ, dashboard tài chính) → docs/COACHING_MENTORING.md
```

---

## 3. Luồng Hoạt Động Tổng Thể

### 3.1 Luồng Quản Lý Dự Án

```
Tạo Project
    │
    ├── Thêm Members (role + rate)
    ├── Tạo Epics (phân nhóm tính năng)
    ├── Tạo Sprints (kế hoạch vòng lặp)
    │
    └── Tạo Tasks
            │
            ├── Gán Assignee, Reviewer
            ├── Set Priority, Status, Phase
            ├── Đặt Due Date, Estimate Hours
            ├── Link Dependencies (Gantt)
            │
            ├── [Member] Làm việc → Log Hours (Worklog)
            ├── [Member] Upload Attachments (Task + Blocker + Project Documents)
            ├── [Member] Comment / Thảo luận
            │
            ├── [System] Notification → Assignee, Watchers, Admins
            │
            └── [Lead/PM] Review → Done
                        │
                        └── Tính cost = hours × rate_snapshot
```

### 3.2 Luồng Daily Report

```
[Member] Viết báo cáo ngày (Today)
    │
    ├── Điền: Goals, Progress, Results, Plan Tomorrow
    ├── Chọn Projects liên quan
    ├── Trạng thái: DRAFT
    │
    └── Submit → SUBMITTED
                │
                └── [Lead/Admin] Review Queue
                            │
                            ├── Score (5 tiêu chí) → REVIEWED + Grade A-F
                            └── Reject (ghi chú) → DRAFT (viết lại)
```

### 3.3 Luồng Blocker / Risk

```
[Member] Phát sinh vướng mắc → Tạo Blocker (RSK-001)
    │
    ├── Gán severity (critical/high/medium/low)
    ├── Gán Owner (người chịu trách nhiệm)
    ├── Link to Task (tùy chọn)
    │
    └── Owner xử lý → Cập nhật status
                │
                ├── in_progress → Working
                ├── resolved → Có resolution notes
                └── closed → Done
```

---

## 4. Kiến Trúc Hệ Thống

```
┌─────────────────────────────────────────────────────────────┐
│                      CLIENT (Browser)                        │
│           Vue 3 + Inertia.js + Tailwind CSS                  │
│                                                              │
│  Pages/ → modules/ + shared/ → composables → stores/ (Pinia) │
└──────────────────────────┬──────────────────────────────────┘
                           │ Inertia Protocol (HTTP + JSON)
┌──────────────────────────▼──────────────────────────────────┐
│                  LARAVEL APPLICATION                          │
│                                                              │
│  routes/web.php → Controllers → UseCases (DailyReport, Project, Task) │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ HTTP Layer        │ Application Layer │ Domain Layer   │  │
│  │ Controllers       │ Use Cases         │ Domain Models  │  │
│  │ Form Requests     │ (DailyReport,     │ (DailyReport)  │  │
│  │ Resources         │  Project, Task)   │                │  │
│  └───────────────────┴───────────────────┴───────────────┘  │
│                                                              │
│  app/Services/ (NotificationService)                         │
│  Support/Options/* (Employee, Project, Department)           │
│  config/business.php — business constants                    │
│                                                              │
└──────────────────────────┬──────────────────────────────────┘
                           │ Eloquent ORM
┌──────────────────────────▼──────────────────────────────────┐
│                   MySQL Database                              │
│           (Prefix: va_prd_, ~27 tables)                      │
└─────────────────────────────────────────────────────────────┘
```

---

## 5. Phân Quyền Người Dùng

| Role | Mô Tả | Quyền Chính |
|---|---|---|
| `admin` | Quản trị viên hệ thống | Toàn quyền, cấu hình hệ thống |
| `lead` | Trưởng nhóm / Team Lead | Tạo dự án, review báo cáo, quản lý thành viên |
| `member` | Thành viên nhóm | Làm việc trong dự án, viết báo cáo ngày |
| `viewer` | Giám đốc / Quan sát | Chỉ xem, không chỉnh sửa |

---

## 6. Tech Stack

| Layer | Công Nghệ | Phiên Bản |
|---|---|---|
| Backend Framework | Laravel | 10.10 |
| PHP | PHP | 8.1+ |
| Frontend Framework | Vue.js | 3.5.35 |
| SPA Bridge | Inertia.js | Latest |
| CSS Framework | Tailwind CSS | 3.4.19 |
| Build Tool | Vite | 5.0 |
| Database | MySQL | Latest |
| Auth | Laravel Sanctum + Custom Guard | - |
| Rich Text Editor | TipTap | 3.24.0 |
| Gantt Chart | Frappe Gantt | 1.2.2 |
| Charts | Chart.js + Vue ChartJS | - |
| Spreadsheet Export | XLSX | - |
| Audit Log | Spatie Activity Log | - |
| Code Formatter | Laravel Pint | - |
| Static Analysis | Larastan / PHPStan | - |
| E2E Testing | Playwright | 1.49 |
| Git Hooks | Husky + commitlint | 9.x |
| CI | GitHub Actions | `.github/workflows/ci.yml` |
| Project Memory | `_dev/` + `_dev/vi/` | operational docs |

---

## 7. Trạng Thái Hiện Tại

**Giai đoạn: Stage 2 — Feature expansion + refactor foundation complete**

| Hạng mục | Trạng thái |
|---|---|
| Refactor Phase 1–5 (modules/, Use Cases, Pinia, tests) | ✅ Hoàn thành (2026-06-03) |
| Dev tooling (_dev/, Husky, Playwright CI) | ✅ Hoàn thành |

| Module | Trạng Thái |
|---|---|
| Authentication | ✅ Hoàn thành |
| Project Management | ✅ Hoàn thành |
| Sprint & Task (+ subtasks, bulk, attachments) | ✅ Hoàn thành |
| Worklog / Time Tracking | ✅ Hoàn thành |
| Project Documents (upload, preview, activity log) | ✅ Hoàn thành |
| Task Attachments | ✅ Hoàn thành |
| Blocker Attachments & Activity Log | ✅ Hoàn thành |
| Sprint Workspace (list, calendar, drag-drop) | ✅ Hoàn thành |
| Gantt Chart + Timeline + Burndown | ✅ Hoàn thành |
| Project Dashboard (overview, workload, activity feed) | ✅ Hoàn thành |
| Risk Import/Export (Excel) | ✅ Hoàn thành |
| Daily Report | ✅ Hoàn thành |
| Blocker Tracking | ✅ Hoàn thành |
| Bug Tracking | ❌ Đã gỡ (2026-06) — dùng Feedback / Blocker |
| Feedback | ✅ Hoàn thành |
| Department Management | ✅ Hoàn thành |
| Comments & Reactions | ✅ Hoàn thành |
| **Notification System (in-app bell + drawer)** | ✅ Hoàn thành |
| Team Dashboard | 🔄 Đang phát triển |
| Weekly Performance Review | 📋 Kế hoạch |
| Knowledge Base (Wiki) | ✅ Triển khai v1 → docs/KNOWLEDGE_BASE.md |
| Coaching / Mentoring | ✅ Triển khai v1 → docs/COACHING_MENTORING.md |
| Account Settings | 📋 Kế hoạch |
