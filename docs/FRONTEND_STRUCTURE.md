# FRONTEND STRUCTURE — VA QLDA

> **Cập nhật 2026-06-03** — sau refactor Phase 2–5 (`modules/`, `shared/`, Pinia, lazy routes).

---

## 1. Technology Stack Frontend

| Công Nghệ | Phiên Bản | Mục Đích |
|---|---|---|
| Vue.js | 3.5.35 | UI framework (Composition API, `<script setup>`) |
| Inertia.js | Latest | SPA bridge với Laravel |
| Pinia | 3.x | Global state (`stores/auth.js`, `stores/ui.js`) |
| Tailwind CSS | 3.4.19 | Utility-first CSS |
| Vite | 5.0 | Build tool + manual code splitting |
| TipTap | 3.24.0 | Rich text editor |
| Playwright | 1.49 | E2E tests (`tests/e2e/`) |
| Ziggy | 2.x | Laravel routes in JavaScript |

---

## 2. Entry Point & App Bootstrap

### app.js

```javascript
// Pinia + Inertia + Ziggy
// Lazy-load pages: import.meta.glob('./Pages/**/*.vue', { eager: false })
// resolvePageComponent('./Pages/${name}.vue', ...)
```

### vite.config.js

- Alias `@` → `resources/js/`
- `manualChunks`: vendor-vue, vendor-tiptap, vendor-chart, vendor-excel, vendor-gantt, vendor-utils

### app.blade.php

Mount point `<div id="app" @inertia>` + Vite assets + Ziggy routes.

---

## 3. Folder Structure (sau refactor)

```
resources/js/
├── app.js, bootstrap.js
├── Pages/{Domain}/           ← Inertia pages (mỏng)
├── Layouts/AppLayout.vue
├── Components/
│   ├── Ui/                   ← Modal, Drawer, PageHeader, ToastContainer, AppDialog
│   ├── AppIcon.vue
│   ├── DailyReport/          ← Feature chưa migrate modules/
│   └── Notifications/
├── modules/                  ← Feature modules (Phase 2)
│   ├── project/
│   │   ├── components/       ← ProjectCard, Sprint/, TaskDetail/, Dashboard/, …
│   │   └── config/           ← columns.js, sprintTableColumns.js, riskTableColumns.js
│   └── daily-report/
│       └── config/reportConfig.js
├── shared/                   ← Cross-module (Phase 2 + 4)
│   ├── ui/                   ← Badge, Avatar, ProgressBar, form/*, EmptyState, …
│   └── composables/          ← useToast, usePermission, useFilter
├── composables/              ← Feature composables (useSprint*, useProject*, useRisk*, …)
├── stores/                   ← Pinia (Phase 3)
│   ├── auth.js
│   └── ui.js
└── constants/index.js        ← Frontend constants (mirror config/business.php)
```

**Import alias:** `@/modules/...`, `@/shared/...`, `@/Components/...`, `@/composables/...`, `@/stores/...`

> `Components/Project/` đã **xóa** — dùng `modules/project/components/`.

---

## 4. Component Hierarchy

```
AppLayout.vue
├── Sidebar (role-filtered nav)
├── NotificationBell + NotificationCenterDrawer
├── UserMenu (modules/project/components/UserMenu.vue)
├── <slot> → Inertia Page
├── ToastContainer → shared/composables/useToast
└── AppDialog → useDialog
```

---

## 5. Inertia Pages

| Domain | Files |
|---|---|
| Auth | `Pages/Auth/Login.vue` |
| Dashboard | `Pages/Dashboard/Index.vue` |
| DailyReport | `Today`, `History`, `Show`, `Review` |
| Project | `Index`, `Create`, `Edit`, `Show` |
| Blocker / Bug / Feedback | `Index`, `Show` (Bug, Feedback) |
| Department | `Pages/Department/Index.vue` |
| Notifications | `Pages/Notifications/Management.vue` |

Pages import feature components từ `@/modules/project/components/...` và primitives từ `@/shared/ui/...`.

---

## 6. Component Catalog

### 6.1 UI Primitives — `Components/Ui/`

`Modal.vue`, `Drawer.vue`, `AppDialog.vue`, `ToastContainer.vue`, `PageHeader.vue`

### 6.2 Shared UI — `shared/ui/`

| Component | Mô tả |
|---|---|
| `Badge.vue`, `Avatar.vue`, `ProgressBar.vue` | Status / user / progress |
| `MoneyInput.vue`, `MultiChips.vue`, `RadioCard.vue`, `FieldTooltip.vue` | Form helpers |
| `CommentThread.vue` | Thread bình luận (Task/Bug/Blocker) |
| `EmptyState.vue`, `LoadingSpinner.vue`, `SkeletonLoader.vue` | Empty / loading |
| `form/FormField.vue`, `TextInput.vue`, `SelectInput.vue`, `DateInput.vue` | Form primitives |

### 6.3 Project Module — `modules/project/components/`

| Nhóm | Components |
|---|---|
| Core | `ProjectCard`, `ProjectDataGrid`, `ProjectForm`, `ProjectMembers`, `GanttChart`, `TaskBoard`, … |
| Sprint/ | `SprintWorkspace`, `SprintTaskTable`, `SprintDataModal`, `TaskDetailPanel`, … |
| TaskDetail/ | `TaskDetailRichEditor`, `TaskDetailCollaboration`, `TaskDetailSubtasks`, … |
| Dashboard/ | `ProjectOverviewCard`, `RiskIssueDataTable`, `RiskImportModal`, `ActivityFeed`, … |
| Timeline/ | `ProjectTimelineView`, `ProjectTimelineBurndown`, … |
| Documents/ | `ProjectDocumentsPanel`, `DocumentPreviewPane` |
| Modals | `TaskFormModal`, `SprintFormModal`, `BlockerFormModal`, `BugFormModal`, … |

### 6.4 Daily Report — `Components/DailyReport/`

`GradePill`, `StatusBadge`, `ScoringPanel`, `RichTextField`, `ProjectPicker`, …

### 6.5 Notifications — `Components/Notifications/`

`NotificationBell`, `NotificationCenterDrawer`, `NotificationItem`, `NotificationSettingsPanel`

---

## 7. Composables

### 7.1 Shared — `shared/composables/`

| File | Mô tả |
|---|---|
| `useToast.js` | Toast state (dùng bởi ToastContainer + features) |
| `usePermission.js` | Role/permission helpers |
| `useFilter.js` | URL-bound filter state |

### 7.2 Feature — `composables/`

| Nhóm | Files |
|---|---|
| Sprint | `useSprintData`, `useSprintWorkspace`, `useSprintTaskTable`, `useSprintFilters`, `useSprintReconcile`, `useSprintExport` |
| Task | `useTaskWorkspace`, `useTaskBulkCreate`, `useTaskHierarchy`, `useTaskPhaseGroups`, `useTaskTimeliness` |
| Project | `useProjectDashboard`, `useProjectTimeline`, `useProjectExport`, `useProjectListExport`, `useProjectCreateDraft` |
| Risk | `useRiskImport`, `useRiskExport`, `useRiskTable` |
| Other | `useNotifications`, `useDocumentPreview`, `useDialog`, `useFormat`, `useConfirmClose`, `useVirtualScroll`, `useNormalizeList` |

**Quy tắc:** Excel I/O trong composable — **không** import `xlsx` trong `.vue`.

---

## 8. Pinia Stores — `stores/`

| Store | Mô tả |
|---|---|
| `auth.js` | User/role snapshot (bổ sung Inertia props) |
| `ui.js` | UI shell state (sidebar, preferences) |

Đăng ký trong `app.js`: `.use(createPinia())`.

---

## 9. Config Files

| File | Vị trí |
|---|---|
| Project columns | `modules/project/config/columns.js` |
| Sprint table columns | `modules/project/components/Sprint/sprintTableColumns.js` |
| Risk table columns | `modules/project/components/Dashboard/riskTableColumns.js` |
| Daily report config | `modules/daily-report/config/reportConfig.js` |
| Notification meta | `composables/notificationMeta.js` |
| App constants | `constants/index.js` |

---

## 10. UI Patterns

### Toast

```javascript
import { useToast } from '@/shared/composables/useToast';
const toast = useToast();
toast.success('Đã lưu thành công');
```

### Forms (Inertia)

```javascript
import { useForm } from '@inertiajs/vue3';
form.post(route('projects.store'), { preserveScroll: true });
```

### Permissions

```javascript
import { usePage } from '@inertiajs/vue3';
usePage().props.auth.user.role;
// entity: project.can?.manage từ Resource
```

### Import / Export / Đối soát

Một nút **Dữ liệu** → `*DataModal.vue` (3 tab: import · export · reconcile).  
Pattern: `RiskImportModal`, `SprintDataModal` + composables `useRiskImport`, `useSprintData`.

---

## 11. Dev Tooling

| Tool | Config |
|---|---|
| ESLint | `eslint.config.js` — flat config, `--max-warnings=0` |
| Husky | `.husky/` — pre-commit lint, commit-msg, pre-push E2E |
| Playwright | `playwright.config.js`, `tests/e2e/` |
| CI | `.github/workflows/ci.yml` |
| Project memory | `_dev/` (EN) + `_dev/vi/` (giải thích VI) |

---

## 12. Role-Based UI

Nav filter backend `App\Support\Navigation` → `usePage().props.auth`.  
Roles: `admin` | `lead` | `member` | `viewer`.
