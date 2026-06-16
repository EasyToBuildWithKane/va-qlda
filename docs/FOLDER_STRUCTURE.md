# FOLDER STRUCTURE — VA QLDA

> **Cập nhật 2026-06-16** — sau refactor Phase 1–5. Hub luồng: [`FLOWS_AND_DOCS_MAP.md`](FLOWS_AND_DOCS_MAP.md).

---

## 1. Cấu Trúc Hiện Tại (sau refactor)

```
va-qlda/
├── _dev/                          ← Project memory (CLI, CI, workflows) + _dev/vi/
├── app/
│   ├── Application/
│   │   ├── DailyReport/           ← Use Cases (Clean Architecture)
│   │   ├── Project/               ← Create, Update, Duplicate, Archive, LogWork
│   │   └── Task/                  ← Create, UpdateStatus, BulkCreate
│   ├── Domain/DailyReport/        ← Domain models + ScoringService
│   ├── Http/Controllers|Requests|Resources/  ← KnowledgeBase/, Coaching/, Congnghe/, AiAccount/, …
│   ├── Models/
│   ├── Policies/
│   ├── Providers/
│   ├── Services/                  ← NotificationService
│   └── Support/
│       ├── Enums/
│       ├── Options/               ← EmployeeOptions, ProjectOptions, DepartmentOptions
│       ├── Options.php            ← Facade delegate → Options/*
│       └── *ActivityLogger.php
├── config/
│   ├── business.php               ← Business constants (MONTHLY_HOURS, defaults)
│   └── ...
├── docs/                          ← Technical documentation
├── resources/js/
│   ├── Pages/                     ← Inertia pages
│   ├── Layouts/
│   ├── Components/Ui/, DailyReport/, Notifications/
│   ├── modules/project/, daily-report/
│   ├── shared/ui/, shared/composables/
│   ├── composables/               ← Feature composables
│   ├── stores/                    ← Pinia auth + ui
│   └── constants/
├── scripts/                       ← prepare-commit-msg, auto-commit
├── tests/
│   ├── Feature/                   ← Login, Project, Task, Blocker, Bug, Department, Feedback
│   └── e2e/                       ← Playwright
├── .husky/                        ← Git hooks
├── .github/workflows/ci.yml
└── .cursor/, .claude/             ← AI rules, skills, commands
```

---

## 2. Refactor Phase 1–5 — Đã Hoàn Thành

| Phase | Nội dung | Trạng thái |
|---|---|---|
| **1** | `config/business.php`, `constants/`, enums, feature tests | ✅ |
| **2** | `modules/project/`, `shared/ui/`, xóa `Components/Project/` | ✅ |
| **3** | Use Cases Project/Task, Options services, Pinia stores | ✅ |
| **4** | Shared UI library (form/, EmptyState, SkeletonLoader) | ✅ |
| **5** | Lazy Inertia pages, Vite manual chunks, Options cache | ✅ (một phần DB optimization còn lại) |

Chi tiết: [`REFACTOR_PLAN.md`](REFACTOR_PLAN.md).

---

## 3. Vấn Đề Đã Giải Quyết (trước refactor)

| Vấn đề cũ | Giải pháp |
|---|---|
| `Components/Project/` quá lớn (40+ files) | → `modules/project/components/` |
| UI primitives trong Project/ | → `shared/ui/` |
| Config trong Components/ | → `modules/*/config/` |
| Chỉ 1 composable | → 25+ composables + `shared/composables/` |
| Không có stores/ | → Pinia `stores/auth.js`, `stores/ui.js` |
| Hardcoded constants | → `config/business.php` + `constants/index.js` |
| Options.php God Object | → `Support/Options/*` + delegate |

---

## 4. Còn Lại / Chưa Làm

| Item | Ghi chú |
|---|---|
| Lowercase `pages/` folder | Bỏ qua — rủi ro Windows case-insensitive |
| `modules/daily-report/components/` | DailyReport vẫn ở `Components/DailyReport/` |
| Domain layer cho Project/Task | Chỉ Application Use Cases, chưa có `app/Domain/Project/` |
| `services/http.js` frontend | API calls vẫn inline Inertia/axios |
| TypeScript / JSDoc types | Chưa có |

---

## 5. Backend Application Layer

```
app/Application/
├── DailyReport/     ← Full Clean Architecture
├── Project/
│   ├── CreateProjectUseCase.php
│   ├── UpdateProjectUseCase.php
│   ├── DuplicateProjectUseCase.php
│   ├── ArchiveProjectUseCase.php
│   └── LogWorkUseCase.php
└── Task/
    ├── CreateTaskUseCase.php
    ├── UpdateTaskStatusUseCase.php
    └── BulkCreateTasksUseCase.php
```

**Pattern mới:** Project/Task mutations → Use Case. DailyReport → Use Case + Domain.  
Blocker/Bug/Feedback → vẫn MVC trực tiếp.

---

## 6. Frontend Import Conventions

```javascript
// Shared UI
import Badge from '@/shared/ui/Badge.vue';
import { useToast } from '@/shared/composables/useToast';

// Project feature
import ProjectCard from '@/modules/project/components/ProjectCard.vue';
import { COLUMNS } from '@/modules/project/config/columns';

// App primitives
import Modal from '@/Components/Ui/Modal.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

// Feature logic
import { useSprintWorkspace } from '@/composables/useSprintWorkspace';
```

---

## 7. Tài Liệu Liên Quan

| File | Nội dung |
|---|---|
| [`FRONTEND_STRUCTURE.md`](FRONTEND_STRUCTURE.md) | Component catalog, composables, patterns |
| [`ARCHITECTURE.md`](ARCHITECTURE.md) | Layer responsibilities |
| [`TECHNICAL_DEBT.md`](TECHNICAL_DEBT.md) | TD items còn lại |
| [`_dev/README.md`](../_dev/README.md) | CLI, CI, workflows (operational) |
