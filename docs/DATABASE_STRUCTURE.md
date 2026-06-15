# DATABASE STRUCTURE — VA QLDA

---

## 1. Tổng Quan

| Thông Tin | Giá Trị |
|---|---|
| Database Engine | MySQL |
| Table Prefix | `va_prd_` |
| Total Tables | ~42+ (core + KB + Coaching — migrations `2026_06_14_*`) |
| ORM | Laravel Eloquent |
| Soft Deletes | employees, tasks, bugs |
| UUID Support | daily_reports (+ có thể mở rộng) |
| Activity Log | Spatie Activity Log |

**Ghi chú (2026-06-15):** Module Talent Management đã gỡ — các bảng `employee_skills`, `certifications`, `performance_kpis`, `learning_items`, `feedback_reviews`, `succession_plans`, `career_levels` bị drop bởi migration `2026_06_15_140000_drop_talent_tables`. Kỹ năng hồ sơ lưu trên `employees.skills` (JSON) và `employees.meta.skill_details`.

---

## 2. Entity Relationship Overview

```
Department ──→ Employee ──→ SystemAccount
    │               │
    │               ├── Project (manager)
    │               ├── project_member (pivot)
    │               ├── Sprint (không trực tiếp)
    │               ├── Task (assignee, reporter, reviewer)
    │               ├── Worklog
    │               ├── Blocker (raised_by, owner)
    │               ├── Bug (reporter, assignee)
    │               ├── Feedback (reporter, assignee)
    │               ├── Comment (author)
    │               └── DailyReport
    │
    └── Project ──→ Sprint ──→ Task ──→ Worklog
                 │          │       ├── task_assignees
                 │          │       ├── task_watchers
                 │          │       ├── task_attachments
                 │          │       ├── task_activities
                 │          │       ├── task_dependencies
                 │          │       └── Comment (morph)
                 │          └── Epic (via project)
                 │
                 ├── Epic
                 ├── Blocker ──→ blocker_attachments
                 │           ├── blocker_activities
                 │           └── Comment (morph)
                 ├── Bug ──→ Comment (morph)
                 ├── Feedback ──→ Comment (morph)
                 └── ProjectAttachment ──→ project_attachment_activities
```

---

## 3. Schema Chi Tiết

### 3.1 va_prd_employees

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| code | varchar(50) | NO | Unique, e.g. EMP-001 |
| full_name | varchar(255) | NO | Họ và tên |
| email | varchar(255) | NO | Unique |
| phone | varchar(20) | YES | |
| avatar_path | varchar(500) | YES | Đường dẫn ảnh |
| role_title | varchar(255) | YES | Chức danh (Display only) |
| join_date | date | YES | Ngày vào làm |
| skills | json | YES | Mảng kỹ năng |
| is_active | tinyint(1) | NO | Default: 1 |
| meta | json | YES | Metadata tuỳ chỉnh |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |
| deleted_at | timestamp | YES | Soft delete |

**Indexes:** code (unique), email (unique)

---

### 3.2 va_prd_system_accounts

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| username | varchar(100) | NO | Unique, login name |
| password | varchar(255) | NO | Bcrypt hash |
| display_name | varchar(255) | NO | Tên hiển thị |
| role | varchar(20) | NO | Enum: admin/lead/member/viewer |
| employee_id | bigint UNSIGNED | YES | FK → employees |
| is_active | tinyint(1) | NO | Default: 1 |
| last_login_at | timestamp | YES | |
| remember_token | varchar(100) | YES | |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

**Indexes:** username (unique), employee_id (FK)

---

### 3.3 va_prd_departments

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| code | varchar(50) | NO | Unique, e.g. DEPT-IT |
| name | varchar(255) | NO | Tên phòng ban |
| color | varchar(50) | NO | Default: 'slate' |
| manager_id | bigint UNSIGNED | YES | FK → employees |
| sort_order | int | NO | Default: 0 |
| is_active | tinyint(1) | NO | Default: 1 |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

**Indexes:** code (unique), manager_id (FK)

### 3.3.1 va_prd_department_member

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| department_id | bigint UNSIGNED | NO | FK → departments |
| employee_id | bigint UNSIGNED | NO | FK → employees |
| joined_at | date | YES | Ngày gán vào phòng ban |
| is_active | tinyint(1) | NO | Default: 1 |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

**Indexes:** unique (department_id, employee_id), employee_id (FK)

---

### 3.4 va_prd_projects

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| code | varchar(50) | NO | Unique, e.g. PRJ-001 |
| name | varchar(255) | NO | Tên dự án |
| description | text | YES | |
| color | varchar(50) | YES | Brand color |
| status | varchar(20) | NO | planning/active/on_hold/completed/cancelled |
| type | varchar(20) | NO | rnd/maintenance/support/internal |
| scope | varchar(20) | NO | headquarters/regional/departmental |
| scope_regions | json | YES | Mảng vùng (khi scope=regional) |
| scope_departments | json | YES | Mảng phòng ban (khi scope=departmental) |
| start_date | date | YES | |
| due_date | date | YES | |
| budget | decimal(15,2) | YES | Ngân sách kế hoạch |
| actual_budget | decimal(15,2) | YES | Chi phí thực tế |
| manager_id | bigint UNSIGNED | YES | FK → employees |
| department_id | bigint UNSIGNED | YES | FK → departments |
| is_active | tinyint(1) | NO | Default: 1 |
| sort_order | int | NO | Default: 0 |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

**Indexes:** code (unique), manager_id, department_id (FK)

---

### 3.5 va_prd_project_member (Pivot)

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| project_id | bigint UNSIGNED | NO | FK → projects |
| employee_id | bigint UNSIGNED | NO | FK → employees |
| role | varchar(20) | NO | pm/lead/developer/qa/designer |
| rate_type | varchar(20) | NO | hourly/monthly |
| rate | decimal(10,2) | YES | Mức lương / giờ hoặc tháng |
| allocation | tinyint | YES | % thời gian phân bổ (0-100) |
| joined_at | date | YES | |
| is_active | tinyint(1) | NO | Default: 1 |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

**Indexes:** unique(project_id, employee_id)

---

### 3.6 va_prd_sprints

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| project_id | bigint UNSIGNED | NO | FK → projects |
| name | varchar(255) | NO | Sprint 1, Sprint 2... |
| goal | text | YES | Mục tiêu sprint |
| status | varchar(20) | NO | planned/active/completed |
| start_date | date | YES | |
| due_date | date | YES | |
| sort_order | int | NO | Default: 0 |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

**Indexes:** project_id (FK)

---

### 3.7 va_prd_epics

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| project_id | bigint UNSIGNED | NO | FK → projects |
| name | varchar(255) | NO | Tên epic/feature |
| color | varchar(50) | YES | |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

**Indexes:** unique(project_id, name)

---

### 3.8 va_prd_tasks

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| project_id | bigint UNSIGNED | NO | FK → projects |
| sprint_id | bigint UNSIGNED | YES | FK → sprints (nullable = backlog) |
| parent_id | bigint UNSIGNED | YES | FK → tasks (null = top-level) |
| epic_id | bigint UNSIGNED | YES | FK → epics |
| title | varchar(500) | NO | |
| description | longtext | YES | Rich HTML |
| status | varchar(20) | NO | todo/in_progress/review/done/cancelled |
| priority | varchar(20) | NO | critical/high/medium/low |
| phase | varchar(50) | YES | Phase/giai đoạn |
| is_milestone | tinyint(1) | NO | Default: 0 |
| assignee_id | bigint UNSIGNED | YES | FK → employees (primary assignee) |
| reporter_id | bigint UNSIGNED | YES | FK → employees |
| reviewer_id | bigint UNSIGNED | YES | FK → employees |
| start_date | datetime | YES | |
| work_started_at | datetime | YES | Khi thực sự bắt đầu làm |
| due_date | date | YES | |
| estimate_hours | decimal(5,2) | YES | Ước tính giờ làm |
| story_points | decimal(4,1) | YES | Story points |
| progress | tinyint UNSIGNED | NO | 0-100, default: 0 |
| order_column | int UNSIGNED | YES | Thứ tự trong sprint/board |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |
| deleted_at | timestamp | YES | Soft delete |

**Indexes:** project_id, sprint_id, parent_id, epic_id, assignee_id, reporter_id, reviewer_id (FK)

---

### 3.9 va_prd_task_assignees (Pivot)

| Column | Type | Description |
|---|---|---|
| task_id | bigint UNSIGNED | FK → tasks |
| employee_id | bigint UNSIGNED | FK → employees |

**PK:** (task_id, employee_id)

---

### 3.10 va_prd_task_watchers (Pivot)

| Column | Type | Description |
|---|---|---|
| task_id | bigint UNSIGNED | FK → tasks |
| employee_id | bigint UNSIGNED | FK → employees |
| created_at | timestamp | |
| updated_at | timestamp | |

**PK:** (task_id, employee_id)

---

### 3.11 va_prd_task_attachments

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| task_id | bigint UNSIGNED | NO | FK → tasks |
| uploaded_by_id | bigint UNSIGNED | NO | FK → employees |
| original_name | varchar(500) | NO | Tên file gốc |
| path | varchar(1000) | NO | Storage path |
| mime_type | varchar(100) | YES | |
| size | bigint UNSIGNED | YES | Bytes |
| is_image | tinyint(1) | NO | Default: 0 |
| version | smallint UNSIGNED | NO | Default: 1 |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

---

### 3.12 va_prd_task_activities

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| task_id | bigint UNSIGNED | NO | FK → tasks |
| employee_id | bigint UNSIGNED | YES | FK → employees |
| event | varchar(100) | NO | created/updated/status_changed... |
| description | text | YES | Human-readable change description |
| meta | json | YES | Before/after values |
| created_at | timestamp | YES | |

---

### 3.13 va_prd_task_dependencies

| Column | Type | Description |
|---|---|---|
| id | bigint UNSIGNED | PK |
| task_id | bigint UNSIGNED | FK → tasks (this task) |
| depends_on_id | bigint UNSIGNED | FK → tasks (predecessor) |
| created_at | timestamp | |
| updated_at | timestamp | |

**Indexes:** unique(task_id, depends_on_id)

---

### 3.14 va_prd_worklogs

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| task_id | bigint UNSIGNED | NO | FK → tasks |
| employee_id | bigint UNSIGNED | NO | FK → employees |
| date | date | NO | Ngày làm việc |
| hours | decimal(5,2) | NO | Số giờ |
| note | text | YES | Ghi chú |
| rate_snapshot | decimal(10,2) | YES | Rate tại thời điểm ghi |
| cost | decimal(12,2) | YES | hours × rate_snapshot |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

**Business Rule:** cost = hours × rate_snapshot (snapshot để tránh thay đổi rate ảnh hưởng lịch sử)

---

### 3.15 va_prd_blockers

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| code | varchar(20) | YES | Auto: RSK-001 |
| project_id | bigint UNSIGNED | NO | FK → projects |
| task_id | bigint UNSIGNED | YES | FK → tasks (liên quan task nào) |
| title | varchar(500) | NO | |
| description | text | YES | |
| root_cause | text | YES | Nguyên nhân gốc rễ |
| severity | varchar(20) | NO | blocker/critical/high/medium/low |
| status | varchar(20) | NO | open/in_progress/resolved/closed |
| raised_by_id | bigint UNSIGNED | YES | FK → employees (người phát hiện) |
| owner_id | bigint UNSIGNED | YES | FK → employees (người chịu TN) |
| raised_at | datetime | YES | |
| due_date | date | YES | Deadline xử lý |
| resolved_at | datetime | YES | |
| resolution | text | YES | Cách giải quyết |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

**Scopes:** open(), overdue()

---

### 3.16 va_prd_blocker_attachments

Tương tự task_attachments, FK → blockers.

### 3.17 va_prd_blocker_activities

Tương tự task_activities, FK → blockers.

---

### 3.18 va_prd_bugs *(đã gỡ module — migration `2026_06_14_120000_drop_bugs_module`)*

Bảng `bugs` và `bug_activities` không còn trên môi trường đã migrate. Schema lịch sử tham chiếu dưới đây.

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| code | varchar(20) | YES | Auto: BUG-0001 |
| project_id | bigint UNSIGNED | NO | FK → projects |
| task_id | bigint UNSIGNED | YES | FK → tasks (liên quan task nào) |
| title | varchar(500) | NO | |
| description | text | YES | |
| steps_to_reproduce | text | YES | |
| expected | text | YES | Expected behavior |
| actual | text | YES | Actual behavior |
| environment | varchar(255) | YES | dev/staging/production |
| severity | varchar(20) | NO | blocker/critical/major/minor |
| priority | varchar(20) | NO | critical/high/medium/low |
| status | varchar(20) | NO | open/in_progress/resolved/closed/wontfix |
| reporter_employee_id | bigint UNSIGNED | YES | FK → employees (nội bộ) |
| reporter_name | varchar(255) | YES | Tên người báo ngoài (external) |
| reporter_email | varchar(255) | YES | Email người báo ngoài |
| assignee_id | bigint UNSIGNED | YES | FK → employees |
| resolved_at | datetime | YES | |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |
| deleted_at | timestamp | YES | Soft delete |

---

### 3.19a va_prd_congnghe_software_proposals

Đề xuất giải pháp phần mềm từ cổng `/congnghe/de-xuat`.

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| reference_code | varchar(24) | YES | Mã CN-00001 (unique) |
| system_account_id | bigint UNSIGNED | YES | FK → system_accounts |
| submitter_name | varchar(120) | NO | |
| submitter_email | varchar(255) | NO | |
| department | varchar(160) | NO | |
| title | varchar(200) | NO | |
| content | text | NO | |
| status | varchar(32) | NO | new / triaged / in_progress / done / rejected |
| email_sent_at | timestamp | YES | |
| email_error | varchar(500) | YES | |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

**va_prd_congnghe_software_proposal_attachments:** `congnghe_software_proposal_id`, `original_name`, `path` (disk `public`), `mime_type`, `size`, `is_image`.

---

### 3.19 va_prd_feedbacks

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| code | varchar(20) | YES | Auto: FB-0001 |
| project_id | bigint UNSIGNED | YES | FK → projects (optional) |
| category | varchar(30) | NO | feature/improvement/bug_report/complaint |
| title | varchar(500) | NO | |
| description | text | YES | |
| rating | tinyint | YES | 1-5 |
| priority | varchar(20) | NO | critical/high/medium/low |
| status | varchar(20) | NO | new/reviewing/planned/completed/declined |
| reporter_employee_id | bigint UNSIGNED | YES | FK → employees |
| reporter_name | varchar(255) | YES | External reporter |
| reporter_email | varchar(255) | YES | External reporter |
| assignee_id | bigint UNSIGNED | YES | FK → employees |
| resolved_at | datetime | YES | |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

---

### 3.20 va_prd_comments (Polymorphic)

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| commentable_type | varchar(255) | NO | App\Models\Task / Bug / Blocker / Feedback |
| commentable_id | bigint UNSIGNED | NO | ID của entity |
| parent_id | bigint UNSIGNED | YES | FK → comments (reply to) |
| employee_id | bigint UNSIGNED | YES | FK → employees |
| author_name | varchar(255) | YES | External author |
| body | text | NO | Nội dung comment |
| reactions | json | YES | {"👍": [1,2,3], "❤️": [4]} |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

**Indexes:** (commentable_type, commentable_id), parent_id, employee_id

---

### 3.21 va_prd_project_attachments

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| project_id | bigint UNSIGNED | NO | FK → projects |
| category | varchar(50) | YES | Enum: ProjectAttachmentCategory |
| uploaded_by_id | bigint UNSIGNED | YES | FK → employees |
| updated_by_id | bigint UNSIGNED | YES | FK → employees |
| original_name | varchar(500) | NO | |
| notes | text | YES | |
| path | varchar(1000) | NO | |
| mime_type | varchar(100) | YES | |
| size | bigint UNSIGNED | YES | |
| is_image | tinyint(1) | NO | Default: 0 |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

### 3.22 va_prd_project_attachment_activities

Tương tự task_activities, FK → project_attachments.

---

### 3.23 va_prd_daily_reports

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| uuid | char(36) | NO | Unique UUID |
| employee_id | bigint UNSIGNED | NO | FK → employees |
| project_id | bigint UNSIGNED | YES | FK → projects (legacy field) |
| projects | json | YES | [{id, name, tasks:[]}] |
| date | date | NO | Ngày báo cáo |
| title | varchar(500) | YES | |
| goals_today | text | YES | Mục tiêu hôm nay |
| progress_update | text | YES | Cập nhật tiến độ |
| results_impact | text | YES | Kết quả & tác động |
| blockers | text | YES | Vướng mắc |
| improvement_suggestions | text | YES | Đề xuất cải tiến |
| highlights | text | YES | Điểm nổi bật |
| plan_tomorrow | text | YES | Kế hoạch ngày mai |
| status | varchar(20) | NO | draft/submitted/reviewed |
| is_late | tinyint(1) | NO | Default: 0 |
| submitted_at | datetime | YES | |
| reviewed_at | datetime | YES | |
| review_notes | text | YES | |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

**Indexes:** uuid (unique), unique(employee_id, date)

---

### 3.24 va_prd_daily_report_scores

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| report_id | bigint UNSIGNED | NO | Unique FK → daily_reports |
| task_completion | decimal(4,2) | YES | 0-10 |
| skill_score | decimal(4,2) | YES | 0-10 |
| attitude_score | decimal(4,2) | YES | 0-10 |
| kaizen_score | decimal(4,2) | YES | 0-10 |
| expertise_score | decimal(4,2) | YES | 0-10 |
| total_score | decimal(5,2) | YES | Tổng điểm |
| grade | varchar(1) | YES | A/B/C/D/E/F |
| reviewer_id | bigint UNSIGNED | YES | FK → employees |
| notes | text | YES | |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

---

### 3.25 activity_log (Spatie)

Bảng audit trail tự động theo dõi thay đổi model.

| Column | Description |
|---|---|
| log_name | Tên log (default, activity) |
| description | Mô tả hành động |
| subject_type | Model type |
| subject_id | Model ID |
| causer_type | Người thực hiện type |
| causer_id | Người thực hiện ID |
| properties | JSON: before/after values |
| created_at | Timestamp |

---

### 3.26 va_prd_app_notifications ✨ MỚI

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| recipient_account_id | bigint UNSIGNED | YES | FK → system_accounts (người nhận) |
| type | varchar(100) | NO | Enum: NotificationType (task_assigned, task_overdue...) |
| category | varchar(50) | NO | Enum: NotificationCategory (task/sprint/project/document/comment/system/admin) |
| priority | varchar(20) | NO | Enum: NotificationPriority (critical/high/medium/low) |
| title | varchar(500) | NO | Tiêu đề thông báo |
| body | text | YES | Nội dung chi tiết |
| actor_account_id | bigint UNSIGNED | YES | FK → system_accounts (người thực hiện) |
| actor_name | varchar(255) | YES | Tên actor (snapshot) |
| project_id | bigint UNSIGNED | YES | FK → projects (context) |
| sprint_id | bigint UNSIGNED | YES | FK → sprints (context) |
| task_id | bigint UNSIGNED | YES | FK → tasks (context) |
| entity_type | varchar(100) | YES | Loại entity liên quan (task/blocker...) |
| entity_id | bigint UNSIGNED | YES | ID entity |
| action_url | varchar(500) | YES | Deep link URL |
| meta | json | YES | Metadata thêm (task_title, project_name...) |
| read_at | datetime | YES | Thời điểm đọc (null = chưa đọc) |
| acknowledged_at | datetime | YES | Thời điểm acknowledge |
| assigned_to_account_id | bigint UNSIGNED | YES | FK → system_accounts (assign for action) |
| is_admin_feed | tinyint(1) | NO | Default: 0 — admin-only notifications |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

**Indexes:** recipient_account_id, read_at (for unread count query)

**Delivery Model:** Bulk insert via `AppNotification::insert($rows)` — không dùng Queue, sync delivery.

---

### 3.27 va_prd_notification_preferences ✨ MỚI

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| system_account_id | bigint UNSIGNED | NO | Unique FK → system_accounts |
| disabled_types | json | YES | Mảng NotificationType values bị tắt |
| channel_in_app | tinyint(1) | NO | Default: 1 |
| channel_email | tinyint(1) | NO | Default: 0 |
| channel_push | tinyint(1) | NO | Default: 0 |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

**Indexes:** system_account_id (unique)

---

### 3.28 va_prd_system_settings ✨ MỚI

Lưu **override** cấu hình runtime (admin chỉnh ở `/settings`). Bảng trống ⇒ app dùng default từ `config/*`. Xem `docs/SYSTEM_CONFIG.md`.

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| key | varchar(255) | NO | Unique, namespaced `{group}.{name}` (vd. `telegram.enabled`); matrix: `permissions.role_grants` |
| value | longtext | YES | JSON-encoded (scalar / list / matrix) |
| updated_by | bigint UNSIGNED | YES | FK → system_accounts, nullOnDelete |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

**Indexes:** key (unique)

---

## 4. Domain Boundaries

```
People Domain:
    employees, system_accounts, departments

Project Domain:
    projects, project_member,
    sprints, epics, tasks,
    task_assignees, task_watchers, task_attachments, task_activities, task_dependencies,
    worklogs,
    project_attachments, project_attachment_activities

Issue Tracking Domain:
    blockers, blocker_attachments, blocker_activities,
    bugs,
    feedbacks

Communication Domain:
    comments

Reporting Domain:
    daily_reports, daily_report_scores

Notification Domain:              ← MỚI
    app_notifications,
    notification_preferences

Audit Domain:
    activity_log

Knowledge Base Domain:              ← Migrate 2026-06-14
    kb_categories, kb_articles, kb_tags, kb_article_tags,
    kb_article_images, kb_article_attachments,
    kb_article_favorites, kb_article_reads
    (+ comments polymorphic → KbArticle)

Coaching / Mentoring Domain:        ← Migrate 2026-06-14
    coaching_courses, coaching_sessions,
    coaching_session_materials, coaching_assignments, coaching_progress
    (+ student_name, coach_name trên courses)
```

---

## 5. Key Business Rules

| Rule | Implementation |
|---|---|
| 1 báo cáo / người / ngày | unique(employee_id, date) trong daily_reports |
| Cost snapshot | worklog.rate_snapshot lưu rate tại thời điểm ghi (tránh retroactive changes) |
| Auto-generate codes | Bug::boot() → BUG-0001, Blocker → RSK-001, Feedback → FB-0001 |
| Task ordering | order_column cho drag-drop |
| Soft deletes | employees, tasks, bugs có deleted_at |
| Polymorphic comments | commentable_type + commentable_id |
| MONTHLY_HOURS = 176 | Hằng số trong Project model cho tính toán rate tháng → giờ |

---

## 6. Module Quản lý Tài khoản AI — PĐX · ĐNTT · Vòng đời (thêm 2026-06)

### 6.1 Bảng `va_prd_ai_payment_requests`

Đề Nghị Thanh Toán — 1 bản ghi / PĐX, tách luồng duyệt tiền với PĐX.

| Cột | Kiểu | Mô tả |
|---|---|---|
| `id` | uuid PK | — |
| `ai_purchase_proposal_id` | uuid FK unique | References `ai_purchase_proposals.id`; cascade delete |
| `payment_request_code` | string unique | Tự sinh: `DNTT-YYYYMMDD-###` |
| `amount` | bigint | Số tiền ĐNTT (VNĐ); mặc định = `cost_amount` PĐX |
| `status` | string(16) | `pending` \| `approved` \| `rejected` \| `paid` |
| `created_by` | bigint FK | References `system_accounts.id`; null on delete |
| `reviewed_by` | bigint FK nullable | References `system_accounts.id`; null on delete |
| `reviewed_at` | timestamp nullable | Thời điểm duyệt hoặc từ chối |
| `rejection_reason` | text nullable | Lý do từ chối |
| `paid_at` | timestamp nullable | Khi ghi nhận thanh toán (`mark-paid`) |
| `payment_document_paths` | json nullable | Đường dẫn chứng từ (v2) |
| `created_at`, `updated_at` | timestamps | — |

**Luồng:** `pending` → `approved` / `rejected`; sau `approved` → `paid` (mark-paid).

**Gate tạo TK:** `AiAccountFromProposalCreator` yêu cầu ĐNTT ở trạng thái `approved` hoặc `paid` trước khi lập tài khoản AI.

### 6.2 Cột lifecycle trên `va_prd_ai_accounts`

Thêm 2026-06-07 để theo dõi vòng đời tài khoản sau khi mua.

| Cột | Kiểu | Mô tả |
|---|---|---|
| `lifecycle_status` | string(24) default `in_use` | `not_purchased` \| `purchased` \| `allocated` \| `in_use` \| `expired` \| `stopped` |
| `purchased_by` | bigint FK nullable | References `system_accounts.id`; null on delete |
| `actual_purchase_cost` | bigint nullable | Chi phí thực tế (khác với `cost_amount` nếu có discount) |
| `allocated_at` | date nullable | Ngày cấp phát cho người dùng |
| `allocated_to_name` | string nullable | Tên người nhận tài khoản |

Ghi chú: cột `status` (active / expiring_soon / expired / cancelled) giữ nguyên cho **cảnh báo hạn** — `lifecycle_status` dùng để theo dõi **vòng đời mua sắm**.

### 6.3 `AiWorkflowMetricsBuilder` — KPI theo giai đoạn

| Key | Định nghĩa |
|---|---|
| `budget_proposed_total` | Sum `cost_amount` PĐX không bị từ chối |
| `budget_proposal_approved_total` | Sum `cost_amount` PĐX đã duyệt |
| `budget_payment_request_total` | Sum `amount` tất cả ĐNTT |
| `budget_payment_approved_total` | ĐNTT `approved` + `paid` |
| `budget_paid_total` | ĐNTT `paid` only |
| `actual_purchase_total` | Sum `actual_purchase_cost` tài khoản đã lập |
| `accounts_allocated_count` | TK `lifecycle` in `allocated`, `in_use` |
| `accounts_expiring_soon_count` | `status = expiring_soon` |
| `accounts_expired_count` | `status = expired` |

API: kèm trong `api.ai-accounts.summary` và `api.ai-accounts.proposals.index` dưới key `workflow_metrics`.

### 6.4 Xóa TK / PĐX và TK mồ côi

Soft delete TK, đồng bộ PĐX, đếm badge vs chi phí theo nhóm, `purgeOrphanedFromProposal`: **`docs/AI_ACCOUNTS.md`**.

---

## 7. Knowledge Base Domain

> Chi tiết: [`docs/KNOWLEDGE_BASE.md`](KNOWLEDGE_BASE.md). Migration: `2026_06_14_120000_create_knowledge_base_tables.php`.

> Chi tiết nghiệp vụ: [`docs/KNOWLEDGE_BASE.md`](KNOWLEDGE_BASE.md). Bảng chưa có migration trong repo.

### 7.1 va_prd_kb_categories

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| name | varchar(255) | NO | Tên danh mục |
| slug | varchar(100) | NO | Unique, seed: general, development, … |
| description | text | YES | |
| color | varchar(50) | YES | Tailwind token / hex |
| icon | varchar(50) | YES | AppIcon name |
| parent_id | bigint UNSIGNED | YES | FK → kb_categories (cây con) |
| sort_order | int | NO | Default: 0 |
| is_active | tinyint(1) | NO | Default: 1 |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

**Indexes:** slug (unique), parent_id (FK)

---

### 7.2 va_prd_kb_articles

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| category_id | bigint UNSIGNED | NO | FK → kb_categories |
| author_id | bigint UNSIGNED | NO | FK → employees |
| title | varchar(500) | NO | |
| slug | varchar(255) | NO | Unique, SEO |
| excerpt | text | YES | Mô tả ngắn |
| content | longtext | YES | HTML TipTap |
| status | varchar(20) | NO | draft / published / archived |
| view_count | int UNSIGNED | NO | Default: 0 |
| published_at | datetime | YES | |
| archived_at | datetime | YES | |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

**Indexes:** slug (unique), category_id, author_id, status; FULLTEXT(title, excerpt, content) — tùy chọn

---

### 7.3 va_prd_kb_tags

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| name | varchar(100) | NO | |
| slug | varchar(100) | NO | Unique |
| color | varchar(50) | YES | |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

---

### 7.4 va_prd_kb_article_tags (Pivot)

| Column | Type | Description |
|---|---|---|
| article_id | bigint UNSIGNED | FK → kb_articles |
| tag_id | bigint UNSIGNED | FK → kb_tags |

**PK:** (article_id, tag_id)

---

### 7.5 va_prd_kb_article_images

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| article_id | bigint UNSIGNED | NO | FK → kb_articles |
| uploaded_by_id | bigint UNSIGNED | NO | FK → employees |
| original_name | varchar(500) | NO | |
| path | varchar(1000) | NO | Storage path |
| mime_type | varchar(100) | YES | |
| size | bigint UNSIGNED | YES | Bytes |
| alt_text | varchar(500) | YES | |
| sort_order | int | NO | Default: 0 |
| created_at | timestamp | YES | |

---

### 7.6 va_prd_kb_article_attachments

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| article_id | bigint UNSIGNED | NO | FK → kb_articles |
| uploaded_by_id | bigint UNSIGNED | NO | FK → employees |
| original_name | varchar(500) | NO | |
| path | varchar(1000) | NO | |
| mime_type | varchar(100) | YES | |
| size | bigint UNSIGNED | YES | |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

---

### 7.7 va_prd_kb_article_favorites (Pivot)

| Column | Type | Description |
|---|---|---|
| system_account_id | bigint UNSIGNED | FK → system_accounts |
| article_id | bigint UNSIGNED | FK → kb_articles |
| created_at | timestamp | |

**PK:** (system_account_id, article_id)

---

### 7.8 va_prd_kb_article_reads (Pivot)

| Column | Type | Description |
|---|---|---|
| system_account_id | bigint UNSIGNED | FK → system_accounts |
| article_id | bigint UNSIGNED | FK → kb_articles |
| read_at | datetime | NO | |
| created_at | timestamp | |

**PK:** (system_account_id, article_id)

**Comments:** `va_prd_comments.commentable_type = App\Models\KbArticle`.

---

## 8. Coaching / Mentoring Domain

> Chi tiết nghiệp vụ: [`docs/COACHING_MENTORING.md`](COACHING_MENTORING.md). Migrations: `2026_06_14_120100_create_coaching_tables.php`, `2026_06_14_140000_add_coaching_course_participant_names.php`.

### 8.1 va_prd_coaching_courses

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| code | varchar(20) | YES | Auto: COACH-001 |
| name | varchar(255) | NO | Tên khóa |
| description | text | YES | |
| objectives | text | YES | Mục tiêu |
| student_name | varchar(255) | YES | Tên học viên (text, guest) |
| coach_name | varchar(255) | YES | Tên coach (text) |
| student_id | bigint UNSIGNED | YES | FK → employees (học viên) |
| coach_id | bigint UNSIGNED | YES | FK → employees |
| status | varchar(20) | NO | planning / active / completed / cancelled |
| start_date | date | YES | |
| end_date | date | YES | |
| total_fee | decimal(15,2) | YES | Học phí tổng (VNĐ) |
| hourly_rate | decimal(10,2) | YES | Giá theo giờ |
| total_hours | decimal(6,2) | YES | Tổng giờ kế hoạch |
| created_by | bigint UNSIGNED | YES | FK → system_accounts |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

**Indexes:** code (unique), student_id, coach_id, status

---

### 8.2 va_prd_coaching_sessions

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| course_id | bigint UNSIGNED | NO | FK → coaching_courses |
| title | varchar(255) | NO | Tên buổi |
| session_number | int UNSIGNED | NO | Số thứ tự trong khóa |
| date | date | YES | Ngày học |
| start_time | time | YES | |
| end_time | time | YES | |
| total_hours | decimal(4,2) | YES | Tổng giờ buổi |
| topic | varchar(500) | YES | Chủ đề |
| content | longtext | YES | HTML |
| notes | text | YES | Ghi chú |
| status | varchar(20) | NO | pending / in_progress / completed / cancelled |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

**Indexes:** unique(course_id, session_number), course_id, date, status

---

### 8.3 va_prd_coaching_session_materials

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| session_id | bigint UNSIGNED | NO | FK → coaching_sessions |
| type | varchar(30) | NO | canva, google_docs, pdf, pptx, youtube, loom, gdrive, file |
| title | varchar(255) | NO | |
| url | varchar(1000) | YES | Link ngoài |
| path | varchar(1000) | YES | File upload |
| mime_type | varchar(100) | YES | |
| size | bigint UNSIGNED | YES | |
| sort_order | int | NO | Default: 0 |
| created_at | timestamp | YES | |

---

### 8.4 va_prd_coaching_assignments

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| session_id | bigint UNSIGNED | NO | FK → coaching_sessions |
| title | varchar(500) | NO | |
| description | text | YES | |
| deadline | datetime | YES | |
| priority | varchar(20) | NO | high / medium / low |
| status | varchar(20) | NO | todo / doing / review / done |
| submission_path | varchar(1000) | YES | File nộp |
| github_url | varchar(500) | YES | |
| notes | text | YES | |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

---

### 8.5 va_prd_coaching_progress

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| course_id | bigint UNSIGNED | NO | FK → coaching_courses |
| session_id | bigint UNSIGNED | NO | FK → coaching_sessions |
| system_account_id | bigint UNSIGNED | NO | FK → system_accounts |
| is_viewed | tinyint(1) | NO | Default: 0 |
| is_in_progress | tinyint(1) | NO | Default: 0 |
| is_completed | tinyint(1) | NO | Default: 0 |
| updated_at | timestamp | YES | |

**Indexes:** unique(course_id, session_id, system_account_id)

**Business rule:** Tiến độ khóa % = `completed_sessions / total_sessions` (ưu tiên `coaching_sessions.status = completed`).

