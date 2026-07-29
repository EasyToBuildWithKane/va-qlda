# VA-Workspace — Claude Code Instructions

**VAschools Quản lý Dự Án** — Laravel 10 + Inertia + Vue 3 + Tailwind.

> Tài liệu chi tiết: `docs/` (kiến trúc) · `_dev/` (CLI, CI, workflows, tiếng Việt: `_dev/vi/`).

> Refactor Phase 1–5 ✅: 13 feature module dưới `modules/` (project, daily-report, knowledge-base, …), `shared/ui/`, Pinia, Project/Task Use Cases. Routes split theo domain: `routes/web.php` (loader) → `routes/web/{domain}.php`.

---

## Stack & Transport

- **Không REST API chính** — `routes/web/*.php` (split theo domain) + Inertia; `routes/api.php` rỗng. JSON chỉ cho endpoint phụ (vd. notifications).
- **Auth:** guard `system`, model `SystemAccount`, roles: `super_admin` | `admin` | `lead` | `member` | `viewer`.
- **DB:** MySQL, prefix bảng `va_prd_` — index name ngắn nếu composite dài.

## Kiến trúc

| Module | Pattern |
|--------|---------|
| DailyReport | Clean: `Application/`, `Domain/` |
| Project, Task | Application Use Cases + MVC read paths |
| Blocker, Feedback, … | MVC: Controller → Model / Support |
| System Config | MVC + Settings overlay: `SettingsSchema`/`SettingsRepository` → `config()` runtime (**super-admin-only**). Xem `docs/SYSTEM_CONFIG.md` · RBAC: `docs/PERMISSIONS.md` |

Không refactor sang Use Case khi user chỉ sửa bug nhỏ. Module mới: ưu tiên FormRequest + Policy + Resource giống module cùng loại.

## Phân quyền (RBAC — xem `docs/PERMISSIONS.md`)

- **Ma trận thật sự điều khiển quyền:** Policy gọi `$account->allows('module.action')` (đọc `va_permissions.role_grants` đã overlay) **OR** nhánh ownership/entity (giữ nguyên). Catalog: `App\Support\Auth\PermissionCatalog`.
- **Vai trò:** `super_admin` = god-mode (`Gate::before`) + **độc quyền** `/settings`, ma trận, gán role, reserved keys. `admin` = full nghiệp vụ nhưng **không** vào `/settings`. `super_admin` luôn `['*']` (khóa).
- Hierarchy grant: `*` → `{module}.*` → exact. `{module}.manage` là ability cụ thể, **không** phải wildcard (đừng để nó ngụ ý delete).
- Check tier ngoài policy: `isSuperAdmin()` / `isAdminTier()` (super+admin) — không hardcode `=== Admin` (sẽ loại super).
- Frontend: `usePage().props.auth.user.permissions` + `usePermission().can('module.action')`; entity `can` từ Resource.
- Nav: `App\Support\Navigation` — super là superset của admin; group `settings` đánh dấu `superOnly`.
- Reserved keys bị strip khỏi mọi role ≠ super ở cả controller lẫn overlay. Không sửa nơi check — chỉnh ma trận ở `/settings`.

## Copy & UX

- UI / flash / validation messages: **tiếng Việt**.
- Brand: `#9A0036`, tokens Tailwind `brand`, `card`, `btn-primary`, `font-display`.
- Layout: `AppLayout` + `#header` → `PageHeader`; route helper `route()` (Ziggy).

## Cấm tuyệt đối

- Commit `.env`, session files, secrets.
- Break DailyReport domain boundaries khi không được yêu cầu refactor.
- Thêm feature + refactor folder lớn trong cùng một thay đổi.

---

## Laravel Backend (`app/`, `routes/`, `database/`)

### Controller (mỏng)

```php
public function store(StoreTaskRequest $request, Project $project): RedirectResponse
{
    $this->authorize('contribute', $project);
    return back()->with('success', 'Đã thêm công việc.');
}
```

- **FormRequest:** `authorize()` + rules; messages tiếng Việt trong `messages()`.
- **Resource:** format Inertia props; quyền `can` khi cần (`ProjectResource`).
- **Bulk import:** `DB::transaction`, max 200 rows, `Import*Request` mirror client.

### Enums

`app/Support/Enums/` — backed string enum + `values()`, `label()`, `options()` khi dùng ở UI.

### Activity & Notifications

- Task/Blocker: `TaskActivityLogger`, `BlockerActivityLogger` sau mutation.
- Inbox: `NotificationService` / `NotificationDispatcher` — không duplicate logic activity.

### DailyReport (Clean Architecture)

- Controller → **Use Case** (`app/Application/DailyReport/`), không query phức tạp trong controller.
- Model domain: `App\Domain\DailyReport\Models\` (không nhầm `App\Models\Project`).

### Routes

- **Split theo domain:** `routes/web.php` chỉ là loader (wire 2 nhóm middleware `guest` / `auth`); route thật ở `routes/web/{domain}.php` (16 partial). Thêm route → sửa partial đúng domain (vd. `projects.php`, `contracts.php`), không nhồi vào `web.php`.
- Nhóm prefix + dot name: `projects.tasks.store`.
- Static segments **trước** `/{id}`: `/daily-reports/review` trước `/{report}`.
- Route model binding tùy chỉnh trong `RouteServiceProvider` nếu tên param ≠ model.

### Policies

Map explicit trong `AuthServiceProvider` nếu model ngoài `App\Models`.

---

## Vue / Inertia Frontend (`resources/js/`)

### Cấu trúc

| Path | Vai trò |
|------|---------|
| `Pages/{Domain}/` | Inertia pages — mỏng, bọc `AppLayout` |
| `Components/Ui/` | Primitives: Modal, Drawer, PageHeader, Toast |
| `modules/project/components/` | Project feature UI |
| `shared/ui/` | Reusable UI: Badge, Avatar, form/* |
| `shared/composables/` | useToast, usePermission, useFilter |
| `composables/use*.js` | Feature logic — **không** import `xlsx` trong Vue |
| `stores/` | Pinia: auth.js, ui.js |
| `Layouts/AppLayout.vue` | Shell, nav, flash → `useToast` |

**Import alias:** `@/modules/...`, `@/shared/...`, `@/Components/...`, `@/composables/...`

> `Components/Project/` đã xóa — dùng `modules/project/components/`.

### Page pattern

```vue
<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
defineProps({ items: Array });
</script>
<template>
  <Head title="..." />
  <AppLayout>
    <template #header><PageHeader title="..." icon="projects" /></template>
  </AppLayout>
</template>
```

### Forms & Data

- Inertia: `useForm`, `router.post/put`, `preserveScroll: true`.
- Axios JSON: notifications, polling — `route('notifications.index')`.
- Permissions: `usePage().props.auth.user.role`, entity `can` từ props.

### UI

- Icons: `<AppIcon name="task" />` — map Lucide trong `AppIcon.vue`.
- Dialog/toast: `useDialog`, `useToast` — không `alert()` native.
- Modal data: `dirty` + `useConfirmClose`; đóng reset state.

### Cấm

- Business rules Excel trong `.vue` (dùng composable).
- Ba modal riêng cho Nhập/Xuất/Đối soát — một `*DataModal` 3 tab.

---

## Database (`database/`, `app/Models/`)

- Prefix: `va_prd_` (config connection).
- FK: `constrained()` + `nullOnDelete` / `cascadeOnDelete` rõ ràng.
- **Index name ngắn** khi composite dài: `'app_notif_recipient_read_idx'`.
- Soft deletes: `employees`, `tasks` — kiểm tra model trước khi `forceDelete`.

### Quan hệ chính

```
Department → Employee → SystemAccount
Project → Sprint, Epic, Task, Worklog, Blocker, Attachment
Task → subtasks (parent_id), dependencies, watchers, assignees
Comment → morph (task, blocker, feedback)
DailyReport → Domain model (UUID), scores
```

### Migration checklist

1. `up`/`down` đối xứng.
2. Enum values khớp `app/Support/Enums/*`.
3. Không breaking rename cột không có migration + cập nhật Resource/FormRequest.

---

## Nhập · Xuất · Đối soát (Production Standard)

Mọi module nhập/xuất/đối soát phải **copy pattern đã chứng minh**.

### Tham chiếu vàng

| Domain | Composable | Modal | Backend import |
|--------|------------|-------|----------------|
| Rủi ro / Vướng mắc | `useRiskImport.js`, `useRiskExport.js` | `RiskImportModal.vue` | `BlockerController@import` + `ImportBlockerRequest` |
| Sprint / Task | `useSprintData.js` | `SprintDataModal.vue` | Chưa bulk — cần thêm API |
| Đối soát | `useSprintReconcile.js` | tab trong `SprintDataModal` | Client-side only |

### Kiến trúc tách lớp (bắt buộc)

| Lớp | Trách nhiệm | Cấm |
|-----|-------------|-----|
| `use*Data.js` / `use*Import.js` | Excel I/O, parse, validate, template, export styled | Import `xlsx` trong `.vue` |
| `*DataModal.vue` | Tab UI, state, gọi composable | Business rules trùng composable |
| `use*Reconcile.js` | Pure checks → `{ issues, summary }` | Gọi API |
| `Import*Request.php` | Validate rows server-side (mirror client) | Tin client 100% |
| Controller | `DB::transaction`, policy, activity log | N+1 POST từng dòng từ browser |

### UI — một điểm vào

- Một nút toolbar **Dữ liệu**, một Modal (`@/Components/Ui/Modal.vue`).
- **3 tab cố định:** `import` | `export` | `reconcile`.
- `canManage` / `canContribute`: ẩn nhập, disable submit; export mở cho viewer.
- Badge đỏ trên nút khi `summary.errors > 0`.

### File mẫu Excel

Thư viện: **`xlsx-js-style`**. Brand palette: `BRAND=9A0036`, `BRAND_SOFT=FDF2F6`.

Sheets bắt buộc: **Huong dan** (9 mục), **Nhap lieu** (marker ẩn, header dòng 5, sample 6–7, nhập từ dòng 8), **Tham chiếu** (nếu có FK).

### Giới hạn

Max **200 rows** client + server.

### Anti-patterns (cấm)

- Ba nút / ba modal riêng cho Nhập, Xuất, Đối soát.
- `sheet_to_json` không kiểm marker/header.
- Export không style cho báo cáo user-facing.
- Client-only validation, không server validate.

---

## Custom Slash Commands

| Command | Dùng khi |
|---------|---------|
| `/docs` | Tra cứu `docs/` + `_dev/` |
| `/add-laravel-feature` | Thêm API/backend feature |
| `/add-vue-page` | Thêm page / component Inertia |
| `/daily-report-domain` | Module báo cáo ngày (Clean Architecture) |
| `/safe-refactor` | Refactor theo REFACTOR_PLAN |
| `/backend-code-review` | Review staged PHP/Laravel theo ISC + OWASP |
| `/frontend-code-review` | Review staged Vue/Inertia theo ISC + OWASP |
