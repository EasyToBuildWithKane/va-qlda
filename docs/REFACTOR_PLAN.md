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
  - Option A: Giữ nguyên nhưng document rõ đây là legacy
  - Option B: Tạo migration add deprecation comment
- **Risk:** Low

### 1.2 Chuẩn Hóa Hardcoded Values
- **Files:**
  - `app/Models/Project.php` → `MONTHLY_HOURS = 176`
  - `app/Support/Options.php` → `"Công nghệ"` default dept
- **Việc làm:**
  - Backend: Move constants vào `config/business.php`
  - Frontend: Tạo `resources/js/constants/index.js`
- **Risk:** Low

### 1.3 Thêm Basic Tests
- **Files:** `tests/Feature/`
- **Việc làm:** Viết tests cho critical paths:
  - `LoginTest` — login/logout flow
  - `DailyReportTest` — create/submit/score flow
  - `ProjectTest` — create/update project
- **Risk:** None (chỉ thêm tests)

### 1.4 Document Enum Options
- **Files:** Tất cả `app/Support/Enums/*.php`
- **Việc làm:** Verify tất cả enums có đủ: values(), options(), label(), color()
- **Risk:** None

---

## Phase 2 — Folder Structure Reorganization (Ước tính: 2-3 ngày)

> Mục tiêu: Di chuyển files về đúng vị trí theo Feature-Based Architecture.

### 2.1 Frontend: Tạo shared/ui/

**Di chuyển từ `Components/Project/` → `shared/ui/`:**
```
Badge.vue         → shared/ui/Badge.vue
Avatar.vue        → shared/ui/Avatar.vue
ProgressBar.vue   → shared/ui/ProgressBar.vue
MoneyInput.vue    → shared/ui/MoneyInput.vue
MultiChips.vue    → shared/ui/MultiChips.vue
RadioCard.vue     → shared/ui/RadioCard.vue
FieldTooltip.vue  → shared/ui/FieldTooltip.vue
PersonSelect.vue  → shared/ui/PersonSelect.vue
CommentThread.vue → shared/ui/CommentThread.vue
```

**Cập nhật imports** trong tất cả files tham chiếu.

**Checklist:**
- [ ] Di chuyển files
- [ ] Cập nhật tất cả imports
- [ ] Test visual regression

### 2.2 Frontend: Tạo modules/project/

**Di chuyển từ `Components/Project/` → `modules/project/components/`:**
- Tất cả project-specific components
- Sub-folders Sprint/, TaskDetail/, Dashboard/, Timeline/, Documents/

**Di chuyển config:**
```
Components/Project/projectColumns.js → modules/project/config/columns.js
Components/DailyReport/reportConfig.js → modules/daily-report/config/reportConfig.js
```

**Checklist:**
- [ ] Di chuyển components
- [ ] Cập nhật imports
- [ ] Test all project pages

### 2.3 Frontend: Tạo shared/composables/

**Di chuyển:**
```
composables/useToast.js → shared/composables/useToast.js
```

**Cập nhật imports** trong tất cả files tham chiếu.

### 2.4 Lowercase Folders (Optional)

**Đổi tên:**
```
Pages/  → pages/
Layouts/ → layouts/
Components/ → (phân tán vào modules/ và shared/)
```

**Lưu ý:** Cần cập nhật `app.js` resolution path + tất cả imports.

---

## Phase 3 — Architecture Standardization (Ước tính: 3-5 ngày)

> Mục tiêu: Chuẩn hóa Application Layer, thêm Pinia, thêm composables.

### 3.1 Backend: Mở Rộng Application Layer

**Tạo Use Cases cho Project module:**
```
app/Application/Project/
├── CreateProjectUseCase.php
├── UpdateProjectUseCase.php
├── DuplicateProjectUseCase.php
└── ArchiveProjectUseCase.php
```

**Tạo Use Cases cho Task module:**
```
app/Application/Task/
├── CreateTaskUseCase.php
├── UpdateTaskStatusUseCase.php
└── BulkCreateTasksUseCase.php
```

**Refactor ProjectController** để inject + call Use Cases thay vì direct Model queries.

**Checklist:**
- [ ] Viết tests trước
- [ ] Tạo Use Cases
- [ ] Refactor Controllers
- [ ] Verify tests pass

### 3.2 Backend: Refactor Options.php

**Tách thành injectable services:**
```
app/Support/Options/EmployeeOptions.php
app/Support/Options/ProjectOptions.php
app/Support/Options/DepartmentOptions.php
```

**Bind trong AppServiceProvider.**

**Checklist:**
- [ ] Tạo service classes
- [ ] Bind services
- [ ] Cập nhật tất cả callers
- [ ] Deprecate static Options class

### 3.3 Frontend: Thêm Pinia

**Install Pinia:**
```bash
npm install pinia
```

**Tạo stores:**
```
stores/auth.js     ← user, role, employee data
stores/ui.js       ← toast state, dialog state
```

**Migrate** useToast từ composable → store.

**Checklist:**
- [ ] Install Pinia
- [ ] Register plugin trong app.js
- [ ] Tạo auth store
- [ ] Tạo ui store
- [ ] Migrate toast state

### 3.4 Frontend: Thêm Shared Composables

**Tạo composables:**
```javascript
// shared/composables/usePermission.js
export function usePermission() {
  const auth = useAuthStore()
  const can = (action) => { /* role-based check */ }
  const isRole = (...roles) => roles.includes(auth.role)
  return { can, isRole }
}

// shared/composables/useForm.js
// Wrapper around @inertiajs/vue3 useForm với validation helpers

// shared/composables/useFilter.js
// URL-bound filter state cho list pages

// shared/composables/useDialog.js
// Imperative dialog API
```

**Checklist:**
- [ ] Tạo usePermission
- [ ] Tạo useForm
- [ ] Tạo useFilter
- [ ] Tạo useDialog
- [ ] Replace inline logic trong pages

---

## Phase 4 — UI Architecture (Ước tính: 2-3 ngày)

> Mục tiêu: Chuẩn hóa UI components, tạo design system cơ bản.

### 4.1 Tạo UI Component Library Cơ Bản

**Standardize shared/ui/ components:**
- Định nghĩa props interface rõ ràng
- Thêm default slots
- Document usage với JSDoc

**Components cần chuẩn hóa:**
- `Badge.vue` — variants: success/warning/danger/info/neutral
- `Avatar.vue` — sizes: sm/md/lg, fallback initials
- `Modal.vue` — sizes: sm/md/lg/xl/full
- `Drawer.vue` — positions: left/right
- `ProgressBar.vue` — colors, animated option

### 4.2 Chuẩn Hóa Form Components

**Tạo `shared/ui/form/`:**
```
FormField.vue     ← label + input + error wrapper
TextInput.vue     ← base text input
SelectInput.vue   ← base select
DateInput.vue     ← date picker wrapper
RichEditor.vue    ← TipTap wrapper (generic)
```

### 4.3 Chuẩn Hóa Empty States & Loading States

**Tạo:**
```
shared/ui/EmptyState.vue    ← "Không có dữ liệu" placeholder
shared/ui/LoadingSpinner.vue
shared/ui/SkeletonLoader.vue
```

---

## Phase 5 — Performance Optimization (Ước tính: 2-3 ngày)

> Mục tiêu: Tối ưu bundle size, lazy loading, caching.

### 5.1 Lazy Loading Routes/Pages

**Cập nhật app.js:**
```javascript
// Thay vì import tất cả pages eagerly
// Dùng dynamic import
resolve: name => {
  const pages = import.meta.glob('./pages/**/*.vue')
  return pages[`./pages/${name}.vue`]()
}
```

### 5.2 Code Splitting

**Vite configuration:**
- Split vendor chunks (vue, inertia, tiptap, frappe-gantt)
- Lazy load TipTap chỉ khi cần (nặng ~200KB)
- Lazy load Frappe Gantt chỉ khi mở Gantt view

### 5.3 Backend Caching

**Thêm caching cho:**
- `Options::employees()` — cache 5 phút
- `Options::projects()` — cache 5 phút
- `Navigation::for()` — cache per user session

### 5.4 Database Query Optimization

**Review và optimize:**
- N+1 queries trong `ProjectController@index`
- Eager loading trong `TaskResource`
- Add missing database indexes

### 5.5 Asset Optimization

**Tailwind:**
- Review và loại bỏ unused custom CSS
- Purge unused utility classes (đã được Vite làm trong prod)

---

## Timeline Tổng Thể

| Phase | Nội Dung | Ước Tính | Prerequisite |
|---|---|---|---|
| Phase 1 | Code Cleanup | 1-2 ngày | None |
| Phase 2 | Folder Restructure | 2-3 ngày | Phase 1 done |
| Phase 3 | Architecture | 3-5 ngày | Phase 2 done + Tests |
| Phase 4 | UI Architecture | 2-3 ngày | Phase 3 done |
| Phase 5 | Performance | 2-3 ngày | Phase 4 done |

**Tổng:** ~10-16 ngày làm việc (không overlap với feature development)

---

## Checklist Hoàn Thành

### Pre-Refactor
- [ ] Toàn bộ tài liệu hoàn chỉnh (file này + 8 docs khác)
- [ ] Team review và approve refactor plan
- [ ] Setup branch: `refactor/phase-1`

### Per-Phase
- [ ] Phase 1 complete + tests pass
- [ ] Phase 2 complete + visual check
- [ ] Phase 3 complete + tests pass
- [ ] Phase 4 complete + visual check
- [ ] Phase 5 complete + bundle size check
