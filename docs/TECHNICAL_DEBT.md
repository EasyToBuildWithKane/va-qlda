# TECHNICAL DEBT — VA QLDA

---

## Tổng Quan

> **Cập nhật 2026-06-03** — sau 3 commits (Departments, Module Projects, Noti Bell): TD-005 giảm mức độ, TD-009 đã xử lý, QW-01 hoàn thành.

| Mức Độ | Số Lượng |
|---|---|
| 🔴 High | 4 (giảm 1) |
| 🟡 Medium | 9 (tăng 1 — pattern mới Notification JSON API) |
| 🟢 Low | 6 |

---

## Nhóm 1: Kiến Trúc

### TD-001 — Kiến Trúc Không Nhất Quán
- **Mức độ:** 🔴 High
- **Ảnh hưởng:** Khó onboard developer mới, khó scale
- **Mô tả:** Clean Architecture (Application + Domain layers) chỉ được áp dụng cho module `DailyReport`. Các module quan trọng hơn như `Project`, `Task`, `Sprint` vẫn dùng thin MVC — Controllers gọi thẳng Models, không có Use Case layer.
- **Hệ quả:** Business logic rải rác trong Controllers, khó test, khó refactor.
- **Đề xuất:** Mở rộng Application Layer cho Project, Task, Sprint, IssueTracking. Xem [REFACTOR_PLAN.md Phase 3].

### TD-002 — Controllers Quá Dày
- **Mức độ:** 🔴 High
- **Ảnh hưởng:** Khó test, khó maintain
- **Mô tả:** `ProjectController@show` và `TaskController` chứa logic query phức tạp (eager loading, filtering, aggregation) trực tiếp trong controller method. Vi phạm Single Responsibility Principle.
- **Hệ quả:** Unit testing không thể isolate business logic.
- **Đề xuất:** Tách query logic vào Query Objects hoặc Repository Classes.

### TD-003 — Options.php là God Object
- **Mức độ:** 🟡 Medium
- **Ảnh hưởng:** Single point of failure, khó test
- **Mô tả:** `app/Support/Options.php` là static class chứa toàn bộ shared options: employees(), projects(), departments(), enums(), defaultOwnerDepartmentId(). Coupled trực tiếp với Models.
- **Hệ quả:** Không thể inject/mock, khó test, thay đổi một query ảnh hưởng toàn bộ.
- **Đề xuất:** Tách thành Service classes có thể inject: `EmployeeOptionsService`, `ProjectOptionsService`.

---

## Nhóm 2: Frontend

### TD-004 — Thiếu State Management
- **Mức độ:** 🔴 High
- **Ảnh hưởng:** State phân tán, prop drilling, khó debug
- **Mô tả:** Không có Pinia stores. State được truyền qua Inertia props hoặc event emits. Khi components sâu 3-4 levels, cần prop drilling hoặc provide/inject.
- **Hệ quả:** Global state như auth user, notifications, UI state không có single source of truth.
- **Đề xuất:** Thêm Pinia. Tạo `stores/auth.js` (user/role), `stores/ui.js` (toast/dialog).

### TD-005 — Thiếu Composables Cốt Lõi ✅ **GIẢM MỨC ĐỘ**
- **Mức độ:** ~~🔴 High~~ → 🟡 Medium
- **Ảnh hưởng:** Logic duplicate trong một số components
- **Cập nhật 2026-06-03:** Đã thêm 20+ composables quan trọng: `useSprintData`, `useProjectDashboard`, `useProjectTimeline`, `useRiskImport`, `useRiskExport`, `useTaskWorkspace`, `useNotifications`, `useDocumentPreview`, `useVirtualScroll`, `useConfirmClose`...
- **Còn thiếu:** `usePermission` (role-based checks), `useForm` (wrapper chuẩn hoá), `useFilter` (URL-bound filters).
- **Đề xuất:** Tạo thêm 3 composables còn thiếu. Xem [FRONTEND_STRUCTURE.md Section 6].

### TD-006 — UI Primitives Nằm Sai Vị Trí
- **Mức độ:** 🟡 Medium
- **Ảnh hưởng:** Khó tái sử dụng, confusing imports
- **Mô tả:** `Badge.vue`, `Avatar.vue`, `ProgressBar.vue`, `MoneyInput.vue`, `RadioCard.vue` nằm trong `Components/Project/` mặc dù là generic UI components dùng được ở nhiều nơi.
- **Hệ quả:** Muốn dùng Badge trong DailyReport phải import từ `../../Project/Badge.vue`.
- **Đề xuất:** Di chuyển vào `shared/ui/`.

### TD-007 — Không Có API Service Layer
- **Mức độ:** 🟡 Medium
- **Ảnh hưởng:** HTTP calls phân tán, khó centralize error handling
- **Mô tả:** Axios calls và Inertia form submissions nằm inline trong components. Không có service layer abstraction.
- **Hệ quả:** Khó thêm global error handling, retry logic, loading states.
- **Đề xuất:** Tạo `services/http.js` wrapper và feature services.

### TD-008 — Components/Project/ Quá Lớn
- **Mức độ:** 🟡 Medium
- **Ảnh hưởng:** Khó tìm file, confusing ownership
- **Mô tả:** `Components/Project/` chứa 40+ files ở nhiều cấp: UI primitives, feature modals, sub-modules (Sprint/, TaskDetail/, Dashboard/, Timeline/, Documents/). Config files (`projectColumns.js`) cũng nằm đây.
- **Đề xuất:** Tổ chức lại theo `modules/project/components/` + `modules/project/config/`.

---

## Nhóm 3: Code Quality

### TD-009 — Hardcoded Values ✅ **GIẢI QUYẾT MỘT PHẦN**
- **Mức độ:** 🟡 Medium → 🟢 Low
- **Cập nhật 2026-06-03:** `"Công nghệ"` default department đã được chuyển vào `config/project.php` với env var `PROJECT_DEFAULT_DEPARTMENT_CODE` (default: `'PB-PT'`).
- **Còn lại:** `MONTHLY_HOURS = 176` vẫn hardcoded trong `Project.php`. CSS color strings rải rác.
- **Đề xuất:** Move `MONTHLY_HOURS` vào `config/project.php`.

### TD-010 — project_id Legacy trong DailyReport
- **Mức độ:** 🟡 Medium
- **Ảnh hưởng:** Confusing data model
- **Mô tả:** `daily_reports.project_id` là "legacy field" — được sync từ `projects[0].id` trong CreateDailyReportUseCase. Nhưng vẫn tồn tại song song với `projects` JSON field.
- **Hệ quả:** Không rõ source of truth. Có thể out-of-sync nếu projects array thay đổi.
- **Đề xuất:** Quyết định: deprecate `project_id` hoàn toàn hoặc remove JSON `projects` field nếu chỉ cần single project.

### TD-011 — Thiếu Tests
- **Mức độ:** 🟡 Medium
- **Ảnh hưởng:** Rủi ro regression khi refactor
- **Mô tả:** Có PHPUnit cài sẵn nhưng không có feature/unit tests cho business logic. Chỉ có factories cho DailyReport.
- **Hệ quả:** Refactoring nguy hiểm vì không có safety net.
- **Đề xuất:** Bắt đầu với feature tests cho critical paths: login, create project, submit daily report.

---

## Nhóm 4: Infrastructure

### TD-012 — Không Có REST API
- **Mức độ:** 🟢 Low
- **Ảnh hưởng:** Khó tích hợp tương lai
- **Mô tả:** `routes/api.php` rỗng. Toàn bộ đi qua Inertia (HTML responses). Không có JSON API endpoint.
- **Đề xuất:** Thêm khi có nhu cầu mobile app hoặc third-party integration.

### TD-013 — Không Có Queue/Background Jobs
- **Mức độ:** 🟢 Low
- **Ảnh hưởng:** Performance khi scale
- **Mô tả:** Không có queue setup. Notifications (planned), email alerts sẽ block request nếu chạy sync.
- **Đề xuất:** Setup Laravel Queue + Redis khi thêm tính năng notifications.

### TD-014 — Không Có Event/Listener Pattern ✅ **GIẢI QUYẾT MỘT PHẦN**
- **Mức độ:** 🟢 Low
- **Cập nhật 2026-06-03:** `NotificationDispatcher` (static bridge) + `NotificationService` đã phần nào thay thế vai trò Events cho notification triggers. Tuy nhiên dispatcher vẫn dùng static calls, không phải proper Events.
- **Còn lại:** Nếu cần thêm side effects khác (metrics, email, webhooks) vẫn cần Events.

### TD-017 — Notification dùng JSON API (không nhất quán với Inertia) [MỚI]
- **Mức độ:** 🟡 Medium
- **Ảnh hưởng:** Hai pattern response tồn tại song song, frontend phải xử lý khác nhau
- **Mô tả:** `NotificationController` trả `JsonResponse` (cursor-based pagination) trong khi toàn bộ hệ thống dùng Inertia. Frontend dùng `useNotifications.js` với `fetch`/axios thay vì Inertia form/visit.
- **Lý do:** Notification cần real-time polling / lazy loading — không phù hợp với full page Inertia.
- **Đề xuất:** Document rõ đây là intentional exception. Khi có WebSocket thì thay bằng push.

### TD-015 — Thiếu TypeScript
- **Mức độ:** 🟢 Low
- **Ảnh hưởng:** Type errors khó debug
- **Mô tả:** Codebase dùng JavaScript thuần (không có TypeScript hoặc JSDoc types).
- **Đề xuất:** Thêm JSDoc types trước, sau đó migrate sang TypeScript nếu cần.

### TD-016 — Navigation Hardcoded
- **Mức độ:** 🟢 Low
- **Ảnh hưởng:** Thêm menu item phải sửa code
- **Mô tả:** `Navigation.php` hardcoded toàn bộ sidebar structure trong PHP class.
- **Đề xuất:** Tạo config file hoặc database-driven navigation nếu cần dynamic nav.

---

## Ma Trận Ưu Tiên (cập nhật 2026-06-03)

| ID | Vấn Đề | Mức Độ | Effort | Ưu Tiên |
|---|---|---|---|---|
| TD-001 | Kiến trúc không nhất quán | 🔴 High | High | Phase 3 |
| TD-002 | Controllers quá dày | 🔴 High | High | Phase 3 |
| TD-004 | Thiếu Pinia stores | 🔴 High | Medium | Phase 3 |
| TD-005 | Thiếu composables (còn 3 cần thêm) | 🟡 Medium | Low | Phase 3 |
| TD-011 | Thiếu tests | 🟡 Medium | High | Phase 1 |
| TD-003 | Options God Object | 🟡 Medium | Medium | Phase 3 |
| TD-006 | UI primitives vị trí sai | 🟡 Medium | Low | Phase 2 |
| TD-007 | Thiếu API service layer | 🟡 Medium | Medium | Phase 3 |
| TD-008 | Components/Project quá lớn | 🟡 Medium | Medium | Phase 2 |
| TD-009 | Hardcoded values (còn MONTHLY_HOURS) | 🟢 Low | Low | Phase 1 |
| TD-010 | project_id legacy field | 🟡 Medium | Medium | Phase 1 |
| TD-017 | Notification JSON API (khác Inertia pattern) | 🟡 Medium | Low | Document |
| TD-012 | Không có REST API | 🟢 Low | High | Future |
| TD-013 | Không có Queue | 🟢 Low | Medium | Future |
| TD-014 | Không có Events (partial) | 🟢 Low | Medium | Future |
| TD-015 | Thiếu TypeScript | 🟢 Low | High | Future |
| TD-016 | Navigation hardcoded | 🟢 Low | Low | Phase 4 |
