# DATABASE STRUCTURE — VA Workspace

---

## 1. Tổng Quan

| Thông Tin | Giá Trị |
|---|---|
| Database Engine | MySQL |
| Table Prefix | `va_prd_` |
| Total Tables | ~45 (core + KB + Credential + Contract/CLM + Congnghe proposals + Onboarding — migrations tới `2026_07_29_*`) |
| ORM | Laravel Eloquent |
| Soft Deletes | employees, tasks, bugs, contracts |
| UUID Support | daily_reports (+ có thể mở rộng) |
| Activity Log | Spatie Activity Log |

**Ghi chú (2026-06-15):** Module Talent Management đã gỡ — các bảng `employee_skills`, `certifications`, `performance_kpis`, `learning_items`, `feedback_reviews`, `succession_plans`, `career_levels` bị drop bởi migration `2026_06_15_140000_drop_talent_tables`. Kỹ năng hồ sơ lưu trên `employees.skills` (JSON) và `employees.meta.skill_details`.

**HRM SSOT (2026-07-28):** Danh tính nhân sự lấy qua Public API v1 (`HRM_API_BASE_URL` + `HRM_API_TOKEN`) — `HrmApiClient` → `HrmIdentityResolver` lazy upsert vào `va_prd_employees` (`hrm_employee_uuid`, `hrm_user_id` = legacy). Workspace **không** đọc DB `va_hrm` / connection `hrm_mysql`. Field HR trên `employees` là **cache ánh xạ** — chỉnh sửa chỉ trên VA-HRM; Workspace không ghi đè qua `/profile`.

**Ghi chú (2026-07-29):** Module Coaching / Mentoring đã gỡ — các bảng `coaching_courses`, `coaching_sessions`, `coaching_session_materials`, `coaching_assignments`, `coaching_progress` bị drop bởi migration `2026_07_29_100000_drop_coaching_tables`.

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
| code | varchar(50) | NO | Unique, e.g. EMP-001 (hoặc mã HR / `HRM-xxxxxx`) |
| full_name | varchar(255) | NO | Họ và tên |
| email | varchar(255) | NO | Unique |
| phone | varchar(20) | YES | |
| avatar_path | varchar(500) | YES | Đường dẫn ảnh |
| role_title | varchar(255) | YES | Chức danh (Display only) |
| join_date | date | YES | Ngày vào làm |
| skills | json | YES | Mảng kỹ năng |
| is_active | tinyint(1) | NO | Default: 1 |
| meta | json | YES | Metadata tuỳ chỉnh (department/company từ HRM) |
| hrm_user_id | bigint UNSIGNED | YES | Unique — link `va_hrm.va_hrm_users.id` (HRM SSOT, lazy upsert khi Google login) |
| hrm_employee_uuid | char(36) | YES | Unique — claim JWT / API `uuid` HRM (`GET /employees*`) |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |
| deleted_at | timestamp | YES | Soft delete |

**Indexes:** code (unique), email (unique), hrm_user_id (unique), hrm_employee_uuid (unique)

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

> **UI:** trang Index `/departments` đã gỡ (2026-07); mutate API + `DepartmentFormModal` vẫn dùng. Org directory sẽ đồng bộ từ HRM.

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
| type | varchar(20) | NO | rnd/deployment/operation (vòng đời) |
| category | varchar(30) | YES | hardware/software — legacy (không còn field form / chip lọc Index) |
| scope | varchar(20) | NO | headquarters/regional/departmental |
| scope_regions | json | YES | Mảng vùng khi scope=regional: `saigon`, `vungtau`, `cantho` |
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

**Công việc con (`parent_id` ≠ null):** sprint, phase, ngày bắt đầu/hạn và người thực hiện (`assignee_id` + pivot `task_assignees`) luôn đồng bộ theo công việc cha; client chỉ sửa giao việc trên task gốc.

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
| rejection_reason | text | YES | Bắt buộc khi từ chối; hiển thị người gửi + email |
| email_sent_at | timestamp | YES | |
| email_error | varchar(500) | YES | |
| rejection_email_sent_at | timestamp | YES | Email thông báo từ chối tới người gửi |
| rejection_email_error | varchar(500) | YES | |
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
| category | varchar(50) | YES | Enum: ProjectAttachmentCategory (`customer`, `uiux`, `ba`, `dev`, `customer_data`, `images`, `showcase`) |
| uploaded_by_id | bigint UNSIGNED | YES | FK → employees |
| updated_by_id | bigint UNSIGNED | YES | FK → employees |
| original_name | varchar(500) | NO | |
| notes | text | YES | |
| path | varchar(1000) | NO | |
| mime_type | varchar(100) | YES | |
| size | bigint UNSIGNED | YES | |
| is_image | tinyint(1) | NO | Default: 0 |
| parent_id | bigint UNSIGNED | YES | FK → project_attachments (cascade) — thư mục cha |
| is_folder | tinyint(1) | NO | Default: 0 — bản ghi thư mục (không file/link) |
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
| grade | varchar(1) | YES | S/A/B/C/D |
| reviewer_id | bigint UNSIGNED | YES | FK → employees |
| notes | text | YES | |
| scoring_snapshot | json | YES | weights + kaizen + source lúc chấm |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

---

### 3.24b va_prd_daily_report_scoring_configs

Trọng số chấm báo cáo ngày theo phòng ban (Workspace).

| Column | Type | Nullable | Description |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| department_code | varchar(64) | NO | Unique |
| department_name | varchar | YES | Denormalized |
| local_department_id | bigint UNSIGNED | YES | FK → departments |
| weights | json | NO | task_completion, skill_score, attitude_score, expertise_score |
| kaizen_bonus_max | decimal(4,2) | NO | Default 2.0 |
| status | varchar(16) | NO | active \| draft |
| updated_by | bigint UNSIGNED | YES | FK → system_accounts |
| timestamps / soft deletes | | | |

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

### 3.29 va_prd_evaluation_* — Cấu hình tiêu chí / mẫu / phiếu đánh giá

Xem `docs/EVALUATION_CONFIG.md`. Migrations: `2026_07_29_160000_create_evaluation_config_tables` → `2026_07_30_120000_drop_evaluation_templates` → `2026_07_30_130000_reshape_evaluation_criteria_catalog` (catalog standalone) → `2026_07_31_120000_create_evaluation_templates_tables` (mẫu đánh giá) → `2026_07_31_160000_enhance_evaluation_templates_targets_fields` (targets / custom criteria / form fields) → `2026_08_03_140000_create_evaluation_forms_tables` (phiếu đánh giá) → `2026_08_03_150000_create_evaluation_form_scoring_tables` (chấm điểm) → `2026_08_03_160000_add_code_description_to_evaluation_form_types` (`code`/`description`, seed PERIODICAL·CONTRACT·TYPE_PROBATION).

| Bảng | Mô tả |
|------|--------|
| `evaluation_criteria` | Tiêu chí chung / theo PB; SoftDeletes; unique `criteria_code` (`TCVA###`); `score_levels` JSON `[{label, weight}]` (2–10 mức); `allow_half_score` |
| `evaluation_templates` | Mẫu đánh giá; SoftDeletes; unique `template_code` (`MDG###`); `position_code` / `position_name` (legacy mirror); `created_by` |
| `evaluation_template_criteria` | Pivot mẫu ↔ tiêu chí catalog: `weight`, `required_score_label`, `include_in_total`, `sort_order` |
| `evaluation_template_targets` | Multi chức danh (`kind=title`) / cấp bậc (`kind=rank`): `code`, `name`, `hrm_uuid`, `source` |
| `evaluation_template_custom_criteria` | Tiêu chí tùy chỉnh trên mẫu (không FK catalog): `custom_name`, weight, score label… |
| `evaluation_template_fields` | Trường form phụ trên mẫu: `field_key`, `field_type`, `options` JSON, `is_required` |
| `evaluation_template_export_logs` | Lịch sử xuất Excel/CSV: `scope`, `format`, `row_count`, `columns` JSON, `filters` JSON, `filename` |
| `evaluation_form_types` | Loại đánh giá (`code` nullable unique, `description`); seed hệ thống PERIODICAL / CONTRACT / TYPE_PROBATION; unique `name` |
| `evaluation_forms` | Header phiếu (`form_code` `PDG###`); FK mẫu/loại; kỳ; manager; deadline; order/weight; status; SoftDeletes |
| `evaluation_form_watchers` | Người theo dõi (`form_id` + `employee_id`) |
| `evaluation_form_raters` | Hội đồng: `role_key`, `label`, `weight_percent`, `sort_order` |
| `evaluation_form_fields` | Trường tùy biến trên phiếu (`evaluator_comment`, `self_next_plan`, …) |
| `evaluation_form_criteria` | Snapshot tiêu chí trên phiếu + `evaluator_mode` / `evaluator_role_keys` |
| `evaluation_form_assignees` | Nhân sự được đánh giá + trưởng phòng / QLTT / BGD |
| `evaluation_form_submissions` | Lượt chấm (form×assignee×rater_role); status draft\|submitted; `total_score` |
| `evaluation_form_score_lines` | Điểm từng tiêu chí trên submission (mức + weight) |
| `evaluation_form_field_values` | Giá trị trường tùy biến khi chấm |

### 3.30 va_prd_workspace_profiles — Workspace theo phòng ban

Xem `docs/WORKSPACE_CONFIG.md`. Migration: `2026_07_30_160000_create_workspace_profiles_table`.

| Bảng | Mô tả |
|------|--------|
| `workspace_profiles` | Shell workspace theo `department_code` (unique); status draft\|active\|archived; `enabled_nav_groups` JSON nullable (allow-list sidebar); SoftDeletes; FK optional `local_department_id` → departments |

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

## 6. Module Quản lý Tài khoản AI (làm phẳng 2026-08)

Chi tiết nghiệp vụ: [`docs/AI_ACCOUNTS.md`](AI_ACCOUNTS.md).

Migration flatten: `2026_08_10_100000_simplify_ai_accounts_flat_workspace.php` — **đã drop** `ai_purchase_proposals`, `ai_payment_requests`, `ai_proposal_scans`, `ai_proposal_scan_signatures`, `ai_account_password_viewers`.

### 6.1 Bảng `va_prd_ai_accounts`

| Cột | Kiểu | Mô tả |
|---|---|---|
| `id` | uuid PK | — |
| `created_by` | FK system_accounts nullable | Người tạo |
| `tool_name` | string | Tên công cụ AI |
| `group_function` | string(32) | DEV / BA / PM / Design / QA / Other |
| `email_registered` | string | Email đăng ký |
| `login_method` | string(16) | `password` \| `google` (default password) |
| `login_password` | text nullable | Encrypted — chỉ khi password |
| `purchase_date` / `expiry_date` | date | Ngày mua / hết hạn |
| `proposal_sent_at` / `proposal_approved_at` / `payment_request_sent_at` | date nullable | Ngày gửi PĐX / duyệt PĐX / gửi ĐNTT |
| `proposal_document_paths` / `payment_request_document_paths` | json nullable | Mỗi loại tối đa 1 file: `[{path, original_name, mime_type, size}]` |
| `cost_amount` | bigint | Chi phí (VNĐ) |
| `cost_unit` | string(16) | monthly / quarterly / yearly / one_time |
| `status` | string(24) | active (Đang sử dụng) / expiring_soon / expired / out_of_token (Hết token) / cancelled (Không còn sử dụng) |
| `notify_before_days` | smallint | Mặc định 14 |
| `last_reminded_at` | timestamp nullable | Lần nhắc hết hạn gần nhất |
| `notes` | text nullable | — |
| `purchase_url` | string(2048) nullable | Link chỗ mua |
| `purchase_type` | string(16) | `new` \| `renewal` (mặc định `new`) — enum `AiAccountPurchaseType` |
| `deleted_at` | soft delete | — |

### 6.2 Bảng `va_prd_ai_account_access_grants`

| Cột | Kiểu | Mô tả |
|---|---|---|
| `ai_account_id` | uuid FK cascade | Tài khoản AI |
| `account_id` | FK system_accounts | Người được cấp |
| `permissions` | json | `view`, `view_password`, `edit`, `delete`, `share` |
| `granted_by` | FK nullable | Người cấp |
| `expires_at` | timestamp nullable | Hết hạn grant |
| unique | `(ai_account_id, account_id)` | `ai_acct_grant_unique` |

Chi phí KPI / báo cáo: quy tháng từ `cost_amount` + `cost_unit` (`AiAccountCostCalculator`). Visibility list: `AiAccount::scopeVisibleTo`.

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

## 8. Credential Management (vault)

| Bảng | Mô tả |
|---|---|
| `va_prd_credentials` | Tài khoản / tài sản số; `login_password` encrypted cast |
| `va_prd_credential_access_grants` | ACL theo `system_accounts`, `permissions` JSON |
| `va_prd_credential_audit_logs` | Audit xem/sao chép/sửa mật khẩu |
| `va_prd_credential_relations` | Liên kết hạ tầng (source → target) |
| `va_prd_credential_password_histories` | Lịch sử mật khẩu (encrypted) |
| `va_prd_credential_access_requests` | Workflow yêu cầu quyền |

Enums: `App\Support\Enums\Credential*` · Policy: `CredentialPolicy` · Nav group `security`.

---

## 9. Contract Management (CLM) — migrations `2026_06_17_10*`

Prefix `va_prd_`, Policy `ContractPolicy` (admin/lead/viewer).

| Bảng | Mô tả |
|---|---|
| `va_prd_vendors` | Nhà cung cấp; `cooperation_status` (`active`/`potential`/`research`/`inactive`, migration `2026_08_11_064914`); `is_active` đồng bộ từ status |
| `va_prd_contract_categories` | Nhóm dịch vụ (vendor_id nullable) |
| `va_prd_vendor_service_categories` | Pivot NCC ↔ nhiều nhóm dịch vụ (`vendor_id`, `contract_category_id`, unique) — migration `2026_08_11_120000` |
| `va_prd_contracts` | Hợp đồng; `root_contract_id` → self (phụ lục); `status` default `draft` = "Đang chờ duyệt" |
| `va_prd_contract_finances` | Dữ liệu tài chính chi tiết (CRUD từ Show page) |
| `va_prd_contract_attachments` | File + link hồ sơ; `category`, `version` |
| `va_prd_contract_renewals` | Audit log gia hạn (song song với việc tạo Contract phụ lục mới) |
| `va_prd_contract_activities` | Lịch sử hoạt động (event, description, meta) |
| `va_prd_vendor_reviews` | Đánh giá NCC — **6 tiêu chí 0–10** + tổng điểm + recommendation; **`contract_id` nullable FK** (migration `2026_06_17_151052`) để gắn đánh giá với hợp đồng cụ thể |

### Schema thay đổi 2026-06-17

| Bảng | Thay đổi |
|---|---|
| `va_prd_contracts` | Thêm cột `root_contract_id` (nullable FK self → contracts, nullOnDelete) nếu thiếu — migration `2026_06_17_170000_add_root_contract_id_to_contracts_table` |
| `va_prd_vendor_reviews` | Thêm cột `contract_id` (nullable FK → contracts, nullOnDelete) — migration `2026_06_17_151052_add_contract_id_to_vendor_reviews_table` |

### Luồng trạng thái hợp đồng

```
Tạo mới → draft ("Đang chờ duyệt")  [locked, không cho chọn khi create]
Kích hoạt thủ công → active ("Đang hiệu lực")
Gia hạn → tạo Contract mới (status=active); HĐ được gia hạn → addendum; `root_contract_id` = id gốc bộ
Auto-sync: expiring_soon / expired / pending_renewal
```


