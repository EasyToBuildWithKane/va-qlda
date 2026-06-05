# API STRUCTURE — VA QLDA

---
sss
## 1. Kiến Trúc API Hiện Tại

**Loại:** Chủ yếu Inertia.js Server-Side Routes + một phần JSON API (Notifications)

Đa số routes đi qua `routes/web.php` và trả về Inertia responses. **Ngoại lệ:** Notification endpoints trả về `JsonResponse` để hỗ trợ polling và lazy loading.

```
routes/web.php      ← Toàn bộ routes (Inertia + JSON cho notifications)
routes/api.php      ← Rỗng (chưa sử dụng)
```

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
| GET | `/login` | LoginController@create | guest | Hiển thị form đăng nhập |
| POST | `/login` | LoginController@store | guest | Xử lý đăng nhập |
| POST | `/logout` | LoginController@destroy | auth | Đăng xuất |

### 2.2 Dashboard

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| GET | `/` | redirect | auth | Redirect → /dashboard |
| GET | `/dashboard` | DashboardController@index | auth | Trang tổng quan |

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
| GET | `/daily-reports` | DailyReportController@index | auth | Lịch sử báo cáo |
| GET | `/daily-reports/today` | DailyReportController@today | auth | Form báo cáo hôm nay |
| POST | `/daily-reports` | DailyReportController@store | auth | Tạo báo cáo mới |
| GET | `/daily-reports/{report}` | DailyReportController@show | auth | Xem báo cáo |
| PUT | `/daily-reports/{report}` | DailyReportController@update | auth | Sửa báo cáo |
| POST | `/daily-reports/{report}/submit` | DailyReportController@submit | auth | Nộp báo cáo |
| GET | `/daily-reports/review` | DailyReportReviewController@index | auth | Hàng chờ chấm |
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
| PUT | `/projects/{project}/sprints/{sprint}` | SprintController@update | auth | Sửa sprint |
| DELETE | `/projects/{project}/sprints/{sprint}` | SprintController@destroy | auth | Xóa sprint |

### 2.6 Tasks

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
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
| POST | `/projects/{project}/attachments` | ProjectAttachmentController@store | auth | Upload tài liệu |
| PUT | `/projects/{project}/attachments/{attachment}` | ProjectAttachmentController@update | auth | Sửa metadata |
| DELETE | `/projects/{project}/attachments/{attachment}` | ProjectAttachmentController@destroy | auth | Xóa tài liệu |

### 2.11 Blockers

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| GET | `/blockers` | BlockerController@index | auth | Danh sách vướng mắc |
| POST | `/blockers` | BlockerController@store | auth | Tạo vướng mắc |
| POST | `/blockers/import` | BlockerController@import | auth | Nhập hàng loạt |
| POST | `/blockers/bulk` | BlockerController@bulk | auth | Thao tác hàng loạt |
| PUT | `/blockers/{blocker}` | BlockerController@update | auth | Sửa vướng mắc |
| DELETE | `/blockers/{blocker}` | BlockerController@destroy | auth | Xóa vướng mắc |
| POST | `/blockers/{blocker}/attachments` | BlockerController@storeAttachment | auth | Upload file |
| DELETE | `/blockers/{blocker}/attachments/{attachment}` | BlockerController@destroyAttachment | auth | Xóa file |

### 2.12 Bugs

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| GET | `/bugs` | BugController@index | auth | Danh sách lỗi |
| POST | `/bugs` | BugController@store | auth | Báo lỗi |
| GET | `/bugs/{bug}` | BugController@show | auth | Chi tiết lỗi |
| PUT | `/bugs/{bug}` | BugController@update | auth | Sửa thông tin lỗi |
| DELETE | `/bugs/{bug}` | BugController@destroy | auth | Xóa lỗi |

### 2.13 Feedback

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| GET | `/feedback` | FeedbackController@index | auth | Danh sách góp ý |
| POST | `/feedback` | FeedbackController@store | auth | Gửi góp ý |
| GET | `/feedback/{feedback}` | FeedbackController@show | auth | Chi tiết góp ý |
| PUT | `/feedback/{feedback}` | FeedbackController@update | auth | Sửa góp ý |
| DELETE | `/feedback/{feedback}` | FeedbackController@destroy | auth | Xóa góp ý |

### 2.14 Comments

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| POST | `/comments` | CommentController@store | auth | Thêm comment |
| PUT | `/comments/{comment}` | CommentController@update | auth | Sửa comment |
| DELETE | `/comments/{comment}` | CommentController@destroy | auth | Xóa comment |
| POST | `/comments/{comment}/react` | CommentController@react | auth | React emoji |

### 2.15 Departments

| Method | URI | Controller | Middleware | Mô Tả |
|---|---|---|---|---|
| GET | `/departments` | DepartmentController@index | auth | Danh sách phòng ban |
| POST | `/departments` | DepartmentController@store | auth | Tạo phòng ban |
| PUT | `/departments/{department}` | DepartmentController@update | auth | Sửa phòng ban |
| PATCH | `/departments/{department}/toggle` | DepartmentController@toggleStatus | auth | Bật/tắt hoạt động |
| DELETE | `/departments/{department}` | DepartmentController@destroy | auth | Xóa phòng ban |

---

## 3. API Grouping (Theo Domain)

```
Auth Group
├── /login, /logout

Dashboard Group
└── /dashboard

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
├── /daily-reports          (index, store)
├── /daily-reports/today    (today form)
├── /daily-reports/review   (review queue)
└── /daily-reports/{id}     (show, update, submit, score, reject)

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
├── /bugs                   (CRUD)
└── /feedback               (CRUD)

Communication Group
└── /comments               (store, update, delete, react)

Organization Group
└── /departments            (CRUD + toggle)
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
| Bugs/Feedback | ✅ | ✅ | ✅ | ✅* |
| Comments | ✅ | ✅ | ✅ | - |
| Departments | ✅ | ✅* | - | - |

*\* = Xem/tạo được nhưng không xóa*

---

## 5. Naming Conventions

| Pattern | Ví Dụ |
|---|---|
| Resource plural noun | `/projects`, `/blockers`, `/bugs` |
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
