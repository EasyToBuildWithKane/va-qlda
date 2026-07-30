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
| FullCalendar | 6.1.x | Lịch dự án (day/week/list) |
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
- Project Show: `ProjectCalendar` lazy qua `defineAsyncComponent` (chỉ tải khi mở tab Lịch)

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
│   ├── knowledge-base/           ← components (KbArticleHero, KbRichTextField, KbBlogSidebar, …), composables/
│   ├── contract/                 ← CLM: components (*SummaryBar, charts), composables, config
│   ├── credential/               ← Kho mật khẩu: components, composables
│   ├── performance/              ← Dashboard + audit components/composables
│   ├── profile/                  ← Hồ sơ (/profile): HR identity + skill matrix **read-only** (mirror VA-HRM); không form identity/avatar/skill local
│   ├── onboarding/               ← useOnboarding / useSmartContext (hint UI đã gỡ khỏi AppLayout)
│   ├── notifications/            ← Bell, center drawer, preferences
│   ├── audit/                    ← Audit trail viewer components/composables
│   ├── aiAccount/                ← Tài khoản AI: composables + components/scan (ProposalScanModal — OCR PĐX, useProposalScan)
│   ├── evaluation/               ← Cấu hình đánh giá: SummaryBar, ConfigForm, CriteriaEditor, columns, export
│   └── workspace-config/         ← Hub cấu hình workspace: ItemGrid (catalog từ backend)
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
| DailyReport | `Today`, `History`, `Show`, `Review` |
| Project | `Index`, `Create`, `Edit`, `Show` — chi tiết UX/tab → `docs/PROJECT_MANAGEMENT.md` |
| Blocker / Feedback | `Index`, `Show` (Feedback) — Index: **`FeedbackSummaryBar`** (dải KPI `kpi-strip` / `kpi-card`, lọc nhanh scope/status; rule `kpi-summary-strip`) + datagrid toolbar (Lọc/Cột), `FilterDatePicker` khoảng ngày, `FeedbackListRowActions` |
| Profile | `Pages/Profile/Show.vue` — hồ sơ cá nhân; org directory UI đã gỡ (HRM) |
| Performance | `Pages/Performance/Dashboard.vue` · **`Audit.vue`** (danh sách audit nhân sự, segmented Tuần/Tháng/Quý, cột **Kỳ** = nhóm dòng collapse theo kỳ con — pattern `Blocker/Index`, `emptyDisplay.js`, `PerformanceAuditSummaryBar` mode list) · **`AuditShow.vue`** (timeline + `PerformanceFilterBar`, back về index) |
| Evaluation config | `Pages/WorkspaceConfig/Evaluation/{Index,Create,Edit,Show}.vue` + `modules/evaluation/` — super-admin; KPI strip + Lọc/Cột/Xuất; nhóm collapse theo phòng ban; Thêm mới trong PageHeader |
| Workspace config hub | `Pages/WorkspaceConfig/Hub.vue` + `modules/workspace-config/` — danh mục mục cấu hình (`/workspace-config`); doc `WORKSPACE_CONFIG.md` |
| Notifications | `Pages/Notifications/Management.vue` |

Pages import feature components từ `@/modules/project/components/...` và primitives từ `@/shared/ui/...`.

---

## 6. Component Catalog

### 6.1 UI Primitives — `Components/Ui/`

`Modal.vue`, `Drawer.vue`, `AppDialog.vue`, `ToastContainer.vue`, `PageHeader.vue`

**Content header (2026-06):** Mỗi Inertia page đặt **một** `PageHeader` trong `AppLayout` slot `#header` (topbar `h-14`). Props: `title`, `subtitle`, `icon` (khớp `App\Support\Navigation.php`), `icon-color` (thường `brand`), `badge` tùy chọn. Prop `back-href` chỉ trang drill-down (Create/Edit/Show con) — không dùng cho mục sidebar cấp 1 (vd. dashboard AI, báo cáo ngày). Actions trong default slot: `btn-primary` / `btn-ghost`, `h-9`. Mẫu: `Pages/AiAccount/Index.vue`, `Pages/AiAccount/Dashboard.vue`, `Pages/DailyReport/History.vue`.

### 6.1b App shell — `Components/Layout/`

`AppSidebar.vue`, `AppSidebarBrand.vue`, `AppSidebarExpandedNav.vue`, `AppSidebarRailNav.vue`, `AppSidebarRailFlyout.vue`, `AppSidebarRailTooltip.vue`, `AppSidebarMobileDrawer.vue` — gắn `Layouts/AppChrome.vue` (không remount theo page); `AppLayout` chỉ fallback khi mount đơn lẻ. Logic: `composables/useAppSidebar.js`. CSS tokens: `--spacing-sidebar-*`, `--color-sidebar` (`#9A0036`), `.sidebar-surface` trong `resources/css/app.css`. Skill: `.cursor/skills/app-sidebar/SKILL.md`.

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
| Dashboard/ | `ProjectShowSummaryBar` (tab Tổng quan Show — KPI embedded + `WorkloadTable`), `ProjectOverviewCard`, `RiskIssueDataTable`, `ProjectFeedbackPanel`, `RiskImportModal`, `ActivityFeed`, … |
| Timeline/ | `ProjectTimelineView`, `ProjectTimelineBurndown`, … |
| Documents/ | `ProjectDocumentsPanel`, `ProjectDocumentTree`, `DocumentPreviewPane`, `ProjectDocumentDetailAside` |
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

### 6.6 Daily Report — `modules/daily-report/components/`

`GradePill`, `StatusBadge`, `ScoringPanel`, `RichTextField`, `ProjectPicker`, …

Trang lịch sử (`Pages/DailyReport/History.vue`) — dashboard SaaS:

| Component | Vai trò |
|---|---|
| `DailyReportSummaryBar.vue` | Dải KPI `kpi-strip` (tổng/đã duyệt/chờ/nháp/trễ) + trend pill — thay `ReportDashboard` trên History |
| `ReportDashboard.vue` | *(legacy)* — không còn gắn page; giữ tham chiếu trend pill nếu cần |
| `ReportCard.vue` | Thẻ báo cáo: header (avatar, chức vụ, thời gian, điểm), dự án + task (badge trạng thái), các mục HORENSO thu gọn (`.rich-content`) |

Bộ lọc: shared datagrid (`DatagridToolbarSearch` `hide-label`, `FilterDatePicker` + key `date_range`, grid `xl:grid-cols-6`, `SearchMultiSelect` `control-size="md"`). Nhóm **Ngày / Tuần / Tháng** + **Thẻ / Bảng** (`DatagridSegmentedControl`). Toolbar **không sticky**; lọc hiển thị opt-in (`default: false`, `useVisibleFilterControls`); trạng thái lọc trên URL. Xuất Excel 7 sheet — `useDailyReportHistoryExport`.

### 6.7 Notifications — `modules/notifications/` + `Components/Notifications/`

`NotificationBell`, `NotificationCenterDrawer`, `NotificationItem`, `NotificationSettingsPanel`. Knowledge Base UI: `modules/knowledge-base/components/` (KbArticleHero, KbRichTextField, KbBlogSidebar, …) — xem [`KNOWLEDGE_BASE.md`](KNOWLEDGE_BASE.md).

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

**Nguồn chuẩn + sơ đồ:** [`docs/IMPORT_EXPORT_RECONCILE.md`](IMPORT_EXPORT_RECONCILE.md).

Một nút **Dữ liệu** → `*DataModal.vue` (3 tab: import · export · reconcile).  
Pattern: `RiskImportModal`, `SprintDataModal` + composables `useRiskImport`, `useSprintData`.  
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
