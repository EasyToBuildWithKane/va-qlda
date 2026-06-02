# Add Vue Page / UI — VA-QLDA

Thêm hoặc mở rộng Vue 3 Inertia pages và components.

## 1. Page scaffold

- File: `resources/js/Pages/{Domain}/{Action}.vue`
- Import: `AppLayout`, `PageHeader`, `Head`
- Props via `defineProps` từ controller `Inertia::render(..., [...])`

## 2. Component placement

| Type | Path |
|------|------|
| Reusable UI | `Components/Ui/` |
| Domain feature | `Components/{Project\|DailyReport\|...}/` |
| Cross-feature primitive | Ưu tiên `Ui/` hơn copy từ `Project/` |

Không move files sang `modules/` trừ khi refactor phase được duyệt (`docs/REFACTOR_PLAN.md`).

## 3. Logic extraction

- Excel, filters, API lists → `composables/use{Feature}.js`
- Toast: `useToast`; confirm: `useDialog` + `AppDialog`
- Permissions: `usePage().props.auth`, `can` trên entity props

## 4. Forms

```javascript
import { useForm } from '@inertiajs/vue3';
const form = useForm({ ... });
form.post(route('projects.store'), { preserveScroll: true });
```

Hiển thị `form.errors.field`; disable submit khi `form.processing`.

## 5. Data modal (import/export)

Một nút toolbar **Dữ liệu** → `*DataModal.vue` với tabs `import|export|reconcile`. Copy pattern `RiskImportModal` / `SprintDataModal`.

## 6. Navigation

Cập nhật `App\Support\Navigation.php` (`status`, `href`, `roles`) nếu thêm nav item.

## Reference

`docs/FRONTEND_STRUCTURE.md` — component catalog và patterns.
