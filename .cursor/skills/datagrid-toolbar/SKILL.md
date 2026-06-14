---
name: datagrid-toolbar
description: >-
  VA-QLDA datagrid toolbars: DatagridToolbarSearch (hide-label, inline-actions,
  h-10), DatagridToolbarActionButton, DatagridSegmentedControl, filter grid,
  FilterDatePicker, useVisibleFilterControls. Gold: DailyReport/History.vue,
  CostReport.vue, ProjectFeedbackPanel.vue. Use for Index, tables, filter UX reviews.
---

# Datagrid toolbar — VA-QLDA

Rule: `.cursor/rules/datagrid-toolbar.mdc` (`alwaysApply: true`)

## Reference map

| Pattern | File |
|---------|------|
| Full toolbar + segmented + column picker + filter grid + datepicker | `resources/js/Pages/DailyReport/History.vue` |
| Classic label «Tìm kiếm», nhiều filter `default: true` | `resources/js/Pages/AiAccount/CostReport.vue` |
| Embedded tab: `half`, primary cùng hàng | `modules/project/components/Dashboard/ProjectFeedbackPanel.vue` |
| Compact search | `modules/project/components/Dashboard/RiskIssueDataTable.vue` |

## Shared components

| Component | Path |
|-----------|------|
| Search | `shared/ui/DatagridToolbarSearch.vue` |
| Lọc/Cột/Xuất | `shared/ui/DatagridToolbarActionButton.vue` |
| Ngày·Tuần·Tháng / Thẻ·Bảng | `shared/ui/DatagridSegmentedControl.vue` |
| Bọc ô lọc (label tuỳ chọn) | `shared/ui/DatagridFilterField.vue` |
| Lọc ngày | `shared/ui/FilterDatePicker.vue` (`@vuepic/vue-datepicker`, locale `vi`) |
| Panel bật filter | `shared/ui/FilterVisibilityDropdown.vue` |
| Composable | `shared/composables/useVisibleFilterControls.js` |

---

## 1. Toolbar — một hàng desktop (History pattern)

**Mobile:** dòng 1 = ô tìm full width; dòng 2 = Lọc·Cột·Xuất trái, segmented phải (`ml-auto` trên nhóm segmented khi wrap).

**Desktop (`lg:flex-nowrap`):** cùng một hàng — tìm co giãn, nút giữa, segmented căn phải.

```vue
<div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
  <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
    <DatagridToolbarSearch
      v-model="filterForm.q"
      input-id="entity-search"
      placeholder="…"
      stretch
      inline-actions
      hide-label
      input-height="h-10"
    />
  </div>

  <div class="flex shrink-0 items-center gap-2">
    <!-- ref + DatagridToolbarActionButton Lọc + FilterVisibilityDropdown -->
    <!-- Cột, Xuất -->
  </div>

  <div class="ml-auto flex shrink-0 flex-wrap items-center justify-end gap-2">
    <DatagridSegmentedControl v-model="groupMode" :items="GROUP_TABS" icon-only-below-sm />
    <DatagridSegmentedControl v-model="viewMode" :items="VIEW_TABS" icon-only-below-sm />
  </div>
</div>
```

### DatagridToolbarSearch props

| Prop | Use |
|------|-----|
| *(default)* | Index: label «Tìm kiếm», long min-widths |
| `half` | ~50% row, project tab |
| `compact` | Short search |
| `stretch` + `inline-actions` | Cùng hàng với nút; ô `flex-1 min-w-0` |
| `hide-label` | Placeholder + `aria-label`; không chữ «Tìm kiếm» |
| `input-height="h-10"` | Căn `h-10` với nút/segmented |
| `cap-input-width` | Max ~36rem khi không inline với nút |

### Anti-pattern

**Không** dùng `md:contents` + `order-*` để gộp hàng — flex children của `contents` có `order: 0`, ô tìm `order-1` bị đẩy xuống dòng 2 trên desktop.

### Card toolbar

- `overflow-visible` cho dropdown teleport.
- Không `sticky top-0` mặc định.

---

## 2. DatagridToolbarActionButton

Thay nút lặp class; `h-10`, `px-3`, icon size 15, active `border-brand/40 bg-brand/5 text-brand`.

```vue
<DatagridToolbarActionButton icon="filter" :active="showFilterPanelDd" @click="openFilterPanel(closeOthers)">
  Lọc
</DatagridToolbarActionButton>
```

---

## 3. DatagridSegmentedControl

- Container `h-10`, active segment nền trắng + shadow.
- `icon-only-below-sm`: ẩn chữ `< sm`, giữ `title` / `aria-label`.

---

## 4. Filter visibility (Lọc)

```js
const FILTER_CONTROLS = [
  { key: 'status', label: 'Trạng thái', default: false },
  { key: 'date_range', label: 'Thời gian', default: false },
];

const {
  visibleFilters,
  hasFilterRow,
  persistVisibleFilters,
  openFilterPanel,
} = useVisibleFilterControls(FILTER_CONTROLS, 'va-qlda.{module}.visible-filters.v3');
```

- `default: false` → lần đầu **không** dòng filter.
- Đổi keys hoặc default → bump `storageKey`.
- `openFilterPanel(() => { colsMenu = false; exportMenu = false })`.

---

## 5. Filter value row — CSS grid

```vue
<Transition name="fade-slide">
  <div
    v-if="hasFilterRow"
    class="grid grid-cols-1 gap-3 border-t border-slate-100 px-5 py-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6 dark:border-slate-700"
  >
    <DatagridFilterField v-if="visibleFilters.status">
      <select v-model="status" :class="FILTER_CONTROL_CLASS" aria-label="Trạng thái">
        <option value="">Trạng thái</option>
        <!-- … -->
      </select>
    </DatagridFilterField>

    <div v-if="visibleFilters.date_range" class="min-w-0 w-full sm:col-span-2 xl:col-span-2">
      <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:gap-3">
        <FilterDatePicker v-model="filterForm.from" placeholder="Từ ngày" :max-date="filterForm.to || null" />
        <FilterDatePicker v-model="filterForm.to" placeholder="Đến ngày" :min-date="filterForm.from || null" />
      </div>
    </div>

    <div v-if="activeCount" class="col-span-full flex justify-end">
      <button type="button" class="text-xs font-medium text-brand" @click="clearFilters">Đặt lại bộ lọc</button>
    </div>
  </div>
</Transition>
```

```js
const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';
```

### Select / multi label rule

- Không label HTML phía trên (`DatagridFilterField` không truyền `label`).
- Option rỗng / placeholder = **tên trường** (`Dự án`, `Người báo cáo`), không `Dự án: Tất cả`.

### SearchMultiSelect trong grid

`control-size="md"` → `h-10`.

---

## 6. FilterDatePicker

- Wrapper: `shared/ui/FilterDatePicker.vue`
- Dep: `@vuepic/vue-datepicker` + `import '@vuepic/vue-datepicker/dist/main.css'` trong component
- `model-type="yyyy-MM-dd"`, `format="dd/MM/yyyy"`, `locale` từ `date-fns/locale` `vi`
- `teleport: true`, brand `#9A0036` cho ngày chọn (scoped `:deep`)

Không dùng `type="date"` native cho filter user-facing trên trang mới/refactor.

---

## 7. Classic row (CostReport)

Giữ label «Tìm kiếm», `h-9` nút, filter có thể `default: true`, date có thể `type="date"` cho đến khi migrate `FilterDatePicker`.

---

## 8. Export & click-outside

- Export: `use*Export.js`, một nút + menu CSV/Excel.
- `data-filter-visibility-panel` / `data-*-toolbar-panel` + `mousedown` document close.

---

## 9. Review checklist

1. Desktop một hàng (`lg:flex-nowrap`), không `contents`/`order` lỗi
2. `hide-label` vs label «Tìm kiếm» đúng trang
3. `h-10` đồng nhất (search, nút, segmented, filter)
4. Grid `xl:grid-cols-6`, không hard-code width filter
5. `default: false` + `hasFilterRow`
6. Ngày: `date_range` + `FilterDatePicker`
7. Inertia debounce ~350ms cho `q`

## Related

- `.cursor/rules/import-export-reconcile.mdc`
- `docs/FRONTEND_STRUCTURE.md` (shared UI table)
- `.cursor/skills/add-vue-page/SKILL.md`
