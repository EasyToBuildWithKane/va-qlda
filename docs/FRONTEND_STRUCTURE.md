# FRONTEND STRUCTURE — VA QLDA

> **Cập nhật 2026-06-15** — content header (`PageHeader` + `#header`); coaching sessions datagrid.

> Rule agent: `.cursor/rules/content-header.mdc` · skill `content-header`.

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
│   ├── KnowledgeBase/        ← KbRichTextField, KbImageGallery
│   ├── DailyReport/          ← Feature chưa migrate modules/
│   └── Notifications/
├── modules/                  ← Feature modules (Phase 2)
│   ├── project/
│   │   ├── components/       ← ProjectCard, Sprint/, TaskDetail/, Dashboard/, …
│   │   └── config/           ← columns.js, sprintTableColumns.js, riskTableColumns.js
│   ├── daily-report/
│   │   └── config/reportConfig.js
│   └── coaching/                 ← Coaching / Mentoring (2026-06)
│       ├── components/           ← Sessions table, calendar, modals, drawer, CoachingSessionAssignmentsTab
│       ├── composables/          ← useCoachingCalendar.js
│       └── config/               ← coachingFormHints.js
├── shared/                   ← Cross-module (Phase 2 + 4)
│   ├── ui/                   ← Badge, Avatar, ProgressBar, form/*, **KpiSummaryStrip**, …
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
├── AppSidebar + AppSidebarMobileDrawer (`Components/Layout/`, `composables/useAppSidebar.js`)
├── NotificationBell + NotificationCenterDrawer
├── UserMenu (modules/project/components/UserMenu.vue)
├── <slot> → Inertia Page
├── ToastContainer → shared/composables/useToast
└── AppDialog → useDialog
```

**Sidebar UX (2026-06):** Desktop `lg+` — expanded (`w-72`) hoặc rail (`w-[4.25rem]`), tooltip + flyout nhóm khi rail; `< lg` — drawer trái (hamburger topbar, overlay, vuốt đóng). Trạng thái rail/nhóm: `localStorage` (`va-qlda.sidebar.rail`, `va-qlda.sidebar.collapsed`).

---

## 5. Inertia Pages

| Domain | Files |
|---|---|
| Auth | `Pages/Auth/Login.vue` |
| Dashboard | `Pages/Dashboard/Index.vue` |
| DailyReport | `Today`, `History`, `Show`, `Review` |
| Project | `Index`, `Create`, `Edit`, `Show` |
| Blocker / Feedback | `Index`, `Show` (Feedback) — Index: **`FeedbackSummaryBar`** (dải KPI `kpi-strip` / `kpi-card`, lọc nhanh scope/status; rule `kpi-summary-strip`) + datagrid toolbar (Lọc/Cột), `FilterDatePicker` khoảng ngày, `FeedbackListRowActions` |
| Department | `Pages/Department/Index.vue` |
| Org team | `Pages/OrgTeam/Index.vue` — header gọn; chế độ `DatagridSegmentedControl`; `OrgTeamForestSummaryBar` + `OrgTeamRootCard`; sơ đồ trong `OrgTeamChartCanvas` (lưới automation, luồng nối animated) + `OrgTeamChart` / `org-team-tree.css`; pill chọn Ban/Khối; `useOrgTeamTreeStats.js`; `OrgTeamFormModal` · **`Members.vue`** — `OrgTeamMembersSummaryBar` + datagrid |
| Notifications | `Pages/Notifications/Management.vue` |

Pages import feature components từ `@/modules/project/components/...` và primitives từ `@/shared/ui/...`.

---

## 6. Component Catalog

### 6.1 UI Primitives — `Components/Ui/`

`Modal.vue`, `Drawer.vue`, `AppDialog.vue`, `ToastContainer.vue`, `PageHeader.vue`

**Content header (2026-06):** Mỗi Inertia page đặt **một** `PageHeader` trong `AppLayout` slot `#header` (topbar `h-14`). Props: `title`, `subtitle`, `icon` (khớp `App\Support\Navigation.php`), `icon-color` (thường `brand`), `badge` tùy chọn. Prop `back-href` chỉ trang drill-down (Create/Edit/Show con) — không dùng cho mục sidebar cấp 1 (vd. dashboard AI, lịch coaching). Actions trong default slot: `btn-primary` / `btn-ghost`, `h-9`. Mẫu: `Pages/AiAccount/Index.vue`, `Pages/AiAccount/Dashboard.vue`, `Pages/Coaching/Sessions/Schedule.vue`.

### 6.1b App shell — `Components/Layout/`

`AppSidebar.vue`, `AppSidebarBrand.vue`, `AppSidebarExpandedNav.vue`, `AppSidebarRailNav.vue`, `AppSidebarRailFlyout.vue`, `AppSidebarRailTooltip.vue`, `AppSidebarFooter.vue`, `AppSidebarMobileDrawer.vue` — dùng bởi `Layouts/AppLayout.vue`; logic nav/active/collapse: `composables/useAppSidebar.js`.

### 6.2 Shared UI — `shared/ui/`

| Component | Mô tả |
|---|---|
| `Badge.vue`, `Avatar.vue`, `ProgressBar.vue` | Status / user / progress |
| `MoneyInput.vue`, `MultiChips.vue`, `RadioCard.vue`, `FieldTooltip.vue` | Form helpers |
| `SearchSelect.vue`, `SearchMultiSelect.vue` | Dropdown tìm-kiếm 1 chọn / nhiều chọn (teleport, viewport-aware) |
| `CommentThread.vue` | Thread bình luận (Task/Bug/Blocker) |
| `EmptyState.vue`, `LoadingSpinner.vue`, `SkeletonLoader.vue` | Empty / loading |
| `DatagridToolbarSearch.vue`, `FilterVisibilityDropdown.vue`, `ColumnVisibilityDropdown.vue` | Toolbar bảng (tìm kiếm, bật lọc/cột) |
| `DatagridToolbarActionButton.vue`, `DatagridSegmentedControl.vue`, `DatagridFilterField.vue` | Nút Lọc/Cột/Xuất, segmented, ô lọc grid `w-full h-10` |
| `FilterDatePicker.vue` | Lọc ngày (`@vuepic/vue-datepicker`, `dd/MM/yyyy` ↔ ISO) |
| `form/FormField.vue`, `TextInput.vue`, `SelectInput.vue`, `DateInput.vue`, `TimeInput.vue` | Form primitives (`input--picker`: date/time hiển thị rõ, click cả ô) |

### 6.3 Project Module — `modules/project/components/`

| Nhóm | Components |
|---|---|
| Core | `ProjectCard`, `ProjectDataGrid`, `ProjectForm`, `ProjectMembers`, `GanttChart`, `TaskBoard`, … |
| Sprint/ | `SprintWorkspace`, `SprintTaskTable`, `SprintDataModal`, `TaskDetailPanel`, … |
| TaskDetail/ | `TaskDetailRichEditor`, `TaskDetailCollaboration`, `TaskDetailSubtasks`, … |
| Dashboard/ | `ProjectOverviewCard`, `RiskIssueDataTable`, `ProjectFeedbackPanel`, `RiskImportModal`, `ActivityFeed`, … |
| Timeline/ | `ProjectTimelineView`, `ProjectTimelineBurndown`, … |
| Documents/ | `ProjectDocumentsPanel`, `DocumentPreviewPane` |
| Modals | `TaskFormModal`, `SprintFormModal`, `BlockerFormModal`, … |

### 6.4 Daily Report — `Components/DailyReport/`

`GradePill`, `StatusBadge`, `ScoringPanel`, `RichTextField`, `ProjectPicker`, …

Trang lịch sử (`Pages/DailyReport/History.vue`) — dashboard SaaS:

| Component | Vai trò |
|---|---|
| `DailyReportSummaryBar.vue` | Dải KPI `kpi-strip` (tổng/đã duyệt/chờ/nháp/trễ) + trend pill — thay `ReportDashboard` trên History |
| `ReportDashboard.vue` | *(legacy)* — không còn gắn page; giữ tham chiếu trend pill nếu cần |
| `ReportCard.vue` | Thẻ báo cáo: header (avatar, chức vụ, thời gian, điểm), dự án + task (badge trạng thái), các mục HORENSO thu gọn (`.rich-content`) |

Bộ lọc: shared datagrid (`DatagridToolbarSearch` `hide-label`, `FilterDatePicker` + key `date_range`, grid `xl:grid-cols-6`, `SearchMultiSelect` `control-size="md"`). Nhóm **Ngày / Tuần / Tháng** + **Thẻ / Bảng** (`DatagridSegmentedControl`). Toolbar **không sticky**; lọc hiển thị opt-in (`default: false`, `useVisibleFilterControls`); trạng thái lọc trên URL. Xuất Excel 7 sheet — `useDailyReportHistoryExport`.

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

### 7.1b JSON API — `shared/services/`

| File | Mô tả |
|---|---|
| `http.js` | Axios wrapper (`httpGet`, `httpPost`) — notifications, future JSON endpoints |

### 7.2 Feature — `composables/`

| Nhóm | Files |
|---|---|
| Sprint | `useSprintData`, `useSprintWorkspace`, `useSprintTaskTable`, `useSprintFilters`, `useSprintReconcile`, `useSprintExport` |
| Task | `useTaskWorkspace`, `useTaskBulkCreate`, `useTaskHierarchy`, `useTaskPhaseGroups`, `useTaskTimeliness` |
| Project | `useProjectDashboard`, `useProjectTimeline`, `useProjectExport`, `useProjectListExport`, `useProjectCreateDraft` |
| Risk | `useRiskImport`, `useRiskExport`, `useRiskTable` |
| Daily report | `modules/daily-report/composables/useDailyReportHistoryExport` — **async**: `axios.get` `daily-reports.export-data` (toàn bộ kết quả lọc) → dựng workbook 7 sheet `xlsx-js-style` |
| Other | `useNotifications`, `useDocumentPreview`, `useDialog`, `useFormat`, `useConfirmClose`, `useVirtualScroll`, `useNormalizeList` |

**Quy tắc:** Excel I/O trong composable — **không** import `xlsx` trong `.vue`. Xuất "toàn bộ dữ liệu lọc" (vượt trang hiện tại) → fetch endpoint JSON rồi build client-side; `xlsx-js-style` **không** vẽ chart gốc (dùng bảng tổng hợp + conditional formatting thay thế).

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

### Datagrid toolbar (bảng)

Rule: `.cursor/rules/datagrid-toolbar.mdc` · skill `.cursor/skills/datagrid-toolbar/SKILL.md`.

| Ngữ cảnh | Ví dụ |
|----------|--------|
| Trang Index, ô tìm dài | `Pages/AiAccount/CostReport.vue`, `Pages/Coaching/Sessions/Index.vue` |
| Toolbar SaaS đầy đủ (một hàng desktop, grid, datepicker) | `Pages/DailyReport/History.vue` |
| Tab dự án: tìm `half`, nút cùng hàng, lọc opt-in | `modules/project/components/Dashboard/ProjectFeedbackPanel.vue` |

Shared: `DatagridToolbarSearch` (`hide-label`, `inline-actions`, …), `DatagridToolbarActionButton`, `DatagridSegmentedControl`, `DatagridFilterField`, `FilterDatePicker`, `FilterVisibilityDropdown`, `useVisibleFilterControls` (`default: false`). Grid lọc: `xl:grid-cols-6`, `gap-3`, `h-10`.

### Coaching pages

| Page | Composables / module |
|---|---|
| `Coaching/Dashboard.vue` | `CoachingWorkspace.vue`, `useCoachingExport.js` |
| `Coaching/Sessions/Index.vue` | `useCoachingSessionList.js`, `CoachingSessionsSummaryBar.vue`, table/group views |
| `Coaching/Sessions/Schedule.vue` | `useCoachingCalendar.js`, `MiniCalendar.vue` |

Doc module: `docs/COACHING_MENTORING.md`.

### Knowledge Base pages

| Page | Composables / components |
|---|---|
| `KnowledgeBase/Index.vue` | `useKbExport.js`, datagrid shared UI, sidebar in-page |
| `KnowledgeBase/Show.vue` | `CommentThread`, TOC từ props `toc` |
| `KnowledgeBase/Edit.vue` | `KbRichTextField`, `KbImageGallery` |

Doc module: `docs/KNOWLEDGE_BASE.md`.

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
