---
name: add-vue-page
description: >-
  Adds or extends Vue 3 Inertia pages and components in VA-QLDA. Use when
  creating Pages, feature components, composables, modals, or wiring AppLayout
  and permissions.
---

# Add Vue Page / UI — VA-QLDA

> Frontend structure post-refactor — see `docs/FRONTEND_STRUCTURE.md`.

## 1. Page scaffold

- File: `resources/js/Pages/{Domain}/{Action}.vue`
- Import: `AppLayout`, `PageHeader`, `Head`
- Props via `defineProps` from controller `Inertia::render(..., [...])`

## 2. Component placement

| Type | Path |
|------|------|
| App UI primitives | `Components/Ui/` — Modal, Drawer, PageHeader |
| Shared reusable UI | `shared/ui/` — Badge, Avatar, form/* |
| Project feature | `modules/project/components/` (+ subfolders Sprint/, Dashboard/, …) |
| DailyReport feature | `Components/DailyReport/` (chưa migrate modules/) |
| Cross-feature logic | `shared/composables/` hoặc `composables/use*.js` |

**Không** tạo file trong `Components/Project/` — path đã xóa.

## 3. Logic extraction

- Excel, filters, lists → `composables/use{Feature}.js`
- Toast: `@/shared/composables/useToast`
- Confirm: `useDialog` + `AppDialog`
- Permissions: `usePage().props.auth`, entity `can` props; optional `shared/composables/usePermission`

## 4. Forms

```javascript
import { useForm } from '@inertiajs/vue3';
const form = useForm({ ... });
form.post(route('projects.store'), { preserveScroll: true });
```

## 5. Content header (bắt buộc)

Đọc skill **`content-header`** và rule `.cursor/rules/content-header.mdc`.

- `PageHeader` trong `<template #header>` của `AppLayout` — không trong body.
- `icon` trùng mục `App\Support\Navigation.php` khi có menu.
- `back-href` chỉ trang con (Create/Edit/Show); không back về hub module có trong sidebar.
- Mẫu: `Pages/AiAccount/Index.vue`, `Pages/Coaching/Dashboard.vue`.

## 6. Datagrid toolbar (bắt buộc nếu có bảng)

Đọc skill **`datagrid-toolbar`** và rule `.cursor/rules/datagrid-toolbar.mdc`.

- Label **Tìm kiếm** + input `flex-1` dài (`lg:min-w-[28rem]`).
- Nút **Lọc** / **Cột** / **Xuất**: icon + label ngắn (không chỉ icon).
- Giá trị lọc (`<select>`) trên **dòng 2**; gửi API khi có endpoint.
- Xuất: composable CSV + Excel (`xlsx-js-style`). Mẫu: `Pages/AiAccount/CostReport.vue`.

## 7. Data modal (import/export)

Một nút **Dữ liệu** → `*DataModal.vue` tabs `import|export|reconcile`.  
Copy: `modules/project/components/Dashboard/RiskImportModal.vue`, `Sprint/SprintDataModal.vue`.

## 8. Navigation

Update `App\Support\Navigation.php` khi thêm menu item.

## 9. Quality

- `npm run lint` — zero warnings
- Playwright E2E nếu đổi UI critical path
- Cập nhật `_dev/` nếu thêm script/hook mới

## Reference

`docs/FRONTEND_STRUCTURE.md`, `_dev/conventions.md`
