---
name: datagrid-toolbar
description: >-
  Implements or reviews VA-QLDA datagrid toolbars: long search with "Tìm kiếm"
  label, short icon+text buttons (Lọc/Cột/Xuất), filters on second row, server-side
  filter params, CSV/Excel export composable. Mandatory for Pages and module
  table UIs. Use when building Index pages, CostReport, Department list, or
  any card+table toolbar.
---

# Datagrid toolbar — VA-QLDA (bắt buộc)

Rule: `.cursor/rules/datagrid-toolbar.mdc` (`alwaysApply: true`)

Reference implementation: `resources/js/Pages/AiAccount/CostReport.vue`

## When to use

- New or refactor page with **search + table + toolbar actions**
- User asks for filter bar, column picker, export, list toolbar UX
- PR review on `Pages/**` or `modules/**/components/**` with datagrid

## 1. Layout (copy)

```
┌─ Toolbar row 1 ─────────────────────────────────────────────────────┐
│ [Tìm kiếm] [════════════ input flex-1 long ════════════] [Lọc][Cột] │ [Xuất▼] [Primary]
└─────────────────────────────────────────────────────────────────────┘
┌─ Toolbar row 2 (if filter controls enabled) ────────────────────────┐
│ [Trạng thái ▼] [Loại ▼]  · summary · Đặt lại                          │
└─────────────────────────────────────────────────────────────────────┘
```

### Search (required)

```vue
<div class="flex min-w-0 flex-1 items-center gap-2">
  <label for="{page}-search" class="shrink-0 text-xs font-medium text-slate-500">
    Tìm kiếm
  </label>
  <div class="relative min-w-0 flex-1 sm:min-w-[200px] lg:min-w-[28rem] xl:min-w-[32rem]">
    <AppIcon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 …" />
    <input id="{page}-search" type="search" class="input h-9 w-full pl-9 pr-8 text-sm" />
    <!-- clear button when value -->
  </div>
</div>
```

- **Never** cap search with `max-w-xl` on large breakpoints.
- Wire `v-model` + debounced API or client filter per module.

### Icon + short label buttons (required)

| Control | Label | Icon |
|---------|-------|------|
| Filter visibility panel | `Lọc` | `filter` |
| Column visibility | `Cột` | `columns` |
| Export menu | `Xuất` | `export` |

```vue
<button
  type="button"
  class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium …"
>
  <AppIcon name="filter" :size="15" />
  <span>Lọc</span>
</button>
```

## 2. Filter panel vs filter values (bắt buộc)

- **Lọc** ≠ chọn giá trị lọc. **Lọc** = checkbox bật/tắt control nào hiện ở **dòng 2**.
- Dùng `useVisibleFilterControls(controls, storageKey)` + `FilterVisibilityDropdown.vue`.
- **Giá trị** (`status`, `type`, …): `<select>` / input trên **dòng 2**, `v-if="visibleFilters.status"`.
- On change: build params → `loadProposals(params)` / Inertia `router.get` with query.

```js
function buildFilterParams() {
  const p = {};
  if (status.value !== 'all') p.status = status.value;
  if (search.trim()) p.search = search.trim();
  return p;
}
watch([status, type], () => applyFilters());
watch(search, debounce(applyFilters, 350));
```

## 3. Export (required for exportable grids)

- Composable: `use{Entity}Export.js` in `modules/{domain}/composables/` or `composables/`
- API: `exportX({ list, columns, visibleKeys, filterNote, format: 'csv' | 'xlsx' })`
- Library: `xlsx-js-style`; filename `VA_{Entity}_{YYYY-MM-DD}.xlsx`
- Copy styles from `useAiProposalExport.js` or `useProjectListExport.js`

## 4. Click-outside & state

```js
const showFilterDd = ref(false);
const showColDd = ref(false);
const showExportDd = ref(false);
function onToolbarClickOutside(e) {
  if (filterRef.value && !filterRef.value.contains(e.target)) showFilterDd.value = false;
  // col, export, …
}
onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));
```

## 5. Review checklist

Pass only if all true:

1. Visible **Tìm kiếm** label + long input (`lg:min-w-[28rem]` or wider)
2. **Lọc**, **Cột**, **Xuất** have icon + short text (not icon-only)
3. Filter values on second row when enabled
4. Search/filter hits backend when API exists
5. Export via composable (CSV + Excel), not inline blob in page

## Related

- Import/export modal: `.cursor/rules/import-export-reconcile.mdc`
- Add page: `.cursor/skills/add-vue-page/SKILL.md`
- Frontend rule: `.cursor/rules/vue-inertia-frontend.mdc`
