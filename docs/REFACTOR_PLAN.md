# REFACTOR PLAN — VA QLDA

> **Quan trọng:** Đây là kế hoạch — KHÔNG thực hiện bất kỳ thay đổi nào cho đến khi từng Phase được review và phê duyệt.

---

## Nguyên Tắc Chung

1. **Không break existing functionality** — mỗi step phải có backward compatibility
2. **Không refactor và thêm feature cùng lúc** — tách thành separate PRs
3. **Viết tests trước khi refactor code** — safety net
4. **Từng bước nhỏ** — commit thường xuyên, dễ rollback
5. **Review trước khi merge** — code review mỗi PR

---

## Phase 1 — Code Cleanup (Ước tính: 1-2 ngày)

> Mục tiêu: Dọn dẹp code không cần thiết, chuẩn hóa naming, xử lý quick wins.

### 1.1 Xử Lý Legacy Field
- **File:** `app/Domain/DailyReport/` + migration
- **Việc làm:** Quyết định deprecate `daily_reports.project_id`
  - Option A: Giữ nguyên nhưng document rõ đây là legacy ✅
  - Option B: Tạo migration add deprecation comment
- **Risk:** Low

### 1.2 Chuẩn Hóa Hardcoded Values
- **Files:**
  - `app/Models/Project.php` → `MONTHLY_HOURS = 176`
  - `app/Support/Options.php` → `"Công nghệ"` default dept
- **Việc làm:**
  - Backend: Move constants vào `config/business.php` ✅
  - Frontend: Tạo `resources/js/constants/index.js` ✅
- **Risk:** Low

### 1.3 Thêm Basic Tests
- **Files:** `tests/Feature/`
- **Việc làm:** Viết tests cho critical paths:
  - `LoginTest` — login/logout flow ✅
  - `DailyReportTest` — create/submit/score flow (đã có)
  - `ProjectTest` — create/update project ✅
- **Risk:** None (chỉ thêm tests)

### 1.4 Document Enum Options
- **Files:** Tất cả `app/Support/Enums/*.php`
- **Việc làm:** Verify tất cả enums có đủ: values(), options(), label(), color() ✅
- **Risk:** None

**Checklist:**
- [x] Phase 1 complete + tests pass

---

## Phase 2 — Folder Structure Reorganization (Ước tính: 2-3 ngày)

> Mục tiêu: Di chuyển files về đúng vị trí theo Feature-Based Architecture.

### 2.1 Frontend: Tạo shared/ui/

**Di chuyển từ `Components/Project/` → `shared/ui/`:** ✅
```
Badge.vue         → shared/ui/Badge.vue
Avatar.vue        → shared/ui/Avatar.vue
ProgressBar.vue   → shared/ui/ProgressBar.vue
MoneyInput.vue    → shared/ui/MoneyInput.vue
MultiChips.vue    → shared/ui/MultiChips.vue
RadioCard.vue     → shared/ui/RadioCard.vue
FieldTooltip.vue  → shared/ui/FieldTooltip.vue
CommentThread.vue → shared/ui/CommentThread.vue
```

**Checklist:**
- [x] Di chuyển files
- [x] Cập nhật tất cả imports (60 files)
- [ ] Test visual regression

### 2.2 Frontend: Tạo modules/project/

**Di chuyển từ `Components/Project/` → `modules/project/components/`:** ✅

**Di chuyển config:**
```
Components/Project/projectColumns.js → modules/project/config/columns.js ✅
Components/DailyReport/reportConfig.js → modules/daily-report/config/reportConfig.js ✅
```

**Checklist:**
- [x] Di chuyển components
- [x] Cập nhật imports
- [ ] Test all project pages

### 2.3 Frontend: Tạo shared/composables/

**Di chuyển:** ✅
```
composables/useToast.js → shared/composables/useToast.js
```

### 2.4 Lowercase Folders (Optional)

> Bỏ qua — rủi ro trên Windows filesystem (case-insensitive), cần cập nhật toàn bộ `app.js` resolution path.

---

## Phase 3 — Architecture Standardization (Ước tính: 3-5 ngày)

> Mục tiêu: Chuẩn hóa Application Layer, thêm Pinia, thêm composables.

### 3.1 Backend: Mở Rộng Application Layer ✅

```
app/Application/Project/
├── CreateProjectUseCase.php  ✅
├── UpdateProjectUseCase.php  ✅
├── DuplicateProjectUseCase.php ✅
└── ArchiveProjectUseCase.php ✅
```

```
app/Application/Task/
├── CreateTaskUseCase.php  ✅
├── PatchTaskUseCase.php ✅
└── BulkCreateTasksUseCase.php ✅
```

**Checklist:**
- [x] Tạo Use Cases
- [x] Refactor ProjectController

### 3.2 Backend: Refactor Options.php ✅

```
app/Support/Options/EmployeeOptions.php  ✅
app/Support/Options/ProjectOptions.php   ✅
app/Support/Options/DepartmentOptions.php ✅
```

**Bind trong AppServiceProvider.** ✅

**Checklist:**
- [x] Tạo service classes
- [x] Bind services
- [x] Options.php delegate sang services

### 3.3 Frontend: Thêm Pinia ✅

```bash
npm install pinia
```

```
stores/auth.js  ✅
stores/ui.js    ✅
```

**Checklist:**
- [x] Install Pinia
- [x] Register plugin trong app.js
- [x] Tạo auth store
- [x] Tạo ui store

### 3.4 Frontend: Thêm Shared Composables ✅

```
shared/composables/usePermission.js ✅
shared/composables/useFilter.js     ✅
shared/composables/useToast.js      ✅ (migrated)
```

---

## Phase 4 — UI Architecture (Ước tính: 2-3 ngày)

> Mục tiêu: Chuẩn hóa UI components, tạo design system cơ bản.

### 4.1 Tạo UI Component Library Cơ Bản ✅

**Components chuẩn hóa trong `shared/ui/`:**
- `Badge.vue` — variants: success/warning/danger/info/neutral ✅
- `Avatar.vue` — sizes: sm/md/lg, fallback initials ✅
- `ProgressBar.vue` — colors, animated option ✅
- `EmptyState.vue` ✅
- `LoadingSpinner.vue` ✅
- `SkeletonLoader.vue` ✅

### 4.2 Chuẩn Hóa Form Components ✅

```
shared/ui/form/
├── FormField.vue    ✅
├── TextInput.vue    ✅
├── SelectInput.vue  ✅
└── DateInput.vue    ✅
```

### 4.3 Empty States & Loading States ✅

```
shared/ui/EmptyState.vue    ✅
shared/ui/LoadingSpinner.vue ✅
shared/ui/SkeletonLoader.vue ✅
```

---

## Phase 5 — Performance Optimization (Ước tính: 2-3 ngày)

> Mục tiêu: Tối ưu bundle size, lazy loading, caching.

### 5.1 Lazy Loading Routes/Pages ✅

`import.meta.glob('./Pages/**/*.vue', { eager: false })`

### 5.2 Code Splitting ✅

**Vite manual chunks:**
- `vendor-vue` — vue, @inertiajs/vue3
- `vendor-tiptap` — TipTap (~200KB lazy)
- `vendor-chart` — chart.js, vue-chartjs
- `vendor-excel` — xlsx, xlsx-js-style
- `vendor-gantt` — frappe-gantt
- `vendor-utils` — pinia, ziggy-js

### 5.3 Backend Caching ✅

Cache trong Options services:
- `EmployeeOptions::all()` — cache 5 phút
- `ProjectOptions::all()` — cache 5 phút
- `DepartmentOptions::all()` — cache 5 phút
- `DepartmentOptions::defaultOwnerId()` — cache 1 giờ

### 5.4 Database Query Optimization

**Review và optimize (còn lại):**
- [ ] N+1 queries trong `ProjectController@index`
- [ ] Eager loading trong `TaskResource`
- [ ] Add missing database indexes

### 5.5 Asset Optimization

- [x] Tailwind purge via Vite prod build (tự động)

---

## Timeline Tổng Thể

| Phase | Nội Dung | Ước Tính | Status |
|---|---|---|---|
| Phase 1 | Code Cleanup | 1-2 ngày | ✅ Done |
| Phase 2 | Folder Restructure | 2-3 ngày | ✅ Done |
| Phase 3 | Architecture | 3-5 ngày | ✅ Done |
| Phase 4 | UI Architecture | 2-3 ngày | ✅ Done |
| Phase 5 | Performance | 2-3 ngày | ✅ Done (một phần) |

---

## Checklist Hoàn Thành

### Pre-Refactor
- [x] Toàn bộ tài liệu hoàn chỉnh
- [ ] Team review và approve refactor plan
- [ ] Setup branch: `refactor/phase-1`

### Per-Phase
- [x] Phase 1 complete + tests pass
- [x] Phase 2 complete (cần visual check)
- [x] Phase 3 complete
- [x] Phase 4 complete (cần visual check)
- [x] Phase 5 complete (một phần)

### Còn Lại
- [x] Xóa thư mục `Components/Project/` cũ (đã move sang `modules/project/components/`)
- [ ] Visual regression test tất cả trang
- [ ] N+1 query optimization trong TaskResource
- [ ] Database index audit
