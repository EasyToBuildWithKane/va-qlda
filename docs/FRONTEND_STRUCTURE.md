# FRONTEND STRUCTURE — VA QLDA

---

## 1. Technology Stack Frontend

| Công Nghệ | Phiên Bản | Mục Đích |
|---|---|---|
| Vue.js | 3.5.35 | UI framework (Composition API) |
| Inertia.js | Latest | SPA bridge với Laravel |
| Tailwind CSS | 3.4.19 | Utility-first CSS |
| Vite | 5.0 | Build tool |
| TipTap | 3.24.0 | Rich text editor |
| Frappe Gantt | 1.2.2 | Gantt chart visualization |
| Chart.js + Vue ChartJS | - | Data visualization |
| XLSX | - | Spreadsheet export/import |
| Ziggy | - | Laravel routes in JavaScript |

---

## 2. Entry Point & App Bootstrap

### app.js
```javascript
// Khởi tạo Inertia app với Vue 3
// Resolve pages từ Pages/ directory
// Mount vào #app element trong app.blade.php
```

### app.blade.php
```html
<!-- Layout gốc Blade -->
<!-- Inject Ziggy routes, Vite assets -->
<!-- Mount point: <div id="app" @inertia> -->
```

---

## 3. Component Hierarchy

```
AppLayout.vue (Main Shell)
│
├── Sidebar Navigation
│   ├── Logo
│   ├── Nav Groups (collapsible)
│   │   └── Nav Items (role-filtered)
│   └── Rail Mode Toggle
│
├── Top Bar
│   ├── Current Date Display
│   └── UserMenu.vue
│       └── Avatar + Dropdown
│
├── <slot> (Page Content)
│   └── [Inertia Page Component]
│
├── ToastContainer.vue (floating notifications)
└── AppDialog.vue (confirm/alert overlay)
```

---

## 4. Inertia Pages

### 4.1 Auth
| File | Mô Tả |
|---|---|
| `Pages/Auth/Login.vue` | Form đăng nhập (username + password) |

### 4.2 Dashboard
| File | Mô Tả |
|---|---|
| `Pages/Dashboard/Index.vue` | Trang tổng quan hệ thống |

### 4.3 Daily Report
| File | Mô Tả |
|---|---|
| `Pages/DailyReport/Today.vue` | Form tạo/sửa báo cáo ngày hôm nay |
| `Pages/DailyReport/History.vue` | Lịch sử báo cáo với bộ lọc |
| `Pages/DailyReport/Show.vue` | Xem chi tiết báo cáo + điểm |
| `Pages/DailyReport/Review.vue` | Hàng chờ chấm điểm (Lead/Admin) |

### 4.4 Project
| File | Mô Tả |
|---|---|
| `Pages/Project/Index.vue` | Danh sách dự án (grid/kanban + filters) |
| `Pages/Project/Create.vue` | Form tạo dự án mới |
| `Pages/Project/Edit.vue` | Form chỉnh sửa dự án |
| `Pages/Project/Show.vue` | Dashboard dự án (phức tạp nhất) |

### 4.5 Issue Tracking
| File | Mô Tả |
|---|---|
| `Pages/Blocker/Index.vue` | Danh sách vướng mắc |
| `Pages/Bug/Index.vue` | Danh sách lỗi |
| `Pages/Bug/Show.vue` | Chi tiết lỗi |
| `Pages/Feedback/Index.vue` | Danh sách góp ý |
| `Pages/Feedback/Show.vue` | Chi tiết góp ý |

### 4.6 Organization
| File | Mô Tả |
|---|---|
| `Pages/Department/Index.vue` | Quản lý phòng ban |

### 4.7 Notifications
| File | Mô Tả |
|---|---|
| `Pages/Notifications/Management.vue` | Trang quản lý thông báo (admin/lead) — Inertia page |

---

## 5. Component Catalog

### 5.1 UI Primitives (Components/UI/)
| Component | Mô Tả |
|---|---|
| `Modal.vue` | Base modal dialog |
| `AppDialog.vue` | Confirm/alert dialog (app-wide) |
| `Drawer.vue` | Slide-in panel |
| `ToastContainer.vue` | Toast notification container |
| `PageHeader.vue` | Page title + breadcrumb |

### 5.2 Shared Project Components (Components/Project/)
| Component | Mô Tả | Ghi Chú |
|---|---|---|
| `Avatar.vue` | User avatar với initials fallback | Nên chuyển → `shared/ui/` |
| `Badge.vue` | Status/priority badge | Nên chuyển → `shared/ui/` |
| `ProgressBar.vue` | Progress indicator | Nên chuyển → `shared/ui/` |
| `MoneyInput.vue` | Currency input field | Nên chuyển → `shared/ui/` |
| `MultiChips.vue` | Tag/chip input | Nên chuyển → `shared/ui/` |
| `RadioCard.vue` | Styled radio button | Nên chuyển → `shared/ui/` |
| `FieldTooltip.vue` | Form field help tooltip | Nên chuyển → `shared/ui/` |
| `PersonSelect.vue` | Employee picker dropdown | Feature-agnostic, nên ở `shared/` |
| `CommentThread.vue` | Comments display + reply | Dùng chung cho Task/Bug/Blocker |

### 5.3 Project Module Components (Components/Project/)
| Component | Mô Tả |
|---|---|
| `ProjectCard.vue` | Project preview card |
| `ProjectDataGrid.vue` | Grid/Kanban portfolio view |
| `ProjectForm.vue` | Create/edit project form |
| `ProjectMetadata.vue` | Project info panel |
| `ProjectActions.vue` | CTA buttons |
| `ProjectMembers.vue` | Team member list |
| `CostSummary.vue` | Budget/cost display |
| `GanttChart.vue` | Gantt timeline (Frappe Gantt) |
| `TaskBoard.vue` | Kanban board |
| `TaskCard.vue` | Task card |
| `TaskFormModal.vue` | Create/edit task modal |
| `TaskFormBulkPanel.vue` | Bulk task import panel |
| `SprintFormModal.vue` | Sprint creation/edit |
| `MemberFormModal.vue` | Add team member |
| `WorklogFormModal.vue` | Log hours |
| `BugFormModal.vue` | Report bug |
| `FeedbackFormModal.vue` | Submit feedback |
| `BlockerFormModal.vue` | Raise blocker |
| `DepartmentFormModal.vue` | Create/edit department |
| `UserMenu.vue` | Top-right user dropdown |

### 5.4 Sprint Sub-Module (Components/Project/Sprint/)
| Component | Mô Tả |
|---|---|
| `SprintWorkspace.vue` | Sprint planning area (main) |
| `SprintListView.vue` | Sprint list with tasks |
| `SprintTaskTable.vue` | Table view của sprint tasks |
| `SprintTaskRows.vue` | Drag-drop rows |
| `SprintCalendarView.vue` | Calendar view |
| `SprintDataModal.vue` | Sprint detail modal |
| `TaskDetailPanel.vue` | Task detail slide-in |

### 5.5 Task Detail Sub-Module (Components/Project/TaskDetail/)
| Component | Mô Tả |
|---|---|
| `TaskDetailRichEditor.vue` | TipTap editor cho task description |
| `TaskDetailAttachments.vue` | File attachments panel |
| `TaskDetailSubtasks.vue` | Subtask management |
| `TaskDetailCollaboration.vue` | Watchers, comments |

### 5.6 Project Dashboard Sub-Module (Components/Project/Dashboard/)
| Component | Mô Tả |
|---|---|
| `ProjectOverviewCard.vue` | Quick stats card |
| `DeadlineBanner.vue` | Deadline warning banner |
| `DashboardViewToggle.vue` | View mode switcher |
| `WorkloadTable.vue` | Team workload breakdown |
| `OverviewTaskList.vue` | Task summary list |
| `GanttMini.vue` | Mini Gantt overview |
| `ActivityFeed.vue` | Recent activity stream |
| `RiskIssuePanel.vue` | Blockers/risks panel |
| `RiskIssueDataTable.vue` | Risk data table |
| `RiskIssueDetailPanel.vue` | Risk detail slide-in |
| `RiskImportModal.vue` | Bulk risk import |

### 5.7 Timeline Sub-Module (Components/Project/Timeline/)
| Component | Mô Tả |
|---|---|
| `ProjectTimelineView.vue` | Full timeline view |
| `ProjectTimelineCenter.vue` | Timeline center panel |
| `ProjectTimelineBurndown.vue` | Burndown chart |

### 5.8 Documents Sub-Module (Components/Project/Documents/)
| Component | Mô Tả |
|---|---|
| `ProjectDocumentsPanel.vue` | Document list + upload |
| `DocumentPreviewPane.vue` | File preview panel |

### 5.9 Daily Report Components (Components/DailyReport/)
| Component | Mô Tả |
|---|---|
| `GradePill.vue` | Grade A-F display badge |
| `StatusBadge.vue` | Report status badge |
| `ScoringPanel.vue` | Reviewer scoring form (5 criteria) |
| `RichTextField.vue` | TipTap wrapper for reports |
| `MarkdownField.vue` | Markdown display |
| `ProjectPicker.vue` | Multi-select projects for report |
| `ProjectSelect.vue` | Single project select |
| `TemplateGallery.vue` | Report template picker |
| `InfoTooltip.vue` | Help tooltips |

### 5.10 Notification Components (Components/Notifications/) ✨ MỚI
| Component | Mô Tả |
|---|---|
| `NotificationBell.vue` | Bell icon + unread badge (trong AppLayout header) |
| `NotificationCenterDrawer.vue` | Slide-in drawer hiển thị danh sách thông báo, filters, cursor pagination |
| `NotificationItem.vue` | Một thông báo: icon, title, body, time, read state |
| `NotificationSettingsPanel.vue` | Panel cài đặt: enabled types, channels (in-app/email/push) |

### 5.11 App-level Components
| Component | Mô Tả |
|---|---|
| `AppIcon.vue` | Lucide icon wrapper |

---

## 6. Composables

### 6.1 Hiện Tại — Sprint Module
| File | Mô Tả |
|---|---|
| `useSprintData.js` | State management cho toàn bộ sprint workspace (tasks, filters, views) |
| `useSprintFilters.js` | Filter logic cho sprint task list |
| `useSprintWorkspace.js` | Sprint view toggling, active sprint state |
| `useSprintTaskTable.js` | Tabular view logic, column config |
| `useSprintTaskStatusPatch.js` | Optimistic status update via PATCH |
| `useSprintReconcile.js` | Import/export reconciliation logic |
| `useSprintExport.js` | Sprint export to Excel |

### 6.2 Hiện Tại — Task Module
| File | Mô Tả |
|---|---|
| `useTaskWorkspace.js` | Task board state, drag-drop, panel open/close |
| `useTaskBulkCreate.js` | Bulk task creation từ CSV/text |
| `useTaskHierarchy.js` | Subtask tree management |
| `useTaskPhaseGroups.js` | Nhóm tasks theo phase |
| `useTaskTimeliness.js` | Tính toán late/overdue state |

### 6.3 Hiện Tại — Project Module
| File | Mô Tả |
|---|---|
| `useProjectDashboard.js` | Dashboard data aggregation, view toggle |
| `useProjectTimeline.js` | Gantt/timeline data preparation, zoom, scroll |
| `useProjectExport.js` | Export single project data to Excel |
| `useProjectListExport.js` | Export project list to Excel |
| `useProjectCreateDraft.js` | Draft state khi tạo project mới |

### 6.4 Hiện Tại — Risk/Blocker Module
| File | Mô Tả |
|---|---|
| `useRiskTable.js` | Risk/blocker table state, column config |
| `useRiskImport.js` | Import blockers từ Excel (parse, validate, submit) |
| `useRiskExport.js` | Export blockers to Excel |

### 6.5 Hiện Tại — Document Module
| File | Mô Tả |
|---|---|
| `useDocumentPreview.js` | File preview logic (PDF, image, office docs) |

### 6.6 Hiện Tại — Notification Module ✨ MỚI
| File | Mô Tả |
|---|---|
| `useNotifications.js` | Fetch, poll, mark-read, preferences, cursor pagination |
| `notificationMeta.js` | Metadata mapping: type → icon, color, label |

### 6.7 Hiện Tại — Shared/Utility
| File | Mô Tả |
|---|---|
| `useToast.js` | Toast state management |
| `useDialog.js` | Imperative confirm/alert dialog API (app-wide AppDialog singleton) |
| `useFormat.js` | Format helpers: số tiền, ngày tháng, text truncate |
| `useConfirmClose.js` | Confirm trước khi đóng form có unsaved changes |
| `useNormalizeList.js` | Normalize list responses (array hoặc paginated object) |
| `useVirtualScroll.js` | Virtual scrolling cho danh sách lớn |

### 6.8 Đề Xuất Thêm (Còn Thiếu)
| File | Mô Tả | Ưu Tiên |
|---|---|---|
| `usePermission.js` | Check user role/permissions (isAdmin, isLead...) | High |
| `useForm.js` | Form state + submit + errors wrapper chuẩn hoá | High |
| `useFilter.js` | URL-bound filter state management cho list pages | Medium |
| `useDebounce.js` | Debounce utility | Low |
| `useClipboard.js` | Copy to clipboard | Low |

---

## 7. Config Files (Không Phải Component)

| File | Vị Trí Hiện Tại | Vị Trí Đề Xuất |
|---|---|---|
| `projectColumns.js` | `Components/Project/` | `modules/project/config/columns.js` |
| `reportConfig.js` | `Components/DailyReport/` | `modules/daily-report/config/reportConfig.js` |
| `notificationMeta.js` | `composables/` | `modules/notifications/config/meta.js` |
| `riskTableColumns.js` | `Components/Project/Dashboard/` | `modules/project/config/riskColumns.js` |
| `sprintTableColumns.js` | `Components/Project/Sprint/` | `modules/project/config/sprintColumns.js` |

---

## 8. UI Patterns

### 8.1 Toast Notifications
```javascript
// Sử dụng useToast composable
const { addToast } = useToast()
addToast({ type: 'success', message: 'Đã lưu thành công' })
```

### 8.2 Confirm Dialog
```javascript
// AppDialog component (app-wide singleton)
// Triggered via event bus hoặc Inertia flash
```

### 8.3 Modal Pattern
```vue
<!-- Wrapper component Modal.vue -->
<Modal :show="show" @close="show = false">
  <template #header>Tiêu đề</template>
  <template #default>Nội dung</template>
  <template #footer>Nút bấm</template>
</Modal>
```

### 8.4 Inertia Data Flow
```
Backend (Controller)
    ↓ Inertia::render('Page/Component', ['prop' => $data])
Frontend (Page Component)
    ↓ defineProps({ prop: Object })
    ↓ Pass xuống child components via props
```

### 8.5 Form Submission Pattern
```javascript
// Dùng useForm từ @inertiajs/vue3
const form = useForm({ field: '' })
form.post(route('resource.store'))
form.processing // loading state
form.errors.field // validation errors
```

---

## 9. Role-Based UI Visibility

Navigation items và action buttons được filter theo role:

```javascript
// Navigation.php (backend) filter theo role
// Truyền qua HandleInertiaRequests middleware
// Frontend nhận qua: usePage().props.auth.role
```

| Role | Visibility |
|---|---|
| `admin` | Tất cả menu items + admin actions |
| `lead` | Project management + Daily Report review |
| `member` | Daily Report + Projects mình tham gia |
| `viewer` | Read-only views |

---

## 10. Shared Resources

### CSS / Styling
- `resources/css/app.css` — Tailwind base imports + custom utilities
- `tailwind.config.js` — Custom colors, spacing, fonts

### Icons
- `AppIcon.vue` wraps Lucide icons
- Usage: `<AppIcon name="folder" />`

### Routes (Ziggy)
- Laravel routes exposed via Ziggy
- Usage in JS: `route('projects.show', { project: id })`
