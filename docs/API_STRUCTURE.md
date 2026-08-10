# API STRUCTURE — VA Workspace

## 1. Kiến Trúc API Hiện Tại

**Loại:** Chủ yếu Inertia.js Server-Side Routes + một phần JSON API (Notifications)a

Đa số routes trả về Inertia responses. **Ngoại lệ:** Notification (và một số endpoint AI/Credential) trả về `JsonResponse` để hỗ trợ polling và lazy loading.

```
routes/web.php      ← Loader mỏng: wire 2 nhóm middleware (guest / auth) rồi require web/*.php
routes/web/*.php    ← partial theo domain (auth, dashboard, projects, contracts, workspace-config, …)
routes/api.php      ← Rỗng (chưa sử dụng)
```

> Route được tách theo vùng chức năng vào `routes/web/{domain}.php`; mỗi partial tự `use` controller và đăng ký route trong nhóm middleware đang active. Map partial → domain: [`FOLDER_STRUCTURE.md`](FOLDER_STRUCTURE.md) §3.

> **Lưu ý pattern:** `NotificationController` là controller đầu tiên dùng `JsonResponse` thay vì Inertia. Frontend dùng `fetch`/axios trực tiếp để poll notifications.

### 1.1 Ngoại lệ JSON có chủ đích (TD-017)

| Lý do | Chi tiết |
|-------|----------|
| **Polling / lazy load** | Inbox cần cursor pagination và unread count mà không reload trang |
| **Endpoint** | `GET /notifications`, `GET /notifications/unread-count`, `POST /notifications/{id}/read`, … |
| **Frontend** | `useNotifications.js` — axios/fetch, không Inertia |
| **Auth** | Cùng session `system` guard + CSRF cookie như web routes |
| **Tương lai** | Thay WebSocket khi triển khai **LT-05** (Real-Time Collaboration) |

**Không** mở rộng pattern JSON cho CRUD chính — giữ Inertia cho pages và mutations full-page.

---

## 2. Danh Sách Tất Cả Routes

### 2.1 Authentication

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| GET | `/login` | LoginController@createPortal | guest | Cổng đăng nhập (SSO HRM hoặc Google UI); guest chưa đăng nhập được redirect về đây |
| GET | `/tech/login` | LoginController@createTech | guest | Cổng Workspace (whitelist) → sau đăng nhập về `/dashboard` |
| GET | `/auth/google` | GoogleAuthController@redirect | guest | OAuth Google (`prompt=select_account` + optional `hd`) — dùng khi SSO HRM tắt |
| GET | `/auth/google/callback` | GoogleAuthController@callback | guest | Callback OAuth |
| GET | `/auth/hrm` | HrmSsoController@redirect | guest | SSO HRM (`HRM_SSO_ENABLED`) — redirect `{HRM}/sso/authorize?client_id=workspace&state=…` |
| GET | `/auth/hrm/callback` | HrmSsoController@callback | guest | Nhận `?token=<JWT RS256>&state=…`, verify JWKS offline (`HrmSsoJwtVerifier`) → session guard `system` |
| POST | `/login`, `/tech/login` | LoginController@store* | guest | Chỉ khi `config('va.password_login_enabled')` |
| GET/POST | `/lh36` | HiddenAdminLoginController | guest | Đăng nhập admin ẩn (E2E/dev) |
| POST | `/logout` | LoginController@destroy | auth | Đăng xuất |

### 2.2 Dashboard

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| GET | `/` | redirect | auth | Redirect → `/dashboard` (guest `/` → middleware auth → `/login`). Landing CN tạm ẩn. |
| GET | `/dashboard` | HubDashboardController (invokable) | auth | Trang tổng quan: chào mừng, `summary` (KPI strip), xu hướng hoạt động, tuân thủ báo cáo, cảnh báo, lưới module (chi tiết công việc tại `/work`) |
| GET | `/work` | WorkDashboardController (invokable) | auth | Dashboard công việc cá nhân (`work-dashboard`) |
| GET | `/my-work` | MyWorkController (invokable) | auth | Việc của tôi — Inertia `MyWork/Index` (self / `?member=` / `?scope=team`) |
| GET | `/my-work/member/{employee}` | MyWorkController@memberTasks | auth | JSON buckets + summary cho modal «Xem nhanh» thành viên (`my-work.member`) |

### 2.2.3 Hiệu suất & Audit

Gate `performance.view` (`admin`, `lead`, `viewer`). Chi tiết module: [`docs/PERFORMANCE_ANALYTICS.md`](PERFORMANCE_ANALYTICS.md).

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| GET | `/performance` | PerformanceDashboardController | auth + gate | Executive dashboard |
| GET | `/performance/audit` | PerformanceAuditController@index | auth + gate | Danh sách audit nhân sự; query: `period`, `date`, `department`, `team`, `project`, `status[]`, `q`, `kpi`, `per_page`, `page` |
| GET | `/performance/audit/{employee}` | PerformanceAuditController@show | auth + gate | Timeline audit một nhân sự; query bộ lọc kỳ giống index |

### 2.2.1 Phòng Công Nghệ (cổng nội bộ)

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| GET | `/demo_1` | CongngheController | auth | Landing Phòng Công Nghệ (tạm — path demo). `name=congnghe` |
| GET | `/congnghe` | redirect | auth | Tạm ẩn → `/dashboard` (bật lại bằng `CONGNGHE_LANDING_PUBLIC=true`) |
| GET | `/phongcongnghe` | redirect | auth | Tạm ẩn → `/dashboard` |
| GET | `/congnghe/de-xuat` | CongngheSoftwareProposalController@create | auth | Form đề xuất giải pháp phần mềm |
| POST | `/congnghe/de-xuat` | CongngheSoftwareProposalController@store | auth | Body: `department_id` (phòng ban active trong `departments`), lưu tên phòng vào `congnghe_software_proposals.department` + file `public/congnghe/proposals/{id}`; email tới `config('va.congnghe_proposal_email')` |
| GET | `/congnghe/de-xuat-cua-toi` | CongngheSoftwareProposalController@index | auth | Đề xuất của người gửi; query: `q`, `status`, `department`, `from`, `to`, `email_sent` (`0`\|`1`), `acknowledged` (`0`\|`1`), `has_attachments` (`0`\|`1`), `per_page`, `page` |
| GET | `/congnghe/de-xuat-cua-toi/{proposal}` | CongngheSoftwareProposalController@show | auth (`viewAsSubmitter` — chỉ người gửi) | Chi tiết đề xuất của người gửi |
| GET | `/congnghe/de-xuat-cua-toi/{proposal}/attachments/{attachment}/file` | CongngheSoftwareProposalAttachmentController@file | auth (`viewAsSubmitter` trên route mine) | Tải file đính kèm (URL trong Resource cho người gửi) |
| GET | `/congnghe/proposals` | CongngheSoftwareProposalManagementController@index | auth (admin, lead) | Query: `q`, `status`, `department`, `email_pending` (`1`), `group` (`department`\|`none`), `per_page`, `page`; props `summary`, `options.departments` |
| GET | `/congnghe/proposals/{proposal}` | CongngheSoftwareProposalManagementController@show | auth (admin, lead) | Chi tiết |
| PUT | `/congnghe/proposals/{proposal}` | CongngheSoftwareProposalManagementController@update | auth (admin, lead) | Cập nhật trạng thái; trạng thái `rejected` bắt buộc `rejection_reason` (≥10 ký tự) và gửi email tới `submitter_email` |
| GET | `/congnghe/proposals/{proposal}/attachments/{attachment}/file` | CongngheSoftwareProposalAttachmentController@file | auth (owner hoặc admin/lead) | Tải file đính kèm |

### 2.2.2 Quản trị nội dung /congnghe

Chi tiết merge section: [`docs/CONGNGHE_CONTENT.md`](CONGNGHE_CONTENT.md).

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| GET | `/congnghe/quan-tri` | CongngheAdminController@index | auth (admin) | Editor section |
| PUT | `/congnghe/quan-tri/order` | CongngheAdminController@reorder | auth (admin) | Sắp thứ tự section |
| PUT | `/congnghe/quan-tri/sections/{section}` | CongngheAdminController@update | auth (admin) | Lưu override |
| POST | `/congnghe/quan-tri/sections/{section}/reset` | CongngheAdminController@reset | auth (admin) | Về default config |

### 2.3 Notifications ✨ MỚI — JSON API

> Các endpoints này trả **JsonResponse** (không phải Inertia). Frontend dùng axios/fetch.

| Method | URI | Controller | Response | Mô Tả |
|---|---|---|---|---|
| GET | `/notifications` | NotificationController@index | JSON | Danh sách (cursor pagination, filters: tab/category/priority/search/from/to) |
| GET | `/notifications/unread-count` | NotificationController@unreadCount | JSON | Số thông báo chưa đọc |
| GET | `/notifications/preferences` | NotificationController@preferences | JSON | Lấy cài đặt + danh sách types |
| PUT | `/notifications/preferences` | NotificationController@updatePreferences | JSON | Cập nhật cài đặt |
| GET | `/notifications/actors` | NotificationController@actors | JSON | Danh sách actors (để filter) |
| GET | `/notifications/manage` | NotificationManagementController | Inertia | Trang quản lý notifications |
| POST | `/notifications/read-all` | NotificationController@markAllRead | JSON | Đánh dấu tất cả đã đọc |
| POST | `/notifications/bulk` | NotificationController@bulk | JSON | Thao tác hàng loạt (read/acknowledge) |
| POST | `/notifications/{notification}/read` | NotificationController@markRead | JSON | Đánh dấu 1 thông báo đã đọc |
| POST | `/notifications/{notification}/acknowledge` | NotificationController@acknowledge | JSON | Acknowledge (critical alerts) |
| POST | `/notifications/{notification}/assign` | NotificationController@assign | JSON | Assign thông báo cho người khác |

### 2.4 Daily Reports

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| GET | `/daily-reports` | DailyReportController@index | auth | Lịch sử báo cáo (filters: `q`, `status`, `project_id`, `employee_ids[]`, `grade`, `from`, `to`, `late`, `group`; trả `summary` kèm `trend`/`completion_rate`) |
| GET | `/daily-reports/export-data` | DailyReportController@exportData | auth | **JSON** — toàn bộ kết quả lọc (không phân trang, ≤5000) cho Excel 7 sheet client-side. Cùng filters như index; member tự giới hạn báo cáo của mình |
| GET | `/daily-reports/today` | DailyReportController@today | auth | Form báo cáo hôm nay |
| POST | `/daily-reports` | DailyReportController@store | auth | Tạo báo cáo mới |
| GET | `/daily-reports/{report}` | DailyReportController@show | auth | Xem báo cáo |
| PUT | `/daily-reports/{report}` | DailyReportController@update | auth | Sửa báo cáo |
| POST | `/daily-reports/{report}/submit` | DailyReportController@submit | auth | Nộp báo cáo |
| GET | `/daily-reports/review` | DailyReportReviewController@index | auth | Hàng chờ chấm (`queue`, `q`, `employee_id`) |
| POST | `/daily-reports/review/bulk-score` | DailyReportReviewController@bulkScore | auth | Duyệt hàng loạt (max 50) |
| POST | `/daily-reports/review/bulk-reject` | DailyReportReviewController@bulkReject | auth | Trả lại hàng loạt (max 50) |
| POST | `/daily-reports/{report}/score` | DailyReportReviewController@score | auth | Chấm điểm |
| POST | `/daily-reports/{report}/reject` | DailyReportReviewController@reject | auth | Từ chối / trả về |

### 2.4 Projects

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| GET | `/projects` | ProjectController@index | auth | Danh sách dự án |
| GET | `/projects/create` | ProjectController@create | auth | Form tạo dự án |
| POST | `/projects` | ProjectController@store | auth | Tạo dự án |
| GET | `/projects/{project}` | ProjectController@show | auth | Dashboard dự án |
| GET | `/projects/{project}/edit` | ProjectController@edit | auth | Form sửa dự án |
| PUT | `/projects/{project}` | ProjectController@update | auth | Cập nhật dự án |
| PATCH | `/projects/{project}/type` | ProjectController@updateType | auth | Sửa loại dự án (nhanh) |
| PATCH | `/projects/{project}/department` | ProjectController@updateDepartment | auth | Sửa phòng ban (nhanh) |
| POST | `/projects/{project}/duplicate` | ProjectController@duplicate | auth | Nhân bản dự án |
| DELETE | `/projects/{project}` | ProjectController@destroy | auth | Xóa dự án |

### 2.5 Sprints

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| POST | `/projects/{project}/sprints` | SprintController@store | auth | Tạo sprint |
| PATCH | `/projects/{project}/sprints/reorder` | SprintController@reorder | auth | Sắp xếp thứ tự sprint |
| PUT | `/projects/{project}/sprints/{sprint}` | SprintController@update | auth | Sửa sprint |
| DELETE | `/projects/{project}/sprints/{sprint}` | SprintController@destroy | auth | Xóa sprint |
| POST | `/projects/{project}/email/daily-summary` | EmailNotificationController@dailySummary | auth | Xếp hàng email tổng hợp ngày |
| POST | `/projects/{project}/sprints/{sprint}/email/summary` | EmailNotificationController@sprintSummary | auth | Xếp hàng email tổng hợp sprint |

### 2.6 Tasks

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| GET | `/projects/{project}/tasks/{task}` | TaskController@show | auth | Redirect deep link → `projects.show` + `?task=` |
| POST | `/projects/{project}/tasks` | TaskController@store | auth | Tạo task |
| POST | `/projects/{project}/tasks/bulk` | TaskController@bulkStore | auth | Tạo nhiều tasks (defaults + titles) |
| POST | `/projects/{project}/tasks/import` | TaskController@import | auth | Nhập Excel Sprint (bulk, max 200 dòng) |
| POST | `/projects/{project}/tasks/{task}/subtasks` | TaskController@storeSubtask | auth | Tạo subtask |
| PUT | `/projects/{project}/tasks/{task}` | TaskController@update | auth | Sửa task |
| PATCH | `/projects/{project}/tasks/{task}` | TaskController@updateStatus | auth | Cập nhật status |
| DELETE | `/projects/{project}/tasks/{task}` | TaskController@destroy | auth | Xóa task |
| POST | `/projects/{project}/tasks/{task}/watchers/toggle` | TaskWatcherController@toggle | auth | Theo dõi/bỏ theo dõi |
| POST | `/projects/{project}/tasks/{task}/attachments` | TaskAttachmentController@store | auth | Upload file |
| DELETE | `/projects/{project}/tasks/{task}/attachments/{attachment}` | TaskAttachmentController@destroy | auth | Xóa file |

### 2.7 Epics

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| POST | `/projects/{project}/epics` | EpicController@store | auth | Tạo epic |

### 2.8 Worklogs

> Worklog được nested dưới task, không phải project trực tiếp.

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| POST | `/projects/{project}/tasks/{task}/worklogs` | WorklogController@store | auth | Ghi giờ làm |
| DELETE | `/projects/{project}/tasks/{task}/worklogs/{worklog}` | WorklogController@destroy | auth | Xóa worklog |

### 2.9 Project Members

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| POST | `/projects/{project}/members` | ProjectMemberController@store | auth | Thêm thành viên |
| PUT | `/projects/{project}/members/{employee}` | ProjectMemberController@update | auth | Sửa thành viên |
| DELETE | `/projects/{project}/members/{employee}` | ProjectMemberController@destroy | auth | Xóa thành viên |

### 2.10 Project Attachments

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| GET | `/projects/{project}/attachments/{attachment}/file` | ProjectAttachmentController@file | auth | Tải/xem file (stream) |
| POST | `/projects/{project}/attachments` | ProjectAttachmentController@store | auth | Upload / thư mục / tạo file trống `.txt` / link |
| PUT | `/projects/{project}/attachments/{attachment}` | ProjectAttachmentController@update | auth | Đổi tên (`title`), sửa nội dung text (`content`), notes, link, thay file; Resource có `preview_snippet` cho file text |
| DELETE | `/projects/{project}/attachments/{attachment}` | ProjectAttachmentController@destroy | auth | Xóa tài liệu |

### 2.11 Blockers

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| GET | `/blockers` | BlockerController@index | auth | Danh sách test case |
| POST | `/blockers` | BlockerController@store | auth | Tạo test case |
| POST | `/blockers/import` | BlockerController@import | auth | Nhập hàng loạt |
| POST | `/blockers/bulk` | BlockerController@bulk | auth | Thao tác hàng loạt |
| PUT | `/blockers/{blocker}` | BlockerController@update | auth | Sửa test case |
| DELETE | `/blockers/{blocker}` | BlockerController@destroy | auth | Xóa test case |
| POST | `/blockers/{blocker}/attachments` | BlockerController@storeAttachment | auth | Upload file |
| DELETE | `/blockers/{blocker}/attachments/{attachment}` | BlockerController@destroyAttachment | auth | Xóa file |

### 2.12 Feedback

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| GET | `/feedback` | FeedbackController@index | auth | Danh sách góp ý |
| POST | `/feedback` | FeedbackController@store | auth | Gửi góp ý |
| GET | `/feedback/{feedback}` | FeedbackController@show | auth | Chi tiết góp ý |
| PUT | `/feedback/{feedback}` | FeedbackController@update | auth | Sửa góp ý |
| DELETE | `/feedback/{feedback}` | FeedbackController@destroy | auth | Xóa góp ý |

**Query `GET /feedback`:** `status`, `category`, `project_id`, `priority`, `assignee_id`, `rating`, `mine`, `scope`, `q` (title/code/description), `created_from`, `created_to` (lọc theo `created_at`), `per_page`, `page`.

### 2.14 Comments

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| POST | `/comments` | CommentController@store | auth | Thêm comment |
| PUT | `/comments/{comment}` | CommentController@update | auth | Sửa comment |
| DELETE | `/comments/{comment}` | CommentController@destroy | auth | Xóa comment |
| POST | `/comments/{comment}/react` | CommentController@react | auth | React emoji |

### 2.15 Departments (mutate API)

Không còn trang danh sách `/departments` (org directory chuyển sang HRM). API còn để `DepartmentFormModal` / gán phòng ban trên dự án.

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| POST | `/departments` | DepartmentController@store | auth | Tạo phòng ban; body gồm `member_ids[]` (tối đa 200) |
| PUT | `/departments/{department}` | DepartmentController@update | auth | Sửa phòng ban; đồng bộ `member_ids` (trưởng phòng tự nằm trong danh sách) |
| PATCH | `/departments/{department}/toggle` | DepartmentController@toggleStatus | auth | Bật/tắt hoạt động |
| DELETE | `/departments/{department}` | DepartmentController@destroy | auth | Xóa phòng ban |

> **Org teams UI đã gỡ** (`/org-teams*`). Bảng `org_teams` / `LedTeamScope` / `OrgTeamTreeBuilder` vẫn dùng nội bộ (My Work, Performance, Congnghe) — nguồn dữ liệu sẽ thay bằng mock/API HRM.

### 2.16 Knowledge Base (Tri thức)

Prefix `knowledge-base.`, middleware `auth`. Chi tiết: [`docs/KNOWLEDGE_BASE.md`](KNOWLEDGE_BASE.md) §9.

| Method | URI | Controller | Response | Mô Tả |
|---|---|---|---|---|
| GET | `/knowledge-base` | KbArticleController@index | Inertia | Danh sách + datagrid (lọc danh mục trên toolbar) |
| GET | `/knowledge-base/blog` | KbArticleController@blog | Inertia | Blog + sidebar (chuyên mục, mới, thẻ) |
| GET | `/knowledge-base/export-data` | KbArticleController@exportData | JSON | Export client (≤200) |
| GET | `/knowledge-base/articles/create` | KbArticleController@create | Inertia | |
| POST | `/knowledge-base/articles` | KbArticleController@store | Redirect | |
| GET | `/knowledge-base/articles/{article}` | KbArticleController@show | Inertia | Binding **slug** |
| GET | `/knowledge-base/articles/{article}/edit` | KbArticleController@edit | Inertia | |
| PUT | `/knowledge-base/articles/{article}` | KbArticleController@update | Redirect | |
| DELETE | `/knowledge-base/articles/{article}` | KbArticleController@destroy | Redirect | |
| POST | `/knowledge-base/articles/{article}/favorite` | KbArticleController@toggleFavorite | Redirect | |
| POST | `/knowledge-base/articles/{article}/read` | KbArticleController@markRead | Redirect | |
| POST | `/knowledge-base/articles/{article}/attachments` | KbArticleController@storeAttachment | Redirect | |
| POST | `/knowledge-base/articles/{article}/images` | KbArticleController@storeImage | JSON | TipTap inline `{ url }` |
| POST | `/knowledge-base/articles/{article}/gallery` | KbArticleController@storeGalleryImage | Redirect | |
| PATCH | `/knowledge-base/gallery/{image}` | KbArticleController@updateGalleryImage | Redirect | |
| DELETE | `/knowledge-base/gallery/{image}` | KbArticleController@destroyGalleryImage | Redirect | |
| GET | `/knowledge-base/attachments/{attachment}/file` | KbArticleController@attachmentFile | Stream | |
| GET | `/knowledge-base/images/{image}/file` | KbArticleController@imageFile | Stream | |

### 2.17 AI Accounts (Inertia + JSON)

Prefix Inertia `ai-accounts.*`, JSON `api.ai-accounts.*` (middleware `auth`). **Route map đầy đủ:** [`docs/AI_ACCOUNTS.md`](AI_ACCOUNTS.md).

| Method | URI | Response | Mô Tả |
|---|---|---|---|
| GET | `/ai-accounts`, `/ai-accounts/dashboard`, `/analytics`, `/cost-report` | Inertia | Trang workspace |
| * | `/api/ai-accounts/*` | JSON | CRUD TK, PĐX, payment, analytics |
| POST/GET/PATCH | `/api/ai-accounts/proposal-scans*` | JSON | Số hóa PĐX bằng OCR (upload, review, confirm, serve file) — chi tiết: [`docs/AI_ACCOUNTS.md`](AI_ACCOUNTS.md) mục «Số hóa Phiếu Đề Xuất» |

### 2.18 Hồ sơ cá nhân

| Method | URI | Controller | Mô Tả |
|---|---|---|---|
| GET | `/profile` | ProfileController | Hồ sơ cá nhân (read-only): refresh mirror HRM; kỹ năng chỉ xem

> Danh bạ `/members*` đã gỡ — org directory sẽ lấy từ HRM.

### 2.19 Cấu hình hệ thống

Chi tiết: [`docs/SYSTEM_CONFIG.md`](SYSTEM_CONFIG.md).

| Method | URI | Controller | Mô Tả |
|---|---|---|---|
| GET | `/settings` | SystemSettingController@index | Nhóm cấu hình |
| PUT | `/settings/{group}` | SystemSettingController@update | `general`, `auth`, `telegram`, `email`, `permissions` |
| GET/PUT/POST | `/settings/email-templates/*` | SystemSettingController | Mẫu email |

### 2.x Cấu hình workspace (super-admin)

| Method | URI | Controller | Mô tả |
|--------|-----|------------|--------|
| GET | `/workspace-config` | WorkspaceConfigController@index | Hub workspace theo phòng ban (`include_archived=1` khi hub.manage) |
| POST | `/workspace-config/ensure-bulk` | WorkspaceProfileController@ensureBulk | Kích hoạt hàng loạt (max 50 mã, hub.manage) |
| GET | `/workspace-config/w/{departmentCode}` | WorkspaceProfileController@show | Shell workspace PB + module catalog + checklist |
| PATCH | `/workspace-config/w/{departmentCode}` | WorkspaceProfileController@update | Cập nhật notes / status (active\|draft\|archived) |
| POST | `/workspace-config/w/{departmentCode}/ensure` | WorkspaceProfileController@ensure | Kích hoạt profile (hub.manage) |

Chi tiết module: [`WORKSPACE_CONFIG.md`](WORKSPACE_CONFIG.md).

### 2.x Cấu hình tiêu chí đánh giá (super-admin)

| Method | URI | Controller | Mô tả |
|--------|-----|------------|--------|
| GET | `/workspace-config/daily-report-scoring` | DailyReportScoringConfigController@edit | Form trọng số BC ngày theo PB |
| PUT | `/workspace-config/daily-report-scoring` | DailyReportScoringConfigController@update | Lưu trọng số theo PB |
| GET | `/workspace-config/evaluation` | EvaluationCriterionController@index | Danh mục tiêu chí |
| POST | `/workspace-config/evaluation` | EvaluationCriterionController@store | Tạo tiêu chí |
| GET | `/workspace-config/evaluation/{evaluationCriterion}` | EvaluationCriterionController@show | Chi tiết + lịch sử |
| PUT | `/workspace-config/evaluation/{evaluationCriterion}` | EvaluationCriterionController@update | Cập nhật |
| DELETE | `/workspace-config/evaluation/{evaluationCriterion}` | EvaluationCriterionController@destroy | Soft delete |
| GET | `/workspace-config/evaluation-templates` | EvaluationTemplateController@index | Danh sách mẫu đánh giá |
| GET | `/workspace-config/evaluation-templates/create` | EvaluationTemplateController@create | Form tạo mẫu (trang mới) |
| POST | `/workspace-config/evaluation-templates` | EvaluationTemplateController@store | Tạo mẫu → redirect Show |
| POST | `/workspace-config/evaluation-templates/import` | EvaluationTemplateController@import | Nhập Excel (max 200) |
| GET | `/workspace-config/evaluation-templates/export-logs` | EvaluationTemplateController@exportLogs | JSON lịch sử xuất |
| POST | `/workspace-config/evaluation-templates/export-logs` | EvaluationTemplateController@recordExport | Ghi lịch sử xuất + audit |
| POST | `/workspace-config/evaluation-templates/{evaluationTemplate}/duplicate` | EvaluationTemplateController@duplicate | Nhân bản |
| GET | `/workspace-config/evaluation-templates/{evaluationTemplate}` | EvaluationTemplateController@show | Chi tiết mẫu |
| PUT | `/workspace-config/evaluation-templates/{evaluationTemplate}` | EvaluationTemplateController@update | Cập nhật |
| DELETE | `/workspace-config/evaluation-templates/{evaluationTemplate}` | EvaluationTemplateController@destroy | Soft delete |
| GET | `/workspace-config/evaluation-forms` | EvaluationFormController@index | Danh sách phiếu đánh giá |
| GET | `/workspace-config/evaluation-forms/create` | EvaluationFormController@create | Wizard tạo phiếu |
| POST | `/workspace-config/evaluation-forms` | EvaluationFormController@store | Tạo phiếu |
| POST | `/workspace-config/evaluation-forms/types` | EvaluationFormController@storeType | Thêm loại ĐG nhanh |
| GET | `/workspace-config/evaluation-forms/templates/{evaluationTemplate}/criteria` | EvaluationFormController@templateCriteria | JSON tiêu chí từ mẫu |
| POST | `/workspace-config/evaluation-forms/{evaluationForm}/open` | EvaluationFormScoringController@open | Mở chấm (draft→active) |
| POST | `/workspace-config/evaluation-forms/{evaluationForm}/close` | EvaluationFormScoringController@close | Khóa kỳ |
| POST | `/workspace-config/evaluation-forms/{evaluationForm}/reopen` | EvaluationFormScoringController@reopen | Mở lại |
| GET | `/workspace-config/evaluation-forms/{evaluationForm}/scoring` | EvaluationFormScoringController@index | Tổng quan chấm điểm |
| GET | `/workspace-config/evaluation-forms/{evaluationForm}/scoring/{assignee}` | EvaluationFormScoringController@show | Form chấm điểm |
| PUT | `/workspace-config/evaluation-forms/{evaluationForm}/scoring/{assignee}` | EvaluationFormScoringController@save | Lưu nháp điểm |
| POST | `/workspace-config/evaluation-forms/{evaluationForm}/scoring/{assignee}/submit` | EvaluationFormScoringController@submit | Nộp điểm |
| GET | `/workspace-config/evaluation-forms/{evaluationForm}/edit` | EvaluationFormController@edit | Sửa phiếu |
| PUT | `/workspace-config/evaluation-forms/{evaluationForm}` | EvaluationFormController@update | Cập nhật |
| DELETE | `/workspace-config/evaluation-forms/{evaluationForm}` | EvaluationFormController@destroy | Soft delete |

Chi tiết: [`EVALUATION_CONFIG.md`](EVALUATION_CONFIG.md) · module cha [`WORKSPACE_CONFIG.md`](WORKSPACE_CONFIG.md).


### 2.20 Realtime & Comments (cross-cutting)

| Method | URI | Controller | Mô Tả |
|---|---|---|---|
| GET | `/realtime/thread-token` | RealtimeController@threadToken | Token thread bình luận |
| POST/PUT/DELETE | `/comments`, `/comments/{comment}` | CommentController | Morph Task, Blocker, Feedback, KB, … |
| POST | `/comments/{comment}/react` | CommentController@react | Reaction |

Doc vận hành: `_dev/realtime.md`.

---

## 3. API Grouping (Theo Domain)

```
Auth Group
├── /login, /tech/login, /auth/hrm, /auth/google, /logout

Dashboard Group
└── /dashboard

Congnghe Group
├── /congnghe                         (landing — Inertia)
├── /congnghe/de-xuat*                (form + mine + attachments)
├── /congnghe/proposals/*             (admin/lead quản lý)
└── /congnghe/quan-tri/*              (admin content CMS)

Notification Group [JSON API] ✨ MỚI
├── /notifications               (index — cursor paging, multi-filter)
├── /notifications/unread-count  (badge count)
├── /notifications/preferences   (get/update user prefs)
├── /notifications/actors        (filter helpers)
├── /notifications/manage        (Inertia management page)
├── /notifications/read-all      (bulk mark read)
├── /notifications/bulk          (bulk read/acknowledge)
└── /notifications/{id}/*        (read, acknowledge, assign)

Daily Report Group
├── /daily-reports             (index, store)
├── /daily-reports/export-data (JSON — toàn bộ kết quả lọc cho Excel 7 sheet)
├── /daily-reports/today       (today form)
├── /daily-reports/review      (review queue)
└── /daily-reports/{id}        (show, update, submit, score, reject)

Project Group
├── /projects               (index, create, store)
├── /projects/{id}          (show, edit, update, delete, duplicate, type, department)
├── /projects/{id}/sprints  (CRUD)
├── /projects/{id}/tasks    (CRUD + bulk + subtasks + watchers + attachments)
├── /projects/{id}/epics    (store only)
├── /projects/{id}/tasks/{id}/worklogs (store, destroy)
├── /projects/{id}/members  (store; update/destroy via {employee})
└── /projects/{id}/attachments (store, update, destroy — project documents)

Issue Tracking Group
├── /blockers               (index, store, import, bulk, CRUD + attachments)
└── /feedback               (CRUD)

Contract Management Group (CLM)
├── /contracts              (index, dashboard, cost, reports)
├── /contracts/export       (JSON for client Excel)
├── /contracts/import       (POST bulk ≤200)
├── /contracts/vendors/*    (CRUD + import + reviews)
├── /contracts/categories/* (store, update, destroy)
├── /contracts/{id}         (show, update, destroy)
├── /contracts/{id}/renewals             (POST — tạo hợp đồng phụ lục mới, status=addendum)
├── /contracts/{id}/finances             (POST store)
├── /contracts/{id}/finances/{fid}       (PUT update, DELETE destroy)
├── /contracts/{id}/reviews              (POST store — đánh giá gắn contract_id + vendor_id)
├── /contracts/{id}/reviews/{rid}        (DELETE destroy)
└── /contracts/{id}/attachments/{aid}    (GET file, POST store, DELETE destroy)

Communication Group
├── /comments                         (morph threads)
└── /realtime/thread-token            (JSON)

Organization Group
├── /departments                      (mutate API: store/update/toggle/destroy — không Index UI)
└── /profile                          (self)

AI Accounts Group
├── /ai-accounts/*                    (Inertia pages)
└── /api/ai-accounts/*                (JSON workspace)

Credential Management Group
├── /credentials                        (index, create, edit, show — Inertia)
├── POST /credentials/import            (bulk ≤200)
└── /api/credentials/{id}/*             (password reveal, ACL, audit, relations, access-requests)

Settings Group (admin)
└── /settings/*                       (groups + email templates)

Workspace Config Group
├── /workspace-config                 (hub — workspace theo phòng ban)
├── /workspace-config/ensure-bulk     (POST bulk ensure)
├── /workspace-config/w/{code}        (shell + PATCH notes/status)
└── /workspace-config/evaluation/*    (cấu hình đánh giá, scoped theo PB)
└── /workspace-config/evaluation-templates/*  (mẫu đánh giá + lịch sử xuất)
└── /workspace-config/evaluation-forms/*      (phiếu đánh giá MVP)

Knowledge Base Group
├── /knowledge-base                    (index — Inertia)
├── /knowledge-base/blog               (blog + sidebar — Inertia)
├── /knowledge-base/export-data        (JSON — export ≤200)
├── /knowledge-base/articles/*         (CRUD — {article} slug)
├── /knowledge-base/articles/{id}/favorite|read|attachments|images|gallery
├── /knowledge-base/gallery/{image}    (PATCH alt, DELETE)
└── /knowledge-base/attachments|images/{id}/file (stream)

Doc chi tiết: [`docs/FLOWS_AND_DOCS_MAP.md`](FLOWS_AND_DOCS_MAP.md), [`docs/KNOWLEDGE_BASE.md`](KNOWLEDGE_BASE.md), [`docs/AI_ACCOUNTS.md`](AI_ACCOUNTS.md).
```

---

## 4. Endpoint Ownership (Theo Role)

| Endpoint Group | admin | lead | member | viewer |
|---|---|---|---|---|
| Auth | ✅ | ✅ | ✅ | ✅ |
| Dashboard | ✅ | ✅ | ✅ | ✅ |
| Notifications (own) | ✅ | ✅ | ✅ | ✅ |
| Notifications (assign) | ✅ | ✅ | - | - |
| Daily Reports (own) | ✅ | ✅ | ✅ | - |
| Daily Reports (review) | ✅ | ✅ | - | - |
| Projects (view) | ✅ | ✅ | ✅ | ✅ |
| Projects (create/edit) | ✅ | ✅ | - | - |
| Projects (delete) | ✅ | - | - | - |
| Tasks/Sprints (manage) | ✅ | ✅ | ✅* | - |
| Worklogs (own) | ✅ | ✅ | ✅ | - |
| Blockers | ✅ | ✅ | ✅ | ✅* |
| Feedback | ✅ | ✅ | ✅ | ✅* |
| Comments | ✅ | ✅ | ✅ | - |
| Departments | ✅ | ✅* | - | - |
| Knowledge Base (read published) | ✅ | ✅ | ✅ | ✅* |
| Knowledge Base (authoring) | ✅ | ✅ | ✅* | - |

*\* = Xem/tạo được nhưng không xóa*

---

## 5. Naming Conventions

| Pattern | Ví Dụ |
|---|---|
| Resource plural noun | `/projects`, `/blockers`, `/feedback` |
| Nested resource | `/projects/{project}/tasks` |
| Custom action via verb | `/projects/{project}/duplicate` |
| Status toggle via PATCH | `PATCH /departments/{department}/toggle` |
| Sub-resource field update | `PATCH /projects/{project}/type` |
| Bulk actions | `POST /blockers/bulk`, `POST /projects/{project}/tasks/bulk` |
| Import | `POST /blockers/import`, `POST /projects/{project}/tasks/import` |

---

## 6. Response Format

### Inertia Response (Standard)
```php
// Page render
return Inertia::render('Project/Show', [
    'project' => new ProjectResource($project),
    'tasks'   => TaskResource::collection($tasks),
]);
```

### Redirect with Flash (After Mutation)
```php
return redirect()->back()->with('success', 'Đã lưu thành công.');
// Flash được share qua HandleInertiaRequests middleware
```

### Resource Format (ProjectResource)
```json
{
  "id": 1,
  "code": "PRJ-001",
  "name": "Tên dự án",
  "status": { "value": "active", "label": "Đang hoạt động", "color": "green" },
  "progress": 65,
  "manager": { "id": 1, "name": "Nguyễn Văn A", "avatar_path": null },
  "members": [...],
  "created_at": "2026-06-03T00:00:00.000000Z"
}
```

---

## 7. Đề Xuất REST API (Tương Lai)

Khi cần tích hợp mobile app hoặc third-party services, nên tạo REST API riêng:

```
routes/api.php (v1):
├── /api/v1/auth/login
├── /api/v1/auth/logout
├── /api/v1/projects      (JSON, stateless)
├── /api/v1/tasks
├── /api/v1/daily-reports
└── /api/v1/me            (current user profile)
```

Sử dụng Laravel Sanctum (đã cài sẵn) cho Bearer Token authentication.
