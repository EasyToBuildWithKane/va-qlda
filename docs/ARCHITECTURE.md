# ARCHITECTURE — VA QLDA

---

## 1. Kiến Trúc Hiện Tại

Dự án áp dụng **Hybrid Architecture**: kết hợp Clean Architecture ở tầng Daily Report domain và MVC truyền thống ở các module còn lại.

```
app/
├── Application/          ← Application Layer (chỉ DailyReport)
│   └── DailyReport/
│       ├── CreateDailyReportUseCase.php
│       ├── UpdateDailyReportUseCase.php
│       ├── SubmitDailyReportUseCase.php
│       ├── ScoreReportUseCase.php
│       └── RejectReportUseCase.php
│
├── Domain/               ← Domain Layer (chỉ DailyReport)
│   └── DailyReport/
│       ├── Models/
│       ├── Services/
│       └── Exceptions/
│
└── Http/                 ← HTTP Layer (MVC cho tất cả)
    ├── Controllers/      ← Request handling
    ├── Requests/         ← Validation
    ├── Resources/        ← Response formatting
    └── Middleware/
```

### Sơ Đồ Layer

```
┌──────────────────────────────────────────────────────┐
│                   HTTP Layer                          │
│  Routes → Controllers → Requests → Resources         │
└────────────────────────┬─────────────────────────────┘
                         │ direct call (Project, Task...)
                         │ use case injection (DailyReport)
┌────────────────────────▼─────────────────────────────┐
│              Application Layer                        │
│         Use Cases (chỉ DailyReport)                  │
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
│         MySQL, File Storage, Activity Log             │
└──────────────────────────────────────────────────────┘
```

---

## 2. Các Vấn Đề Kiến Trúc Đang Tồn Tại

### 2.1 Kiến Trúc Không Nhất Quán

| Vấn Đề | Mô Tả | Mức Độ |
|---|---|---|
| Clean Architecture chỉ áp dụng cho DailyReport | Các module khác (Project, Task, Blocker) vẫn dùng thin MVC, không có Use Case layer | Medium |
| Domain models tách biệt với App\Models | `App\Domain\DailyReport\Models\DailyReport` vs `App\Models\Project` — hai pattern tồn tại song song | Medium |
| Controllers quá dày | ProjectController, TaskController làm quá nhiều logic (query, business logic, response formatting) | High |

### 2.2 Coupling

| Vấn Đề | Mô Tả | Mức Độ |
|---|---|---|
| Options.php là God Object | Class static đơn lẻ chứa toàn bộ shared options cho toàn ứng dụng, coupled trực tiếp với Models | Medium |
| Navigation.php hardcoded | Sidebar menu hardcoded trong PHP class, không có cơ chế config | Low |
| Controllers trực tiếp query Models | Không có Repository layer, business logic rải rác trong Controllers và Models | Medium |

### 2.3 Frontend Architecture

| Vấn Đề | Mô Tả | Mức Độ |
|---|---|---|
| Composables thiếu | Chỉ có `useToast.js`, không có composables cho form state, API calls, permissions | High |
| Components chua phân tầng rõ | Project/ chứa cả UI primitives (Badge, Avatar) lẫn complex features (GanttChart, TaskBoard) | Medium |
| Không có global state | Không có Pinia stores — state được pass qua props hoặc lấy từ Inertia page props | Medium |
| Services layer thiếu | Không có API service layer riêng biệt, HTTP calls nằm inline trong components | Medium |

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
Navigation.php ──→ SystemAccount (role-based)
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
