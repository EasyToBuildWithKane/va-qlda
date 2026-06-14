---
name: datagrid-toolbar
description: >-
  Implements or reviews VA-QLDA datagrid toolbars: DatagridToolbarSearch (long,
  half, compact), icon+text Lọc/Cột/Xuất, filter row opt-in via useVisibleFilterControls,
  embedded project-tab layout (search half + actions same row). Use for Index pages,
  CostReport, ProjectFeedbackPanel, RiskIssueDataTable, or any card+table toolbar.
---

# Datagrid toolbar — VA-QLDA (bắt buộc)

Rule: `.cursor/rules/datagrid-toolbar.mdc` (`alwaysApply: true`)

| Ngữ cảnh | Tham chiếu vàng |
|----------|-----------------|
| Trang full width | `resources/js/Pages/AiAccount/CostReport.vue` |
| Tab / panel nhúng (ô tìm ~50%, nút cùng hàng) | `resources/js/modules/project/components/Dashboard/ProjectFeedbackPanel.vue` |
| Panel nhúng, ô tìm ngắn | `resources/js/modules/project/components/Dashboard/RiskIssueDataTable.vue` (`compact`) |

## When to use

- New or refactor page with **search + table + toolbar actions**
- User asks for filter bar, column picker, export, «tìm kiếm một nửa», «bộ lọc chọn mới hiện»
- PR review on `Pages/**` or `modules/**/components/**` with datagrid

## 1. Layout

### Full-width page (Index, CostReport)

```
┌─ Row 1 ─────────────────────────────────────────────────────────────┐
│ [Tìm kiếm] [════════════ input flex-1 long ════════════] [Lọc][Cột] │ [Xuất▼] [Primary]
└─────────────────────────────────────────────────────────────────────┘
┌─ Row 2 (hasFilterRow) ──────────────────────────────────────────────┐
│ [Trạng thái ▼] [Loại ▼]  · summary · Đặt lại                          │
└─────────────────────────────────────────────────────────────────────┘
```

### Embedded panel (project tab, dashboard card)

```
┌─ Row 1 (flex-wrap, gap-2) ────────────────────────────────────────────┐
│ [Tìm kiếm][════ half width ~50% sm+ ════] [Lọc][Cột][Primary]        │
└─────────────────────────────────────────────────────────────────────┘
```

- **Không** `lg:justify-between` tách primary sang cột phải — gom nút trong `flex shrink-0 flex-wrap items-center gap-2`.
- Dòng 2: `v-if="hasFilterRow"`; mỗi control `v-if="visibleFilters.{key}"`.

## 2. DatagridToolbarSearch

Component: `resources/js/shared/ui/DatagridToolbarSearch.vue`

| Prop | Khi dùng |
|------|----------|
| *(mặc định)* | Trang Index — `flex-1`, `lg:min-w-[28rem]` `xl:min-w-[32rem]` |
| `half` | Tab dự án / panel rộng vừa — `sm:w-1/2 sm:max-w-[50%]` |
| `compact` | Toolbar chật, ô tìm ngắn (Risk trong project) |
| `stretch` | Ô tìm chiếm phần flex còn lại (coaching list, …) |

```vue
<!-- Project tab feedback -->
<div class="flex min-w-0 flex-wrap items-center gap-2">
  <DatagridToolbarSearch v-model="q" half input-id="project-feedback-search" />
  <div class="flex shrink-0 flex-wrap items-center gap-2">
    <!-- Lọc, Cột, btn-primary -->
  </div>
</div>
```

## 3. Filter visibility (Lọc) — opt-in dòng 2

**Lọc** chỉ bật/tắt control trên dòng 2, không chọn giá trị.

```js
const FILTER_CONTROLS = [
  { key: 'status', label: 'Trạng thái', default: false },
  { key: 'category', label: 'Phân loại', default: false },
  // default: true → hiện ngay lần đầu (CostReport, Risk status/severity)
];

const {
  visibleFilters,
  hasFilterRow,
  persistVisibleFilters,
  openFilterPanel,
} = useVisibleFilterControls(FILTER_CONTROLS, 'va-qlda.{module}.visible-filters.v2');
```

Composable: `resources/js/shared/composables/useVisibleFilterControls.js`

- **defaultState:** `c.default !== false` → không khai báo `default` = bật; `default: false` = tắt lần đầu.
- **localStorage:** merge theo key — chỉ override khi key có trong JSON đã lưu; key mới trong code dùng `defaultState`.
- **Đổi hành vi mặc định** cho user đã lưu cũ → bump `storageKey` (`.v2`, `.v3`).

UI: `FilterVisibilityDropdown.vue` + `@persist="persistVisibleFilters"`.

Filter **values** on row 2:

```js
watch(search, debounce(() => router.get(...), 350));
watch([status, category], () => router.get(...));
```

## 4. Icon + short label buttons

| Control | Label | Icon |
|---------|-------|------|
| Filter visibility | `Lọc` | `filter` |
| Columns | `Cột` | `columns` |
| Export | `Xuất` | `export` |

```vue
<button type="button" class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium …">
  <AppIcon name="filter" :size="15" />
  <span>Lọc</span>
</button>
```

## 5. Export

- Composable `use{Entity}Export.js`; `xlsx-js-style`; CSV + Excel từ một nút **Xuất**
- Không blob/export logic trong `.vue`

## 6. Click-outside

```js
function onToolbarClickOutside(e) {
  if (e.target.closest?.('[data-filter-visibility-panel]')) return;
  if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) showFilterPanelDd.value = false;
}
```

Mở Lọc → đóng Cột/Xuất (callback trong `openFilterPanel`).

## 7. Review checklist

1. **Tìm kiếm** label + đúng prop (`half` / `compact` / long)
2. Panel nhúng: Lọc, Cột, Primary **cùng hàng**
3. Dòng 2 chỉ khi `hasFilterRow`; opt-in = `default: false` trên controls
4. Search/filter gọi API khi có endpoint
5. Lọc/Cột/Xuất: icon + chữ ngắn (không `w-9` icon-only)

## Related

- `.cursor/rules/import-export-reconcile.mdc`
- `.cursor/skills/add-vue-page/SKILL.md`
- `.cursor/rules/vue-inertia-frontend.mdc`
