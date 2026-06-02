# FOLDER STRUCTURE — VA QLDA

---

## 1. Cấu Trúc Thư Mục Hiện Tại

```
va-qlda/
├── app/
│   ├── Application/                    ← Use Cases (chỉ DailyReport)
│   │   └── DailyReport/
│   │       ├── CreateDailyReportUseCase.php
│   │       ├── UpdateDailyReportUseCase.php
│   │       ├── SubmitDailyReportUseCase.php
│   │       ├── ScoreReportUseCase.php
│   │       └── RejectReportUseCase.php
│   │
│   ├── Domain/                         ← Domain Layer (chỉ DailyReport)
│   │   └── DailyReport/
│   │       ├── Models/
│   │       │   ├── DailyReport.php
│   │       │   └── DailyReportScore.php
│   │       ├── Services/
│   │       │   └── ScoringService.php
│   │       └── Exceptions/
│   │           └── DailyReportException.php
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/LoginController.php
│   │   │   ├── Blocker/BlockerController.php
│   │   │   ├── Bug/BugController.php
│   │   │   ├── Comment/CommentController.php
│   │   │   ├── DailyReport/
│   │   │   │   ├── DailyReportController.php
│   │   │   │   └── DailyReportReviewController.php
│   │   │   ├── Department/DepartmentController.php
│   │   │   ├── Feedback/FeedbackController.php
│   │   │   ├── Project/
│   │   │   │   ├── EpicController.php
│   │   │   │   ├── ProjectAttachmentController.php
│   │   │   │   ├── ProjectController.php
│   │   │   │   ├── ProjectMemberController.php
│   │   │   │   ├── SprintController.php
│   │   │   │   ├── TaskAttachmentController.php
│   │   │   │   ├── TaskController.php
│   │   │   │   ├── TaskWatcherController.php
│   │   │   │   └── WorklogController.php
│   │   │   └── DashboardController.php
│   │   ├── Middleware/                  ← Standard Laravel middlewares
│   │   │   ├── HandleInertiaRequests.php
│   │   │   ├── Authenticate.php
│   │   │   └── ...
│   │   ├── Requests/
│   │   │   ├── Auth/LoginRequest.php
│   │   │   ├── Blocker/
│   │   │   ├── Bug/
│   │   │   ├── Comment/
│   │   │   ├── DailyReport/
│   │   │   ├── Department/
│   │   │   ├── Feedback/
│   │   │   └── Project/
│   │   └── Resources/
│   │       ├── Concerns/PresentsEntities.php
│   │       ├── BlockerResource.php
│   │       ├── BugResource.php
│   │       ├── CommentResource.php
│   │       ├── DailyReportResource.php
│   │       ├── DailyReportScoreResource.php
│   │       ├── DepartmentResource.php
│   │       ├── EpicResource.php
│   │       ├── FeedbackResource.php
│   │       ├── MemberResource.php
│   │       ├── ProjectListResource.php
│   │       ├── ProjectResource.php
│   │       ├── SprintResource.php
│   │       ├── TaskResource.php
│   │       ├── WorklogResource.php
│   │       └── ...
│   │
│   ├── Models/                         ← Eloquent models (App domain)
│   │   ├── Blocker.php
│   │   ├── BlockerActivity.php
│   │   ├── BlockerAttachment.php
│   │   ├── Bug.php
│   │   ├── Comment.php
│   │   ├── Department.php
│   │   ├── Employee.php
│   │   ├── Epic.php
│   │   ├── Feedback.php
│   │   ├── Project.php
│   │   ├── ProjectAttachment.php
│   │   ├── ProjectAttachmentActivity.php
│   │   ├── Sprint.php
│   │   ├── SystemAccount.php
│   │   ├── Task.php
│   │   ├── TaskActivity.php
│   │   ├── TaskAttachment.php
│   │   └── Worklog.php
│   │
│   ├── Policies/
│   │   ├── BlockerPolicy.php
│   │   ├── BugPolicy.php
│   │   ├── DailyReportPolicy.php
│   │   ├── DepartmentPolicy.php
│   │   ├── FeedbackPolicy.php
│   │   └── ProjectPolicy.php
│   │
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   ├── AuthServiceProvider.php
│   │   ├── BroadcastServiceProvider.php
│   │   ├── EventServiceProvider.php
│   │   └── RouteServiceProvider.php
│   │
│   └── Support/
│       ├── Concerns/HasUuid.php
│       ├── Enums/
│       │   ├── BlockerSeverity.php
│       │   ├── BlockerStatus.php
│       │   ├── BugSeverity.php
│       │   ├── BugStatus.php
│       │   ├── FeedbackCategory.php
│       │   ├── FeedbackStatus.php
│       │   ├── Grade.php
│       │   ├── ProjectAttachmentCategory.php
│       │   ├── ProjectScope.php
│       │   ├── ProjectStatus.php
│       │   ├── ProjectType.php
│       │   ├── RateType.php
│       │   ├── Region.php
│       │   ├── ReportStatus.php
│       │   ├── ScoreTrend.php
│       │   ├── SprintStatus.php
│       │   ├── SystemRole.php
│       │   ├── TaskPhase.php
│       │   ├── TaskPriority.php
│       │   └── TaskStatus.php
│       ├── BlockerActivityLogger.php
│       ├── Navigation.php
│       ├── Options.php
│       ├── ProjectAttachmentActivityLogger.php
│       ├── ProjectCatalog.php
│       ├── TaskActivityLogger.php
│       └── TaskTimeliness.php
│
├── bootstrap/
│   └── app.php
│
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── database.php
│   └── ...
│
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
│
├── docs/                               ← [MỚI] Tài liệu kỹ thuật
│
├── public/
│
├── resources/
│   ├── css/
│   │   └── app.css
│   ├── js/
│   │   ├── app.js
│   │   ├── bootstrap.js
│   │   ├── Components/               ← Mixed: UI primitives + feature components
│   │   │   ├── AppIcon.vue
│   │   │   ├── DailyReport/          ← OK: feature-grouped
│   │   │   ├── Project/              ← OK: feature-grouped (nhưng quá rộng)
│   │   │   └── UI/                   ← OK: UI primitives
│   │   ├── composables/
│   │   │   └── useToast.js           ← Thiếu nhiều composables cần thiết
│   │   ├── Layouts/
│   │   │   └── AppLayout.vue
│   │   └── Pages/                    ← OK: Inertia pages
│   │       ├── Auth/
│   │       ├── Blocker/
│   │       ├── Bug/
│   │       ├── DailyReport/
│   │       ├── Dashboard/
│   │       ├── Department/
│   │       ├── Feedback/
│   │       └── Project/
│   └── views/
│       └── app.blade.php
│
├── routes/
│   ├── api.php                        ← Rỗng (chưa có REST API)
│   ├── channels.php
│   ├── console.php
│   └── web.php
│
├── storage/
├── tests/
├── .env.example
├── artisan
├── composer.json
├── package.json
├── tailwind.config.js
└── vite.config.js
```

---

## 2. Vấn Đề Của Cấu Trúc Hiện Tại

| Vấn Đề | Vị Trí | Lý Do |
|---|---|---|
| Clean Architecture chỉ ở DailyReport | `app/Application/`, `app/Domain/` | Không nhất quán với các module còn lại |
| `Components/Project/` quá lớn | `resources/js/Components/Project/` | Chứa 40+ files: UI primitives, feature components, config files |
| UI primitives nằm trong feature folder | `Components/Project/Badge.vue`, `Avatar.vue` | Badge, Avatar là shared UI, không phải Project-specific |
| Config files nằm trong Components | `Components/Project/projectColumns.js` | Không phải component — là data/config |
| Chỉ có 1 composable | `composables/useToast.js` | Thiếu: useForm, usePermission, useFilter, useApi |
| Không có stores/ | `resources/js/` | Cần Pinia stores cho global state |
| Không có services/ | `resources/js/` | API calls nằm inline trong components |
| Không có types/ | `resources/js/` | Không có TypeScript types/interfaces |
| Không có constants/ | `resources/js/` | Hardcoded values rải rác |

---

## 3. Cấu Trúc Thư Mục Đề Xuất

### 3.1 Backend (app/)

```
app/
├── Application/                  ← Use Cases (mở rộng cho tất cả features)
│   ├── DailyReport/              ← Hiện tại
│   ├── Project/                  ← Thêm mới
│   │   ├── CreateProjectUseCase.php
│   │   ├── UpdateProjectUseCase.php
│   │   └── DuplicateProjectUseCase.php
│   ├── Task/                     ← Thêm mới
│   └── Sprint/                   ← Thêm mới
│
├── Domain/                       ← Domain Layer (mở rộng)
│   ├── DailyReport/              ← Hiện tại
│   ├── Project/                  ← Thêm mới
│   │   ├── Models/
│   │   ├── Services/
│   │   │   ├── CostCalculationService.php
│   │   │   └── ProgressCalculationService.php
│   │   └── Exceptions/
│   └── IssueTracking/            ← Thêm mới (Blocker, Bug, Feedback)
│
├── Http/                         ← Giữ nguyên cấu trúc
│
├── Models/                       ← Giữ nguyên, thêm interfaces
│
├── Policies/                     ← Giữ nguyên
│
├── Providers/                    ← Giữ nguyên
│
└── Support/
    ├── Concerns/
    │   ├── HasUuid.php
    │   └── HasActivityLog.php    ← Thêm: chuẩn hóa activity logging
    ├── Enums/                    ← Giữ nguyên
    ├── Navigation.php
    ├── Options.php               ← Refactor thành service providers
    └── Helpers/                  ← Thêm: functional helpers
        ├── MoneyHelper.php
        └── DateHelper.php
```

### 3.2 Frontend (resources/js/)

```
resources/js/
├── app.js                        ← Entry point
├── bootstrap.js                  ← Axios setup
│
├── layouts/                      ← App layouts (lowercase)
│   └── AppLayout.vue
│
├── pages/                        ← Inertia pages (thin, use modules)
│   ├── auth/
│   │   └── Login.vue
│   ├── dashboard/
│   │   └── Index.vue
│   ├── daily-report/
│   │   ├── Today.vue
│   │   ├── History.vue
│   │   ├── Show.vue
│   │   └── Review.vue
│   ├── project/
│   │   ├── Index.vue
│   │   ├── Create.vue
│   │   ├── Edit.vue
│   │   └── Show.vue
│   ├── blocker/
│   │   └── Index.vue
│   ├── bug/
│   │   ├── Index.vue
│   │   └── Show.vue
│   ├── feedback/
│   │   ├── Index.vue
│   │   └── Show.vue
│   └── department/
│       └── Index.vue
│
├── modules/                      ← Feature modules (MỚI)
│   ├── project/
│   │   ├── components/           ← Project-specific components
│   │   │   ├── ProjectCard.vue
│   │   │   ├── ProjectDataGrid.vue
│   │   │   ├── ProjectForm.vue
│   │   │   ├── sprint/
│   │   │   ├── task/
│   │   │   ├── task-detail/
│   │   │   ├── dashboard/
│   │   │   ├── timeline/
│   │   │   └── documents/
│   │   ├── composables/          ← Project composables
│   │   │   ├── useProject.js
│   │   │   ├── useTask.js
│   │   │   └── useSprint.js
│   │   ├── services/             ← Project API calls
│   │   │   └── projectService.js
│   │   └── config/               ← Project config (was projectColumns.js)
│   │       └── columns.js
│   │
│   ├── daily-report/
│   │   ├── components/
│   │   ├── composables/
│   │   └── config/
│   │       └── reportConfig.js
│   │
│   ├── issue-tracking/           ← Blocker, Bug, Feedback
│   │   ├── components/
│   │   └── composables/
│   │
│   └── people/                   ← Employee, Department
│       ├── components/
│       └── composables/
│
├── shared/                       ← Shared across modules (MỚI)
│   ├── ui/                       ← UI primitives
│   │   ├── Modal.vue
│   │   ├── Drawer.vue
│   │   ├── AppDialog.vue
│   │   ├── ToastContainer.vue
│   │   ├── PageHeader.vue
│   │   ├── Badge.vue             ← (moved from Project/)
│   │   ├── Avatar.vue            ← (moved from Project/)
│   │   ├── ProgressBar.vue       ← (moved from Project/)
│   │   └── AppIcon.vue
│   ├── composables/              ← Shared composables (MỚI)
│   │   ├── useToast.js           ← (moved from composables/)
│   │   ├── useDialog.js
│   │   ├── useForm.js
│   │   ├── useFilter.js
│   │   └── usePermission.js
│   └── utils/                    ← Utilities (MỚI)
│       ├── format.js
│       └── date.js
│
├── stores/                       ← Pinia stores (MỚI)
│   ├── auth.js
│   └── ui.js                     ← toast, dialog state
│
├── services/                     ← API services (MỚI)
│   └── http.js                   ← Axios wrapper
│
├── types/                        ← TypeScript/JSDoc types (MỚI)
│   ├── project.d.ts
│   ├── task.d.ts
│   └── user.d.ts
│
└── constants/                    ← App constants (MỚI)
    └── index.js
```

---

## 4. Lý Do Thay Đổi

| Thay Đổi | Lý Do |
|---|---|
| Tạo `modules/` | Gom components + composables + services theo feature, dễ tìm và bảo trì |
| Tạo `shared/ui/` | Tách UI primitives ra khỏi feature folders, dễ reuse |
| Tạo `shared/composables/` | Centralize shared logic, tránh duplicate |
| Tạo `stores/` | Global state management với Pinia |
| Tạo `services/` | API layer riêng, dễ test và thay thế |
| Tạo `types/` | Type safety, code documentation |
| Lowercase folder names | Convention chuẩn cho Vue/Nuxt projects |
| Mở rộng Application/ và Domain/ cho tất cả features | Nhất quán kiến trúc |
