# PROJECT OVERVIEW — VA Workspace

> **VA Workspace** (VAschools Quản Lý Dự Án) — Hệ thống quản lý dự án nội bộ dành cho tổ chức giáo dục VAschools.

---

## 1. Mục Tiêu Dự Án

VA Workspace là nền tảng quản lý công việc và đánh giá hiệu suất nhân sự nội bộ, được xây dựng dành riêng cho VAschools. Hệ thống giải quyết các bài toán thực tế của tổ chức:

| Bài toán | Giải pháp |
|---|---|
| Theo dõi tiến độ dự án phân tán | Quản lý dự án tập trung với Sprint, Task, Gantt |
| Không có kênh báo cáo ngày chuẩn hóa | Module Daily Report với chấm điểm và xếp loại |
| Khó quản lý rủi ro / vướng mắc | Module Blocker với tracking mức độ nghiêm trọng |
| Thiếu luồng phản hồi từ nhân viên | Module Feedback & Bug Report |
| Không đo được chi phí nhân công thực tế | Worklog gắn rate theo từng dự án |
| Quản lý nhân sự theo phòng ban | Module Department + Employee linking |
| Quản lý hợp đồng / NCC phân tán, quên hạn gia hạn | Module Contract Lifecycle (CLM) — Explorer, chi phí, gia hạn, cảnh báo hết hạn |
| Mật khẩu / tài khoản hạ tầng rải rác, thiếu kiểm soát | Module Credential — kho mật khẩu có phân quyền + nhật ký truy cập |
| Không đo được hiệu suất nhân sự khách quan | Module Performance — dashboard KPI + audit công việc theo nhân viên |

---

## 2. Các Module Chính

```
VA Workspace
├── [AUTH]          Xác thực người dùng (custom guard "system")
├── [NOTIFICATION]  Hệ thống thông báo in-app (bell icon, drawer, preferences)
│                   NotificationService + NotificationDispatcher (centralized fan-out)
├── [PROJECT]       Quản lý dự án, sprint, task, epics → docs/PROJECT_MANAGEMENT.md
│   ├── Sprint      Lập kế hoạch theo vòng lặp Agile
│   ├── Task        Công việc chi tiết (có subtask, dependency, attachment)
│   ├── Worklog     Ghi giờ làm & chi phí nhân công
│   ├── Gantt       Timeline trực quan
│   ├── Documents   Tài liệu dự án đính kèm
│   └── Members     Quản lý thành viên & role trong dự án
├── [DAILY REPORT]  Báo cáo ngày (tạo → nộp → chấm điểm → xếp loại)
├── [BLOCKER]       Quản lý vướng mắc / rủi ro (RSK-001)
├── [FEEDBACK]      Góp ý & đề xuất từ nhân viên (FB-0001)
├── [COMMENT]       Thảo luận đa hình (Task, Blocker, Feedback)
├── [DEPARTMENT]    Phòng ban (model + mutate API; Index UI gỡ — chờ HRM)
├── [SYSTEM CONFIG] Cấu hình (super_admin): Cấu hình chung · Thông báo hệ thống · Phân quyền · Cấu hình hợp đồng → docs/SYSTEM_CONFIG.md
├── [EVALUATION CONFIG] Cấu hình tiêu chí đánh giá (chung + theo PB, super_admin) → docs/EVALUATION_CONFIG.md
├── [CONGNGHE]      Trung tâm Công Nghệ — landing, đề xuất phần mềm, quản trị nội dung → docs/CONGNGHE_CONTENT.md
├── [PROFILE]       Hồ sơ cá nhân (/profile)
├── [AI ACCOUNTS]   Tài khoản AI, PĐX, chi phí, analytics → docs/AI_ACCOUNTS.md
├── [DASHBOARD HUB] Tổng quan hệ thống (/dashboard) — truy cập nhanh tất cả module, mini-stats theo role
├── [DASHBOARD WORK] Dashboard Công Việc (/work) — KPI dự án, tiến độ & tuân thủ báo cáo ngày
├── [MY WORK]       Việc của tôi (/my-work) — task cá nhân đa dự án theo bucket; lead xem việc nhóm (RBAC my_work.*)
├── [KNOWLEDGE BASE] Tri thức nội bộ (bài viết, danh mục, tags, tìm kiếm, yêu thích) → docs/KNOWLEDGE_BASE.md
├── [CONTRACT]      Quản lý hợp đồng / NCC (Explorer, chi phí, gia hạn phụ lục, đánh giá) → docs/CONTRACT_MANAGEMENT.md
├── [CREDENTIAL]    Kho tài khoản / mật khẩu hạ tầng (phân quyền, nhật ký truy cập) → docs/CREDENTIAL_MANAGEMENT.md
├── [PERFORMANCE]   Hiệu suất & audit công việc (dashboard KPI, audit theo nhân viên) → docs/PERFORMANCE_ANALYTICS.md
├── [AUDIT]         Nhật ký truy vết bảo mật (/audit) — cross-module SecurityAuditLogger, AuditActionCatalog (admin/super)
└── [ONBOARDING]    Tour tương tác theo vai trò khi đăng nhập (tiến độ, ngữ cảnh) → docs/ONBOARDING.md
```

> **Sơ đồ luồng tổng hợp (mermaid) + bản đồ doc ↔ code:** [`docs/FLOWS_AND_DOCS_MAP.md`](FLOWS_AND_DOCS_MAP.md).

---

## 3. Luồng Hoạt Động Tổng Thể

Chi tiết sơ đồ (mermaid): [`FLOWS_AND_DOCS_MAP.md`](FLOWS_AND_DOCS_MAP.md) §7. Module KB / Excel: doc module tương ứng.

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
│  routes/web/*.php → Controllers → UseCases (DailyReport, Project, Task) │
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
| `super_admin` | Siêu quản trị | God-mode (`Gate::before`), toàn quyền kể cả `/settings`, ma trận RBAC, gán role, reserved keys |
| `admin` | Quản trị viên hệ thống | Toàn quyền nghiệp vụ, **không** vào `/settings` |
| `lead` | Quản lý | Tạo dự án, review báo cáo, quản lý thành viên |
| `member` | Thành viên nhóm | Làm việc trong dự án, viết báo cáo ngày |
| `viewer` | Giám đốc / Quan sát | Chỉ xem, không chỉnh sửa |

> Hierarchy check: `isSuperAdmin()` / `isAdminTier()` (super+admin) — không hardcode `=== admin`.
> RBAC chi tiết: `docs/PERMISSIONS.md` · `App\Support\Auth\PermissionCatalog`.

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

| Module | Route | Trạng Thái |
|---|---|---|
| Authentication | `/login`, `/auth/hrm` (SSO), `/auth/google` (fallback) | ✅ Hoàn thành |
| **Dashboard Hub** (tổng quan tất cả module) | `/dashboard` | ✅ Hoàn thành |
| **Dashboard Công Việc** (KPI dự án, compliance) | `/work` | ✅ Hoàn thành |
| **My Today's Work** (Việc của tôi — đa dự án + team view) | `/my-work` | ✅ Hoàn thành |
| Project Management | `/projects` | ✅ — `PROJECT_MANAGEMENT.md` |
| Sprint & Task (+ subtasks, bulk, attachments) | `/projects/{id}` | ✅ Hoàn thành |
| Worklog / Time Tracking | `/projects/{id}/tasks/{task}/worklogs` | ✅ Hoàn thành |
| Project Documents (upload, preview, activity log) | `/projects/{id}/attachments` | ✅ Hoàn thành |
| Task Attachments | `/projects/{id}/tasks/{task}/attachments` | ✅ Hoàn thành |
| Blocker Attachments & Activity Log | `/blockers` | ✅ Hoàn thành |
| Sprint Workspace (list, calendar, drag-drop) | `/projects/{id}` | ✅ Hoàn thành |
| Gantt Chart + Timeline + Burndown | `/projects/{id}` | ✅ Hoàn thành |
| Project Dashboard (overview, workload, activity feed) | `/projects/{id}` | ✅ Hoàn thành |
| Risk Import/Export (Excel) | `/blockers` | ✅ Hoàn thành |
| Công Nghệ (landing + đề xuất + quản trị nội dung) | Landing tạm `/demo_1` (`CONGNGHE_LANDING_PUBLIC=false`); `/congnghe` & `/phongcongnghe` ẩn → `/dashboard`; đề xuất vẫn `/congnghe/de-xuat*` | ✅ → docs/CONGNGHE_CONTENT.md |
| AI Accounts (PĐX, TK, chi phí, analytics) | `/ai-accounts` | ✅ → docs/AI_ACCOUNTS.md |
| Org teams / danh bạ UI | `/org-teams`, `/members` | ❌ Đã gỡ UI (2026-07) — bảng OrgTeam tạm giữ; chờ HRM API |
| Profile (hồ sơ cá nhân) | `/profile` | ✅ Hoàn thành |
| System settings (super_admin) | `/settings` | ✅ → docs/SYSTEM_CONFIG.md |
| Workspace config hub (scoped theo PB) | `/workspace-config` | ✅ → docs/WORKSPACE_CONFIG.md |
| Evaluation criteria (super_admin CRUD; hub.view đọc scoped) | `/workspace-config/evaluation` | ✅ → docs/EVALUATION_CONFIG.md |
| Daily Report | `/daily-reports` | ✅ Hoàn thành → docs/DAILY_REPORT.md |
| Blocker Tracking | `/blockers` | ✅ Hoàn thành |
| Bug Tracking | — | ❌ Đã gỡ (2026-06) — dùng Feedback / Blocker |
| Feedback | `/feedback` | ✅ Hoàn thành |
| Department (mutate API, không Index) | `/departments` POST/PUT/PATCH/DELETE | ✅ Model + FormModal; Index UI gỡ |
| Comments & Reactions | morph (task, blocker, feedback) | ✅ Hoàn thành |
| Notification System (in-app bell + drawer) | `/notifications` | ✅ Hoàn thành |
| Notification Dispatcher (centralized fan-out) | (service layer) | ✅ Hoàn thành |
| Knowledge Base (Wiki) | `/knowledge-base` | ✅ Triển khai v1 → docs/KNOWLEDGE_BASE.md |
| Contract Lifecycle (CLM) | `/contracts` | ✅ Hoàn thành → docs/CONTRACT_MANAGEMENT.md |
| Credential Vault | `/credentials` | ✅ Hoàn thành → docs/CREDENTIAL_MANAGEMENT.md |
| Performance Analytics & Audit | `/performance` | ✅ Hoàn thành → docs/PERFORMANCE_ANALYTICS.md |
| Audit Trail (security log viewer) | `/audit` | ✅ Hoàn thành (admin/super) |
| RBAC / Permission Matrix | `/settings/permissions` | ✅ Hoàn thành → docs/PERMISSIONS.md |
| Onboarding Tour | `/onboarding` | ✅ Hoàn thành → docs/ONBOARDING.md |
| Team Dashboard | — | 🔄 Đang phát triển |
| Account Settings | — | 📋 Kế hoạch |
