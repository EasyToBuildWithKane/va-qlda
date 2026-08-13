# FRONTEND STRUCTURE — VA Workspace

> **Cập nhật 2026-07-30** — Hub `WorkspaceConfig` + `modules/workspace-config`; evaluation là item con. Gỡ `modules/people` (org UI). DailyReport + KnowledgeBase trong `modules/`; Content header (`PageHeader` + `#header`); FullCalendar (lịch dự án).

> Rule agent: `.cursor/rules/content-header.mdc` · skill `content-header`.

---

## 1. Technology Stack Frontend

| Công Nghệ | Phiên Bản | Mục Đích |
|---|---|---|
| Vue.js | 3.5.35 | UI framework (Composition API, `<script setup>`) |
| Inertia.js | 2.x | SPA bridge với Laravel (`@inertiajs/vue3`) |
| Pinia | 3.x | Global state (`stores/auth.js`, `stores/ui.js`) |
| Tailwind CSS | 3.4.19 | Utility-first CSS |
| Vite | 5.0 | Build tool + manual code splitting |
| TipTap | 3.24.0 | Rich text editor |
| FullCalendar | 6.1.x | Lịch dự án (tháng / tuần·ngày timeGrid / danh sách) |
| Chart.js / vue-chartjs | 4.x / 5.x | Biểu đồ (dashboard, CLM, hiệu suất) |
| frappe-gantt | 1.2.x | Gantt timeline dự án |
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
- `manualChunks`: vendor-vue, vendor-tiptap, vendor-chart, vendor-excel (`xlsx-js-style`), vendor-datepicker, vendor-calendar (FullCalendar), vendor-docx, vendor-utils; `reportCompressedSize: false`, `chunkSizeWarningLimit: 600` (gantt/ogl tách theo dynamic import khi được dùng)
- Project Show: `ProjectCalendar` lazy qua `defineAsyncComponent` (chỉ tải khi mở tab Lịch) — `useProjectCalendar` (filter/KPI/unscheduled), hover card, cột «Chưa lịch», persist view `localStorage`

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
│   ├── Layout/               ← App shell sidebar (AppSidebar*)
│   ├── AppIcon.vue
│   └── Notifications/
├── modules/                  ← Feature modules (Phase 2+) — mỗi module có components/ (+ composables/, config/ khi cần):
│   ├── project/                  ← components (ProjectCard, Sprint/, TaskDetail/, Dashboard/), config/
│   ├── daily-report/             ← components (GradePill, ScoringPanel, ReportCard, …), config/reportConfig.js, composables/
│   ├── routine-task/             ← Nhật ký việc thường xuyên: SummaryBar, FormModal, ListRow, PeopleBar, useRoutineTasks
│   ├── knowledge-base/           ← components (KbArticleHero, KbRichTextField, KbBlogSidebar, …), composables/
│   ├── contract/                 ← CLM: components (*SummaryBar, charts), composables, config
│   ├── credential/               ← Kho mật khẩu: components, composables
│   ├── performance/              ← Dashboard + audit components/composables
│   ├── profile/                  ← Hồ sơ (/profile): HR identity + skill matrix **read-only** (mirror VA-HRM); không form identity/avatar/skill local
│   ├── onboarding/               ← WelcomeScreen + WelcomePanel (hero + nội dung, max-w-3xl, không badge/cắt tên); useOnboardingWelcome; settings một viewport
│   ├── notifications/            ← Bell, center drawer, preferences
│   ├── audit/                    ← Audit trail viewer components/composables
│   ├── aiAccount/                ← Tài khoản AI: FormModal 4 tab (Thông tin·Chứng từ·Chi phí&hạn·Phân quyền) + AccessGrantsPanel + Index; CostReport + AiCostReportSummaryBar (KPI strip)
│   ├── evaluation/               ← Tiêu chí đánh giá: SummaryBar, CriterionFormModal, ActivityTimeline, CategoryTabs, RowActions, DepartmentAutocomplete, columns, export
│   ├── evaluation-template/      ← Mẫu đánh giá: SummaryBar, EvaluationTemplateForm (+ FormModal sửa), MultiCatalogSelect, DataModal, columns, import/export composables
│   └── workspace-config/         ← Hub: ProfileCard/Grid, InsightsBanner, ProfileDrawer, export composable
├── shared/                   ← Cross-module (Phase 2 + 4)
│   ├── ui/                   ← Badge, Avatar, ProgressBar, form/*, **KpiSummaryStrip**, …
│   └── composables/          ← useToast, usePermission, useFilter, useOrgTeamPeople (Congnghe chart)
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
AppChrome.vue (persistent shell)
├── AppSidebar + AppSidebarMobileDrawer (`Components/Layout/`, `composables/useAppSidebar.js`)
└── slot → AppLayout.vue
      ├── topbar (hamburger mobile → openMobile)
      ├── NotificationBell + NotificationCenterDrawer
      ├── UserMenu
      ├── <slot> → Inertia Page
      ├── ToastContainer → shared/composables/useToast
      └── AppDialog → useDialog
```

**Sidebar UX (2026-07, rule `app-sidebar`):** Desktop `lg+` — expanded (`w-sidebar-expanded` / 15.5rem) hoặc rail (`w-sidebar-rail` / 4rem), surface `.sidebar-surface` (brand `#9A0036`), nav `text-xs` + section `uppercase tracking-[0.05em]`, active `bg-sidebar-active`, logo wordmark / mark khi rail, tooltip + flyout trắng khi rail; `< lg` — drawer (`w-sidebar-drawer`). Persist: `va-workspace.sidebar.rail`, `va-workspace.sidebar.collapsed`; nhóm chứa route active luôn mở. User menu chỉ ở topbar (không footer sidebar).

**Badge số liệu thật trên nav:** mục có `badgeKey` được `App\Support\NavigationBadges::decorate()` gắn `item.badge` (số đếm thực); render pill đỏ ở expanded/mobile, số góc icon + chấm trên nhóm thu gọn ở rail, pill trong flyout. Ẩn khi count = 0. Xem [§12 Role-Based UI](#12-role-based-ui).

---

## 5. Inertia Pages

| Domain | Files |
|---|---|
| Auth | `Pages/Auth/Login.vue` |
| Congnghe (landing `/congnghe`) | `Pages/Congnghe/Index.vue` + `partials/*` — **không** `AppLayout`; hero: `HeroSection.vue` + `HeroTechOrbit.vue` (3 vòng quỹ đạo stack công nghệ sau mascot); `CongnghePageShell.vue` cho form/chi tiết người gửi (prop Inertia `chrome`: `nav` + `footer` từ `CongngheContentRepository::portalChrome()`); header `CongngheNavbar.vue` + menu `CongngheUserMenu.vue` (đề xuất đã gửi, hồ sơ, đăng xuất); form `Proposal.vue`; **người gửi:** `MyProposals.vue` (`CongngheMyProposalsSummaryBar.vue`, `congnghe-portal-*` theme), `MyProposalShow.vue` (`/congnghe/de-xuat-cua-toi`) — cổng Congnghe, **không** sidebar Workspace; **quản lý (admin/lead):** `Pages/Congnghe/Proposals/Index.vue` (`CongngheSoftwareProposalsSummaryBar.vue`, datagrid toolbar chuẩn), `Show.vue` (`CongngheSoftwareProposalSheet.vue` — layout phiếu + xem trước đính kèm) — `AppLayout`, sidebar nhóm «Quản trị» → «Đề xuất phần mềm» |
| Dashboard | **`Hub.vue`** (`/dashboard` — welcome, `HubDashboardSummaryBar`, trend, compliance, module grid) · **`Work.vue`** (`/work`) · `Index.vue` — **`TaskProgressStatsSection`**, **`DailyReportCompliancePanel`**, `ProjectProgressCard`, biểu đồ xu hướng & trạng thái dự án |
| MyWork | **`Index.vue`** (`/my-work`) — bucket Quá hạn/Hôm nay/Sắp tới/Chưa hạn; segmented **Thẻ / Bảng** + nhóm Theo hạn · Theo dự án (mặc định) · Tất cả; list nhóm **dòng ngang** theo dự án (`MyWorkTaskTable` + `MyWorkTaskListRow`, cột dính tiêu đề, không cột Thao tác — bấm tên việc mở modal); hàng quá hạn tô nền đỏ (không badge); trạng thái/ưu tiên = chấm + chữ (`MyWorkToneLabel`); chọn cột `config/columns.js`; toolbar Lọc/Cột/Xuất; `partials/MyWorkTaskDetailModal` (`max-w-5xl` + `fitViewport`, header tiến độ + lưới nhãn 2 cột); team: `MemberWorkModal`, `TeamWorkDepartmentLanes`, `TeamRoster`; composable `useMyWork` |
| DailyReport | `Today`, `History`, `Show`, `Review` |
| RoutineTask | **`Index.vue`** (`/routine-tasks`) — nhật ký list theo ngày; modal form ngang `fitViewport`; KPI 6 thẻ (gồm giờ ET/thực tế); people strip thành viên dưới quyền; `modules/routine-task/` |
| Project | `Index`, `Create`, `Edit`, `Show` — chi tiết UX/tab → `docs/PROJECT_MANAGEMENT.md`. `Show`: 9 tab (overview, documents, timeline, board, sprints, blockers, qa, feedback, weekly); strip icon-trên/nhãn-dưới, `auto-fit minmax(7.75rem)` — không cắt chữ. Props bổ sung: `testCases` (`TestCaseResource[]`), `testSuites` (`TestSuiteResource[]`), `testCaseSummary`. Tab `blockers` dùng `ProjectBlockerPanel` (KPI strip nhúng); tab `qa` dùng `ProjectTestCasePanel` stub (`modules/testcase/components/`) |
| Blocker / Feedback | `Index`, `Show` (Feedback) — Index: **`FeedbackSummaryBar`** (dải KPI `kpi-strip` / `kpi-card`, lọc nhanh scope/status; rule `kpi-summary-strip`) + datagrid toolbar (Lọc/Cột), `FilterDatePicker` khoảng ngày, `FeedbackListRowActions` |
| TestCase (QA) | `Pages/TestCase/Index.vue` (planned) — datagrid toolbar, KPI strip `{total, ready, pass, fail, not_run}`, lọc theo project/suite/priority/status/last_result; Inertia props `testCases`, `summary`, `options`, `can.create`; thực thi qua `POST /test-cases/{id}/execute` |
| Profile | `Pages/Profile/Show.vue` — hồ sơ cá nhân; org directory UI đã gỡ (HRM) |
| Performance | `Pages/Performance/Dashboard.vue` · **`Audit.vue`** (danh sách audit nhân sự, chọn **phòng ban HRM** trên toolbar, segmented Tuần/Tháng/Quý, cột **Đơn vị** từ HRM, cột **Kỳ** = nhóm dòng collapse theo kỳ con — pattern `Blocker/Index`, `emptyDisplay.js`, `PerformanceAuditSummaryBar` mode list) · **`AuditShow.vue`** (KPI strip đầu trang + `PerformanceFilterBar` + timeline, back về index) |
| Evaluation config | `Pages/WorkspaceConfig/Evaluation/{Index,Show}.vue` + `modules/evaluation/` — CRUD modal; phòng ban autocomplete (trống = chung); mã `TCVA###`; thang điểm JSON (nhãn + trọng số, 2–10 mức); Index cột mức điểm động (header Mức 1…N); KPI strip + Lọc/Cột/Xuất; nhóm PB + tab loại; Show timeline audit + avatar |
| Evaluation templates | `Pages/WorkspaceConfig/EvaluationTemplates/{Index,Create,Show}.vue` + `modules/evaluation-template/` — **Thêm mẫu** Create (zoom 90%; đối tượng XOR chức danh\|cấp bậc; tiêu chí picker hẹp + panel đã chọn rộng); tiêu chí catalog + tuỳ chỉnh, trường form phụ; **Dữ liệu** → DataModal; Show + sửa modal + danh sách phiếu theo mẫu |
| Evaluation forms | `Pages/WorkspaceConfig/EvaluationForms/{Index,Create,Edit}.vue` + `Scoring/{Index,Show}.vue` + `modules/evaluation-form/` — list + KPI + Xuất; wizard 3 tab: `FormGeneralTab` (mã lock/unlock, AC mẫu, loại có mô tả, kỳ chip + 1 ngày áp dụng, RadioCard order, hội đồng + chức danh HRM, trường placeholder; không status trên form), tiêu chí + trọng số, tab nhân sự inline-edit; chấm điểm; `/workspace-config/evaluation-forms` |
| Workspace config hub | `Pages/WorkspaceConfig/Hub.vue` + `Workspace/Show.vue` + `modules/workspace-config/` (`WorkspaceProfileCard`, InsightsBanner, ProfileDrawer, `WorkspaceConfigItemGrid`, **`WorkspaceNavMenuPanel`** menu sidebar theo PB, `useWorkspaceHubExport`, checklist, bulk ensure) — `/workspace-config`, `/workspace-config/w/{code}`; doc `WORKSPACE_CONFIG.md` |
| Daily report scoring (Workspace) | `Pages/WorkspaceConfig/DailyReportScoring/Edit.vue` — trọng số 4 tiêu chí + Kaizen theo PB; `/workspace-config/daily-report-scoring` |
| Notifications | `Pages/Notifications/Management.vue` |
| AiAccount | `Index.vue` · **`CostReport.vue`** (`/ai-accounts/cost-report`) — `AiCostReportSummaryBar` (KPI strip 5 thẻ: tổng/active/sắp hết hạn/hết hạn/chi phí tháng) + bảng theo nhóm; rule `kpi-summary-strip` |

Pages import feature components từ `@/modules/project/components/...` và primitives từ `@/shared/ui/...`.

---

## 6. Component Catalog

### 6.1 UI Primitives — `Components/Ui/`

`Modal.vue`, `Drawer.vue`, `AppDialog.vue`, `ToastContainer.vue`, `PageHeader.vue`

**Content header (2026-06):** Mỗi Inertia page đặt **một** `PageHeader` trong `AppLayout` slot `#header` (topbar `h-14`). Props: `title`, `subtitle`, `icon` (khớp `App\Support\Navigation.php`), `icon-color` (thường `brand`), `badge` tùy chọn. Prop `back-href` chỉ trang drill-down (Create/Edit/Show con) — không dùng cho mục sidebar cấp 1 (vd. tài khoản AI, báo cáo ngày). Actions trong default slot: `btn-primary` / `btn-ghost`, `h-9`. Mẫu: `Pages/AiAccount/Index.vue`, `Pages/AiAccount/CostReport.vue`, `Pages/DailyReport/History.vue`.

### 6.1b App shell — `Components/Layout/`

`AppSidebar.vue`, `AppSidebarBrand.vue`, `AppSidebarExpandedNav.vue`, `AppSidebarRailNav.vue`, `AppSidebarRailFlyout.vue`, `AppSidebarRailTooltip.vue`, `AppSidebarMobileDrawer.vue` — gắn `Layouts/AppChrome.vue` (không remount theo page); `AppLayout` chỉ fallback khi mount đơn lẻ. Logic: `composables/useAppSidebar.js`. CSS tokens: `--spacing-sidebar-*`, `--color-sidebar` (`#9A0036`), `.sidebar-surface` trong `resources/css/app.css`. Skill: `.cursor/skills/app-sidebar/SKILL.md`.

### 6.2 Shared UI — `shared/ui/`

| Component | Mô tả |
|---|---|
| `Badge.vue`, `Avatar.vue`, `ProgressBar.vue` | Status / user / progress |
| `MoneyInput.vue`, `MultiChips.vue`, `RadioCard.vue`, `FieldTooltip.vue` | Form helpers |
| `SearchSelect.vue`, `SearchMultiSelect.vue` | Dropdown tìm-kiếm 1 chọn / nhiều chọn (teleport, viewport-aware) |
| `form/AutocompleteInput.vue` | Ô nhập gõ-để-tìm (combobox), hỗ trợ tạo mới (`creatable`) |
| `CommentThread.vue` | Thread bình luận (Task/Bug/Blocker) |
| `EmptyState.vue`, `LoadingSpinner.vue`, `SkeletonLoader.vue` | Empty / loading |
| `DatagridToolbarSearch.vue`, `FilterVisibilityDropdown.vue`, `ColumnVisibilityDropdown.vue` | Toolbar bảng (tìm kiếm, bật lọc/cột) |
| `DatagridToolbarActionButton.vue`, `DatagridSegmentedControl.vue`, `DatagridFilterField.vue` | Nút Lọc/Cột/Xuất, segmented, ô lọc grid `w-full h-10` |
| `FilterDatePicker.vue` | Lọc ngày (`@vuepic/vue-datepicker` v14: `formats.input` `dd/MM/yyyy` ↔ ISO, locale date-fns `vi` — không truyền chuỗi `"vi"`) |
| `form/FormField.vue`, `TextInput.vue`, `SelectInput.vue`, `DateInput.vue`, `TimeInput.vue` | Form primitives (`input--picker`: date/time hiển thị rõ, click cả ô) |

### 6.3 Project Module — `modules/project/components/`

| Nhóm | Components |
|---|---|
| Core | `ProjectCard`, `ProjectDataGrid`, `ProjectForm`, `ProjectDepartmentAccessPanel` (phòng phụ trách + phòng liên đới dạng ô chọn/thẻ tag), `ProjectMembers`, `GanttChart`, `TaskBoard`, … · Index Kanban: lane trắng + inset border; wrap thẻ; nhóm theo loại dự án |
| Sprint/ | `SprintWorkspace`, `SprintTaskRows`, `SprintTaskTable` (không cột SLA), `SprintDataModal`, `TaskDetailPanel`, … |
| TaskDetail/ | `TaskDetailGeneralInfo` (Thông tin chung 2 cột trong panel), `TaskDetailRichEditor`, `TaskDetailCollaboration`, `TaskDetailSubtasks`, … |
| Dashboard/ | `ProjectShowSummaryBar` (tab Tổng quan Show — KPI embedded), `WorkloadTable` (phân bổ tải + thêm/sửa thành viên), `ProjectOverviewCard`, `ProjectBlockerPanel` (tab Vướng mắc — KPI strip nhúng + import/export/reconcile modal), `ProjectFeedbackPanel`, `BlockerDataModal` (3 tab: Nhập/Xuất/Đối soát), `BlockerInlineDetail`, `ActivityFeed`, … |
| WeeklyReport/ | `WeeklyReportWorkspace` — tab `weekly` / `overview` embedded. Kỳ theo khoảng ngày (toàn dự án, không kẹp Sprint). Toolbar **một hàng**: `WeeklyReportTimelineNav` (từ–đến ngày) + `WeeklyReportHeader` (nhãn engine AI/nội bộ, trạng thái, Gửi duyệt, Cập nhật, Xuất, **Tạo lại** ghi đè toàn bộ). Tab Tổng quan **chưa có báo cáo**: empty (`WeeklyReportEmptyState` `readOnly`) — không date range / không Tạo báo cáo. 3 thẻ nội dung, rủi ro, lịch sử phiên bản. Prompt tùy chỉnh tại `/settings/ai` |
| Timeline/ | `ProjectTimelineView`, `ProjectTimelineBurndown`, … |
| Documents/ | `ProjectDocumentsPanel`, `DocumentContextMenu`, `DocumentFolderCard` (lavender), `DocumentFileCard`, `DocumentFilesTable`, `DocumentPreviewPane` + `useDocumentPreview` — Drive; kéo thả; chuột phải; bulk; tìm trong thư mục; Word preview tờ giấy (letterhead + watermark); đính kèm task hẹp |
| Modals | `TaskFormModal`, `SprintFormModal`, `BlockerFormModal`, … |

### 6.4 Credential module — `modules/credential/`

| Path | Mô tả |
|---|---|
| `Pages/Credential/` | Index, Show (4 tab: Tổng quan, Bảo mật, Phân quyền, Nhật ký), Create, Edit |
| `config/columns.js` | Cột bảng Index + `useVisibleColumns` |
| `components/CredentialSummaryBar.vue` | KPI strip 6 thẻ + lọc nhanh |
| `components/CredentialPasswordViewer.vue` | Hiện/ẩn/sao chép + audit API |
| `components/CredentialAccessGrantModal.vue` | Modal cấp/sửa quyền (SearchSelect + 2 cột) |
| `components/CredentialAccessGrantRowActions.vue` | Dropdown thao tác trên bảng ACL |
| `components/CredentialDataModal.vue` | Nhập · Xuất · Đối soát |
| `composables/useCredentialImport.js` | Excel template/parse (marker `VA_CREDENTIAL_IMPORT_V1`) |

### 6.5 Contract (CLM) — `modules/contract/`

| Path | Mô tả |
|------|--------|
| `Pages/Contract/` | Dashboard, Index (`ContractPortfolioSummaryBar`), Show, Cost, Reports, … |
| `Pages/Contract/Show.vue` | Chi tiết HĐ — workspace `.contract-show-scale` (`zoom: 0.9`); tab Tổng quan: KPI strip + lưới **55% / 45%** |
| `components/ContractShowSummaryBar.vue` | Tab Tổng quan Show: dải KPI `kpi-strip` 6 thẻ (`variant="embedded"`, `dense-values`), mở tab Hồ sơ / Gia hạn |
| `components/ContractFinanceFormPanel.vue` | Tab Tài chính Show: form nhập dòng `contract_finances` (`MoneyInput`, tooltip; **chi phí năm = tổng HĐ** nhập trực tiếp) — `inject('contractFinanceForm')` |
| `components/ContractExplorer.vue` | Cây NCC → nhóm → hợp đồng trên Index |
| `components/ContractDataModal.vue` | Nhập · Xuất · Đối soát danh mục |
| `components/VendorFormModal.vue` + `VendorFormFields.vue` | Modal thêm/sửa NCC: `fit-viewport` `max-w-5xl`, form ngang 3 cột; loại dịch vụ = tag chọn nhiều + thêm tag mới (`category_names`) |

### 6.6 Daily Report — `modules/daily-report/components/`

`GradePill`, `StatusBadge`, `ReviewScoringPanel` (+ `ScoreSelector` compact), `RichTextField`, `ProjectPicker`, …

Trang chờ duyệt (`Pages/DailyReport/Review.vue`) — master–detail: queue server filter (`queue`/`q`), bulk Duyệt/Trả lại, overview 2 cột (tóm tắt + panel chấm nhận `scoringRubricsByEmployee`).

Trang lịch sử (`Pages/DailyReport/History.vue`) — dashboard SaaS:

| Component | Vai trò |
|---|---|
| `DailyReportSummaryBar.vue` | Dải KPI `kpi-strip` (tổng/đã duyệt/chờ/nháp/trễ) + trend pill — thay `ReportDashboard` trên History |
| `ReportDashboard.vue` | *(legacy)* — không còn gắn page; giữ tham chiếu trend pill nếu cần |
| `ReportCard.vue` | Thẻ báo cáo: header (avatar, chức vụ, thời gian, điểm), dự án + task (badge trạng thái), các mục HORENSO thu gọn (`.rich-content`) |

Bộ lọc: shared datagrid (`DatagridToolbarSearch` `hide-label`, `FilterDatePicker` + key `date_range`, grid `xl:grid-cols-6`, `SearchMultiSelect` `control-size="md"`). Nhóm **Ngày / Tuần / Tháng** + **Thẻ / Bảng** (`DatagridSegmentedControl`). Toolbar **không sticky**; lọc hiển thị opt-in (`default: false`, `useVisibleFilterControls`); trạng thái lọc trên URL. Xuất Excel 7 sheet — `useDailyReportHistoryExport`.

### 6.6b Routine Task — `modules/routine-task/`

Nhật ký công việc hằng ngày (`/routine-tasks`) — không đụng Project domain. Lead xem được thành viên dưới quyền (`LedTeamScope`) hoặc toàn bộ nếu có `routine_task.view`.

| Path | Vai trò |
|---|---|
| `components/RoutineTaskSummaryBar.vue` | KPI strip 6 thẻ (tổng / cần làm / đang làm / hoàn thành / giờ ET / giờ thực tế) |
| `components/RoutineTaskFormModal.vue` | Modal `max-w-6xl` + `fitViewport` — 3 cột: giờ+ET, nội dung+vướng mắc, tiến độ+tệp |
| `components/RoutineTaskListRow.vue` | Dòng list: khung giờ, tiến độ, ET/TH, badge vướng mắc, số tệp |
| `components/RoutineTaskPeopleBar.vue` | Chip avatar: tôi + thành viên báo cáo trực tiếp |
| `composables/useRoutineTasks.js` | Inertia create / update / toggle / delete / reorder / xoá tệp |

`Pages/RoutineTask/Index.vue` — nhóm theo ngày (hôm nay / hôm qua / ngày khác / việc lặp lại); toolbar tìm + Lọc (`date_range`); nút **Thêm** mở modal. Sync từ báo cáo ngày qua `SyncDailyReportRoutineTasksUseCase` (sentinel `projects[].id = -1`).

### 6.7 Notifications — `modules/notifications/` + `Components/Notifications/`

`NotificationBell`, `NotificationCenterDrawer`, `NotificationItem`, `NotificationSettingsPanel`. Knowledge Base UI: `modules/knowledge-base/components/` (KbArticleHero, KbRichTextField, KbBlogSidebar, …) — xem [`KNOWLEDGE_BASE.md`](KNOWLEDGE_BASE.md).

---

## 7. Composables

### 7.1 Shared — `shared/composables/`

| File | Mô tả |
|---|---|
| `useToast.js` | Toast state + âm thanh (parity VA-HRM); UI `ToastContainer` góc trên phải, nền đậm success/error |
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
| Vướng mắc (Blocker) | `useBlockerImport` (tên chuẩn: `downloadBlockerImportTemplate`, `parseBlockerImportFile`), `useBlockerExport` (`exportRiskBlockers`), `useBlockerTable` (`useBlockerTable`, `BLOCKER_SEVERITY_DOT`, …), `useBlockerReconcile` (pure fn đối soát) |
| Feedback (tab dự án) | `useFeedbackExport` — xuất Excel/CSV theo lọc client (`ProjectFeedbackPanel`) |
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
| Blocker table columns | `modules/project/components/Dashboard/blockerTableColumns.js` (`BLOCKER_TABLE_COLUMNS`, `loadBlockerTableColumns`) |
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

**Nguồn chuẩn + sơ đồ:** [`docs/IMPORT_EXPORT_RECONCILE.md`](IMPORT_EXPORT_RECONCILE.md).

Một nút **Dữ liệu** → `*DataModal.vue` (3 tab: import · export · reconcile).  
Pattern: `BlockerDataModal` (3 tab Nhập/Xuất/Đối soát), `SprintDataModal` + composables `useBlockerImport`, `useSprintData`.  
Knowledge Base: chỉ **xuất** qua `useKbExport.js` + `GET knowledge-base.export-data` (không modal 3 tab).

### Datagrid toolbar (bảng)

Rule: `.cursor/rules/datagrid-toolbar.mdc` · skill `.cursor/skills/datagrid-toolbar/SKILL.md`.

| Ngữ cảnh | Ví dụ |
|----------|--------|
| Trang Index, ô tìm dài | `Pages/AiAccount/CostReport.vue`, `Pages/Feedback/Index.vue` |
| Toolbar SaaS đầy đủ (một hàng desktop, grid, datepicker) | `Pages/DailyReport/History.vue` |
| Tab dự án: tìm `half`, nút cùng hàng, lọc opt-in | `modules/project/components/Dashboard/ProjectFeedbackPanel.vue` |

Shared: `DatagridToolbarSearch` (`hide-label`, `inline-actions`, …), `DatagridToolbarActionButton`, `DatagridSegmentedControl`, `DatagridFilterField`, `FilterDatePicker`, `FilterVisibilityDropdown`, `useVisibleFilterControls` (`default: false`). Grid lọc: `xl:grid-cols-6`, `gap-3`, `h-10`.

### Knowledge Base pages

| Page | Composables / components |
|---|---|
| `KnowledgeBase/Index.vue` | `useKbExport.js`, datagrid shared UI, lọc danh mục trên toolbar |
| `KnowledgeBase/Blog.vue` | `KbBlogSidebar`, `KbBlogPostCard`, sidebar data từ `KbBlogSidebarData` |
| `KnowledgeBase/Show.vue` | `KbArticleHero`, `KbArticleToc` (mobile), `KbFloatingToolbar`, `KbArticleCommentsSection`, `KbArticleCardsSwiper` (related + more), TOC server `KbContentAnchors`; layout full width |
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

**Cờ hiển thị mục nav** (`Navigation::definition()` item):

| Cờ | Kiểu | Ý nghĩa |
|---|---|---|
| `roles` | allowlist | Chỉ role trong danh sách thấy mục; không khai báo = mọi role. |
| `hideForRoles` | blocklist | Ẩn mục với role trong danh sách (áp dụng **sau** `roles`). VD `['admin']` ẩn «Thông báo», «Gửi đề xuất phần mềm», «Đề xuất của tôi» khỏi admin. |
| `badgeKey` | counter | Khóa badge số liệu thật; `App\Support\NavigationBadges::decorate()` đếm → gắn `item.badge`. |

`badgeKey` hỗ trợ: `notifications_unread` (`NotificationService::unreadCount`), `proposals_new` (đề xuất status `New`). Decorate chạy trong `HandleInertiaRequests::share()` (prop `nav`), **chỉ query khóa còn lại sau lọc role** (không lặp luật phân quyền) và **strip `badgeKey`** trước khi gửi frontend. Thêm badge mới: khai báo `badgeKey` ở `Navigation`, thêm nhánh `match` trong `NavigationBadges::counts()`, render `item.badge` ở `AppSidebarExpandedNav` / `AppSidebarRailNav` / `AppSidebarRailFlyout`.
