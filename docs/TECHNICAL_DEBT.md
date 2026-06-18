# TECHNICAL DEBT — VA QLDA

> Sổ đăng ký nợ kỹ thuật sau refactor Phase 1–5.
>
> **Cập nhật:** 2026-06-03 (triển khai toàn diện) · [`REFACTOR_PLAN.md`](REFACTOR_PLAN.md)

---

## Tóm tắt

| Mức độ | Mở | Đã xử lý |
|--------|-----|----------|
| 🔴 High | 0 | 1 |
| 🟡 Medium | 1 *(partial)* | 8 |
| 🟢 Low | 4 | 10+ |

**Refactor Phase 1–5:** ✅ Hoàn thành.

---

## Chỉ mục ID

| ID | Tiêu đề | Trạng thái |
|----|---------|------------|
| TD-001 | Kiến trúc không nhất quán | 🟡 Partial *(chấp nhận)* |
| TD-002 | Controllers / loaders | ✅ Resolved |
| TD-003–009, TD-017–018, TD-021 | *(xem bảng dưới)* | ✅ Resolved |
| TD-007 | API service layer (FE) | ✅ Resolved |
| TD-010 | `project_id` legacy DailyReport | ✅ Documented |
| TD-011 | Test coverage | 🟡 Partial |
| TD-012 | Không REST API | 🟢 By design |
| TD-013–016 | Infrastructure | 🟢 Roadmap |
| TD-019 | N+1 / indexes | ✅ Resolved |
| TD-020 | Visual regression | ✅ Baseline |

---

## ✅ Đã giải quyết (2026-06-03)

| ID | Giải pháp |
|----|-----------|
| TD-002 | `ProjectShowDataLoader`, `ProjectIndexQuery`, `ProjectSummaryQuery`; `CreateTaskUseCase`, `UpdateTaskUseCase`, `PatchTaskUseCase`, `ImportTasksUseCase` |
| TD-007 | `shared/services/http.js` — `useNotifications` dùng `httpGet`/`httpPost` |
| TD-010 | `ReportProjectSync` + `docs/DAILY_REPORT_PROJECTS.md` + test sync |
| TD-018 | Bulk `tasks/import` + `SprintDataModal` một POST |
| TD-019 | Migration `projects_active_sort_name_idx`, `daily_reports_employee_status_idx`; summary 1 query |
| TD-020 | Playwright project `visual` + `npm run test:e2e:visual` |
| TD-011 *(partial)* | `NotificationTest`, E2E `notifications.spec.js`, mở rộng `projects.spec.js` |
| TD-017, TD-021 | Documented / re-export |

---

## 🟡 Còn lại (có chủ đích)

### TD-001 — Kiến trúc partial

Blocker/Feedback giữ MVC; DailyReport + Project/Task có Use Cases. **Không bắt buộc** Domain cho mọi module.

### TD-011 — Tests *(partial)*

**Đã có:** 115+ PHPUnit · E2E: auth, blockers, bugs, departments, daily-report, projects, notifications · visual baseline.

**Có thể bổ sung:** Worklog feature test · E2E task detail · import Excel E2E.

### TD-012–016 — Infrastructure (roadmap)

REST API (LT-01), Queue, Events/WebSocket (LT-05), TypeScript, Nav config UI (LT-07) — **by design / roadmap**, không phải bug.

---

## Ma trận ưu tiên

| Ưu tiên | Hành động | Trạng thái |
|---------|-----------|------------|
| — | Sprint bulk import | ✅ |
| — | Project/Task loaders & Use Cases | ✅ |
| — | DailyReport project sync doc | ✅ |
| — | DB indexes + summary query | ✅ |
| — | `http.js` + notifications tests | ✅ |
| — | Visual regression baseline | ✅ |
| Tùy chọn | E2E task detail / worklog | Open |
| Roadmap | LT-01 … LT-07 | Deferred |

---

## Lệnh kiểm tra

```bash
composer test
npm run test:e2e              # chromium (CI)
npm run test:e2e:visual       # snapshot từng màn hình (feature-screens.spec.js)
npm run test:e2e:visual -- --update-snapshots   # cập nhật baseline sau đổi UI
```

---

## Cập nhật tài liệu

Khi đóng TD: cập nhật bảng trên + `_dev/testing.md` + `ARCHITECTURE.md` nếu cần.
