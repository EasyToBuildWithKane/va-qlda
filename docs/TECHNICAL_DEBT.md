# TECHNICAL DEBT — VA QLDA

---

## Tổng Quan

> **Cập nhật 2026-06-03** — sau refactor Phase 1–5 và feature tests.

| Mức Độ | Số Lượng (ước tính) |
|---|---|
| 🔴 High | 1 |
| 🟡 Medium | 6 |
| 🟢 Low / Resolved | 10+ |

---

## Đã Giải Quyết (Refactor 2026-06)

| ID | Vấn đề | Giải pháp |
|---|---|---|
| TD-003 | Options God Object | `Support/Options/*` + delegate + cache |
| TD-004 | Thiếu Pinia | `stores/auth.js`, `stores/ui.js` |
| TD-005 | Thiếu composables | 25+ composables + `shared/composables/` |
| TD-006 | UI primitives sai vị trí | `shared/ui/` |
| TD-008 | Components/Project quá lớn | `modules/project/components/` |
| TD-009 | Hardcoded values | `config/business.php` + `constants/index.js` |
| TD-011 | Thiếu tests | Feature tests: Login, Project, Task, Blocker, Bug, Department, Feedback |
| TD-001 | *(partial)* | Project/Task Use Cases — Blocker/Bug vẫn MVC |

---

## Nhóm 1: Kiến Trúc (còn lại)

### TD-001 — Kiến Trúc Không Nhất Quán (partial)
- **Mức độ:** 🟡 Medium *(giảm từ 🔴 High)*
- **Còn lại:** Blocker, Bug, Feedback chưa có Application layer; DailyReport vẫn là pattern duy nhất có Domain models riêng.
- **Đề xuất:** Mở rộng Use Cases cho IssueTracking khi cần; không bắt buộc Domain layer cho mọi module.

### TD-002 — Controllers Quá Dày
- **Mức độ:** 🔴 High
- **Mô tả:** `ProjectController@show`, `TaskController@index` vẫn chứa query phức tạp.
- **Đề xuất:** Query Objects hoặc dedicated ViewModels/Resources loaders.

---

## Nhóm 2: Frontend (còn lại)

### TD-007 — Không Có API Service Layer
- **Mức độ:** 🟡 Medium
- **Mô tả:** Axios/Inertia inline trong components.
- **Đề xuất:** `services/http.js` wrapper khi cần centralize error handling.

### TD-017 — Notification JSON API
- **Mức độ:** 🟡 Medium
- **Mô tả:** Intentional exception — polling/lazy load không phù hợp Inertia full page.
- **Đề xuất:** Document; thay WebSocket khi có LT-05.

---

## Nhóm 3: Code Quality

### TD-010 — project_id Legacy trong DailyReport
- **Mức độ:** 🟡 Medium
- **Mô tả:** `daily_reports.project_id` legacy vs `projects` JSON field.
- **Đề xuất:** Deprecate hoặc document rõ source of truth.

### TD-011 — Tests (partial resolved)
- **Mức độ:** 🟡 Medium *(giảm)*
- **Đã có:** Login, Project, Task, Blocker, Bug, Department, Feedback feature tests.
- **Còn thiếu:** DailyReport submit/score flow, Sprint bulk import API, E2E coverage mở rộng.

---

## Nhóm 4: Infrastructure

| ID | Vấn đề | Mức độ | Ghi chú |
|---|---|---|---|
| TD-012 | Không REST API | 🟢 Low | `api.php` rỗng — by design |
| TD-013 | Không Queue | 🟢 Low | Cần khi email notifications |
| TD-014 | Events (partial) | 🟢 Low | NotificationDispatcher thay thế một phần |
| TD-015 | Thiếu TypeScript | 🟢 Low | JS thuần |
| TD-016 | Navigation hardcoded | 🟢 Low | `Navigation.php` |

---

## Ma Trận Ưu Tiên (cập nhật 2026-06-03)

| ID | Vấn đề | Mức độ | Ưu tiên |
|---|---|---|---|
| TD-002 | Controllers quá dày | 🔴 High | Query objects / loaders |
| TD-001 | Kiến trúc partial | 🟡 Medium | IssueTracking Use Cases (khi cần) |
| TD-010 | project_id legacy | 🟡 Medium | DailyReport cleanup |
| TD-007 | API service layer FE | 🟡 Medium | Khi thêm nhiều axios endpoints |
| TD-017 | Notification JSON | 🟡 Medium | Document only |
| TD-011 | Tests mở rộng | 🟡 Medium | DailyReport + E2E |
| TD-012–016 | Infrastructure | 🟢 Low | Roadmap LT-* |

**Refactor Phase 1–5:** ✅ Hoàn thành — xem [`REFACTOR_PLAN.md`](REFACTOR_PLAN.md).
