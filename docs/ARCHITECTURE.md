# ARCHITECTURE — VA QLDA

> Sơ đồ vận chuyển request & bản đồ module: [`FLOWS_AND_DOCS_MAP.md`](FLOWS_AND_DOCS_MAP.md).

---

## 1. Kiến Trúc Hiện Tại

Dự án áp dụng **Hybrid Architecture**: Clean Architecture cho DailyReport, Application Use Cases cho Project/Task (Phase 3), injectable Services cho Notification, và MVC cho các module còn lại (Blocker, Bug, Feedback, **Coaching**, **Knowledge Base**, **Congnghe**, **AiAccount**).

```
app/
├── Application/
│   ├── DailyReport/          ← Use Cases (full Clean Architecture)
│   ├── Project/              ← Create, Update, Duplicate, Archive, LogWork
│   └── Task/                 ← Create, UpdateStatus, BulkCreate
│
├── Domain/                   ← Domain Layer (chỉ DailyReport)
│   └── DailyReport/
│
├── Services/
│   └── NotificationService.php
│
└── Http/                     ← Controllers, Requests, Resources
```

**Frontend (sau refactor Phase 2–5):**

```
resources/js/
├── Pages/              ← Inertia (lazy-loaded)
├── modules/project/    ← Feature components + config
├── shared/ui/          ← UI primitives
├── shared/composables/ ← useToast, usePermission, useFilter
├── composables/        ← Feature logic (Sprint, Project, Risk, …)
└── stores/             ← Pinia auth + ui
```

### Sơ Đồ Layer

```
┌──────────────────────────────────────────────────────┐
│                   HTTP Layer                          │
│  Routes → Controllers → Requests → Resources         │
└────────────────────────┬─────────────────────────────┘
                         │ direct call (Project, Task...)
                         │ use case injection (DailyReport)
                         │ service injection (Notification)
┌────────────────────────▼─────────────────────────────┐
│         Application Layer + Services Layer            │
│   Use Cases (DailyReport)                            │
│   app/Services/ — NotificationService (injectable)   │
│   Support/NotificationDispatcher (static bridge)     │
└────────────────────────┬─────────────────────────────┘
                         │
┌────────────────────────▼─────────────────────────────┐
│               Domain Layer                            │
│   Domain Models, Services, Exceptions                 │
│   (chỉ DailyReport — còn lại dùng App\Models)        │
└────────────────────────┬─────────────────────────────┘
                         │ Eloquent ORM
┌────────────────────────▼─────────────────────────────┐
│                Infrastructure                         │
│   MySQL, File Storage, Activity Log, Artisan Commands │
└──────────────────────────────────────────────────────┘
```

---

## 2. Các Vấn Đề Kiến Trúc Đang Tồn Tại

### 2.1 Kiến Trúc Không Nhất Quán

| Vấn Đề | Mô Tả | Mức Độ |
|---|---|---|
| ~~Clean Architecture chỉ DailyReport~~ | **Đã cải thiện:** Project/Task có Application Use Cases; Blocker/Bug vẫn MVC | 🟡 Medium |
| Domain models tách biệt với App\Models | `App\Domain\DailyReport\Models\` vs `App\Models\Project` — hai pattern song song | Medium |
| Controllers quá dày | `ProjectController@show`, `TaskController` vẫn có query phức tạp | High |

### 2.2 Coupling

| Vấn Đề | Mô Tả | Mức Độ |
|---|---|---|
| ~~Options.php God Object~~ | **Đã refactor:** `Support/Options/*` + delegate, bind AppServiceProvider | 🟢 Resolved |
| Navigation.php hardcoded | Sidebar menu hardcoded trong PHP class | Low |
| Controllers trực tiếp query Models | Một số actions vẫn query trực tiếp (show/index) | Medium |

### 2.3 Frontend Architecture

| Vấn Đề | Mô Tả | Mức Độ |
|---|---|---|
| ~~Composables thiếu~~ | **Đã cải thiện:** 25+ composables + `shared/composables/` | 🟢 Resolved |
| ~~Components chưa phân tầng~~ | **Đã migrate:** `modules/project/`, `shared/ui/` | 🟢 Resolved |
| ~~Không có global state~~ | **Đã thêm:** Pinia `stores/auth.js`, `stores/ui.js` | 🟡 Partial |
| Không có API service layer | HTTP calls inline Inertia/axios — chưa có `services/http.js` | Medium |
| Notification JSON API | `NotificationController` trả JSON — intentional exception | Medium |

### 2.4 Dependency Issues

| Vấn Đề | Mô Tả | Mức Độ |
|---|---|---|
| api.php rỗng | Không có REST API — toàn bộ đi qua Inertia routes. Khó tích hợp mobile/third-party sau này | Low |
| Không có Event/Listener pattern | Không dùng Laravel Events, logic như "send notification when task done" sẽ khó thêm sau | Medium |
| Không có Queue | Background jobs chưa được thiết lập | Low |

---

## 3. Kiến Trúc Đề Xuất Mới

### 3.1 Target Architecture: Feature-Based Layered Architecture

```
Backend (Laravel):
┌─────────────────────────────────────────────────────────────┐
│                     HTTP Layer                               │
│  routes/ → Controllers → FormRequests → Resources           │
│  (Controllers = thin orchestrators only)                     │
└────────────────────────────┬────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────┐
│                  Application Layer                            │
│  app/Application/{Feature}/                                  │
│  Use Cases per feature: Project, Task, Sprint, Blocker...   │
│  (Business rules, no HTTP/DB coupling)                       │
└────────────────────────────┬────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────┐
│                   Domain Layer                                │
│  app/Domain/{Feature}/                                       │
│  Entities, Value Objects, Domain Services, Exceptions        │
│  (Pure PHP, no framework dependencies)                       │
└────────────────────────────┬────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────┐
│                Infrastructure Layer                           │
│  Eloquent Repositories, File Storage, Mail, Queue            │
│  (Framework-dependent implementations)                       │
└─────────────────────────────────────────────────────────────┘

Frontend (Vue 3):
┌─────────────────────────────────────────────────────────────┐
│                    Pages (Inertia)                           │
│  pages/{feature}/  — thin, composed of feature components   │
└────────────────────────────┬────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────┐
│                Feature Modules                               │
│  modules/{feature}/                                          │
│  Components + Composables + Services per feature            │
└────────────────────────────┬────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────┐
│                  Shared Layer                                 │
│  shared/ui/     — Primitives: Button, Modal, Badge           │
│  shared/        — Composables, types, constants, utils       │
└────────────────────────────┬────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────┐
│                   Stores (Pinia)                             │
│  stores/auth, stores/ui (toast, dialog)                     │
└─────────────────────────────────────────────────────────────┘
```

### 3.2 Module Boundaries Đề Xuất

```
Feature Modules:
┌──────────────────┬──────────────────┬──────────────────┐
│   Project        │   DailyReport    │   People         │
│   ─────────      │   ──────────     │   ──────         │
│   Project        │   Report         │   Employee       │
│   Sprint         │   Score          │   Department     │
│   Task           │   Review         │   SystemAccount  │
│   Epic           │                  │                  │
│   Worklog        │                  │                  │
│   Attachment     │                  │                  │
│   Member         │                  │                  │
└──────────────────┴──────────────────┴──────────────────┘
┌──────────────────┬──────────────────┬──────────────────┐
│   IssueTracking  │   Communication  │   Auth           │
│   ────────────   │   ─────────────  │   ────           │
│   Blocker        │   Comment        │   Login          │
│   Bug            │   Reaction       │   Session        │
│   Feedback       │                  │   Permission     │
└──────────────────┴──────────────────┴──────────────────┘
```

---

## 4. Dependency Map Hiện Tại

```
Models (App\Models):
Project ──→ Employee (manager, members)
Project ──→ Department
Sprint  ──→ Project
Epic    ──→ Project
Task    ──→ Project, Sprint, Epic, Employee (assignee, reporter, reviewer)
Task    ──→ Task (parent/subtasks, dependencies)
Worklog ──→ Task, Employee
Blocker ──→ Project, Task, Employee
Bug     ──→ Project, Task, Employee
Feedback──→ Project, Employee
Comment ──→ (polymorphic) Task, Bug, Blocker, Feedback

Domain Models (App\Domain):
DailyReport  ──→ Employee
DailyReportScore ──→ DailyReport, Employee

Support:
Options.php ──→ Employee, Project, Department (direct Model coupling)
Navigation.php ──→ SystemAccount (role-based, định nghĩa thuần)
NavigationBadges.php ──→ NotificationService, CongngheSoftwareProposal (đếm badge nav)
TaskActivityLogger.php ──→ Task, Employee
BlockerActivityLogger.php ──→ Blocker, Employee
```

---

## 5. Layer Responsibilities

| Layer | Trách Nhiệm | KHÔNG nên làm |
|---|---|---|
| **Controllers** | Nhận HTTP request, validate qua Form Request, gọi Use Case / Model, trả Resource | Không chứa business logic, không query trực tiếp |
| **Form Requests** | Validate input, authorize request | Không transform data, không query ngoài validation rules |
| **Use Cases** | Orchestrate business flow, gọi Domain Services | Không biết về HTTP, không format response |
| **Domain Models** | Business rules, domain logic, relationships | Không biết về HTTP layer, không format response |
| **Resources** | Format response data cho client | Không chứa business logic |
| **Policies** | Authorization rules | Không chứa business logic |
| **Support** | Utility functions, shared helpers | Không chứa business logic của feature |
