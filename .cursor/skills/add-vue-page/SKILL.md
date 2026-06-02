---
name: add-vue-page
description: >-
  Adds or extends Vue 3 Inertia pages and components in VA-QLDA. Use when
  creating Pages, feature components, composables, modals, or wiring AppLayout
  and permissions.
---

# Add Vue Page / UI — VA-QLDA

## 1. Page scaffold

- File: `resources/js/Pages/{Domain}/{Action}.vue`
- Import: `AppLayout`, `PageHeader`, `Head`
- Props via `defineProps` from controller `Inertia::render(..., [...])`

## 2. Component placement

| Type | Path |
|------|------|
| Reusable UI | `Components/Ui/` |
| Domain feature | `Components/{Project\|DailyReport\|...}/` |
| Cross-feature primitive | Prefer `Ui/` over copying from `Project/` |

Do **not** move files to `modules/` unless refactor phase approved (`docs/REFACTOR_PLAN.md`).

## 3. Logic extraction

- Excel, filters, API lists → `composables/use{Feature}.js`
- Toast: `useToast`; confirm: `useDialog` + `AppDialog`
- Permissions: `usePage().props.auth`, `can` on entity props

## 4. Forms

```javascript
import { useForm } from '@inertiajs/vue3';
const form = useForm({ ... });
form.post(route('projects.store'), { preserveScroll: true });
```

Show `form.errors.field`; disable submit when `form.processing`.

## 5. Data modal (import/export)

One toolbar button **Dữ liệu** → `*DataModal.vue` with tabs `import|export|reconcile`. Copy `RiskImportModal` / `SprintDataModal` pattern.

## 6. Navigation

Live routes: update `App\Support\Navigation.php` (`status`, `href`, `roles`).

## Reference

`docs/FRONTEND_STRUCTURE.md` — component catalog and patterns.
