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

### 5.10 App-level Components
| Component | Mô Tả |
|---|---|
| `AppIcon.vue` | Lucide icon wrapper |

---

## 6. Composables

### Hiện Tại
| File | Mô Tả |
|---|---|
| `composables/useToast.js` | Toast state management (show/hide notifications) |

### Đề Xuất Thêm
| File | Mô Tả | Ưu Tiên |
|---|---|---|
| `useDialog.js` | Centralize confirm/alert dialog state | High |
| `useForm.js` | Form state + submit + errors wrapper | High |
| `usePermission.js` | Check user role/permissions | High |
| `useFilter.js` | URL-bound filter state management | Medium |
| `useInfiniteScroll.js` | Infinite scroll / pagination | Medium |
| `useDebounce.js` | Debounce utility | Low |
| `useClipboard.js` | Copy to clipboard | Low |

---

## 7. Config Files (Không Phải Component)

| File | Vị Trí Hiện Tại | Vị Trí Đề Xuất |
|---|---|---|
| `projectColumns.js` | `Components/Project/` | `modules/project/config/columns.js` |
| `reportConfig.js` | `Components/DailyReport/` | `modules/daily-report/config/reportConfig.js` |

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
