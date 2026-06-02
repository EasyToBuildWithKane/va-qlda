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

## 5. Data modal (import/export)

Một nút **Dữ liệu** → `*DataModal.vue` tabs `import|export|reconcile`.  
Copy: `modules/project/components/Dashboard/RiskImportModal.vue`, `Sprint/SprintDataModal.vue`.

## 6. Navigation

Update `App\Support\Navigation.php` khi thêm menu item.

## 7. Quality

- `npm run lint` — zero warnings
- Playwright E2E nếu đổi UI critical path
- Cập nhật `_dev/` nếu thêm script/hook mới

## Reference

`docs/FRONTEND_STRUCTURE.md`, `_dev/conventions.md`
