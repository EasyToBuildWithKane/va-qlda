---
name: kpi-summary-strip
description: >-
  VA-Workspace KPI summary strip: kpi-strip section, clipped kpi-card grid, quick-filter
  cards, progress bars, backend summary props. Gold: FeedbackSummaryBar on /feedback.
  Use when adding stats sections, KPI cards, or cloning Feedback index overview UI.
---

# KPI summary strip — VA-Workspace

Rule: `.cursor/rules/kpi-summary-strip.mdc` (`alwaysApply: true`)

Live reference: [Theo dõi phản hồi — /feedback](https://projects.vaschools.edu.vn/feedback)

## Reference map

| Pattern | Files |
|---------|--------|
| Index hub + quick filter scope/status | `modules/project/components/FeedbackSummaryBar.vue`, `Pages/Feedback/Index.vue` |
| Embedded strip (no outer card radius) | `ContractShowSummaryBar.vue` + `Pages/Contract/Show.vue` (`variant="embedded"`) |
| Datagrid below strip | `Pages/Feedback/Index.vue` (toolbar trong `.card` riêng) |

---

## 1. Page wiring (Feedback)

```vue
<FeedbackSummaryBar
  :summary="summary"
  :active-scope="filterForm.scope"
  :active-status="filterForm.status"
  @quick-filter="onQuickFilter"
/>

<div class="card overflow-visible">
  <!-- DatagridToolbarSearch, filters, table -->
</div>
```

```js
function onQuickFilter({ scope, status }) {
  filterForm.scope = scope ?? '';
  filterForm.status = status ?? '';
}
```

Backend (`FeedbackController@index`):

```php
'summary' => [
    'total' => Feedback::count(),
    'open' => Feedback::open()->count(),
    'new' => Feedback::where('status', FeedbackStatus::New->value)->count(),
    'resolved' => Feedback::where('status', FeedbackStatus::Resolved->value)->count(),
    'avg_rating' => round((float) Feedback::whereNotNull('rating')->avg('rating'), 1),
],
```

Header badge có thể mirror KPI: `:badge="summary.open ?? null"` trên `PageHeader`.

---

## 2. Component contract

| Prop | Role |
|------|------|
| `summary` | Object KPI từ Inertia (required) |
| `activeScope` / `activeStatus` | Sync visual `kpi-card--active` với query |
| *(emit)* `quick-filter` | Payload `{ scope?, status? }` hoặc domain-specific |

Module mới: đặt tên `EntitySummaryBar.vue` cùng feature folder; **copy CSS block** từ `FeedbackSummaryBar.vue` scoped styles (chưa có shared CSS file — giữ đồng bộ khi sửa).

---

## 3. Card config (computed)

```js
const cards = computed(() => {
  const s = props.summary;
  const total = s.total ?? 0;
  const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

  return [
    {
      key: 'total',
      label: 'Tổng phản hồi',
      field: 'total',
      tone: 'brand',
      icon: 'feedback',
      sub: total ? 'Toàn hệ thống' : 'Chưa có phản hồi',
      progress: null,
      filter: { scope: '', status: '' }, // interactive
    },
    // filter: null → static card (e.g. avg rating)
  ];
});
```

Helpers: `displayValue(summary, card)`, `isInteractive(card)`, `isActive(card)`, `onCard(card)`.

Formats tùy chọn: `format: 'rating' | 'hours'` trong config + branch trong `displayValue`.

---

## 4. Strip markup skeleton

```vue
<section
  class="kpi-strip relative mb-5 overflow-x-hidden rounded-card border border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white px-4 py-4 shadow-sm sm:px-5 sm:py-5"
  aria-label="Thống kê phản hồi"
>
  <!-- kpi-strip__bg-outer / __bg-inner (pointer-events-none) -->
  <header class="relative mb-3 flex flex-wrap items-end justify-between gap-2">
    <div>
      <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">Thống kê</p>
      <h2 class="font-display text-sm font-semibold text-slate-800">Tổng quan …</h2>
    </div>
    <p class="text-[11px] text-slate-500">Thẻ có viền nét đứt — bấm để lọc nhanh danh sách</p>
  </header>

  <div class="relative grid grid-cols-2 gap-3 pb-1 sm:grid-cols-3 lg:grid-cols-5">
    <component
      :is="isInteractive(card) ? 'button' : 'div'"
      v-for="card in cards"
      :key="card.key"
      type="button"
      class="kpi-card group relative min-h-[6.75rem] …"
      :class="[toneClass[card.tone], isInteractive(card) ? 'kpi-card--interactive' : 'kpi-card--static', isActive(card) ? 'kpi-card--active' : '']"
      …
    >
      <!-- shell layers: __shell-outer, __shell-inner, __shell-accent, __shine -->
      <!-- label, value, icon, sub, optional progress bar, hover «Lọc nhanh» -->
    </component>
  </div>
</section>
```

---

## 5. Tones & icons

**`toneClass`:** `brand` | `emerald` | `amber` | `sky` | `violet` (+ `rose`, `slate` nếu cần).

**`iconToneClass`:** `text-{tone}-700 bg-{tone}-50 ring-{tone}-200/80` (brand dùng `text-brand bg-brand/10`).

**Gợi ý semantic**

| KPI | tone | icon (AppIcon) |
|-----|------|----------------|
| Tổng | brand | domain icon |
| Đang xử lý / mở | sky | `sprint` |
| Mới | violet | `add` |
| Hoàn thành / đã xử lý | emerald | `done` |
| Đánh giá | amber | `star` |

Giá trị chính: `brand` tone → `text-brand`; còn lại `text-slate-900`. Dùng `tabular-nums`.

---

## 6. Interactive UX

- Static: inset border nhẹ (`kpi-card--static`).
- Interactive: dashed outline → solid brand on hover; lift `translateY(-3px)`; shimmer trên accent (`kpi-shimmer` keyframes).
- Active filter: inset brand ring + shadow (`kpi-card--active`).
- Focus: `focus-visible` ring brand 3px.
- Hover hint: «Lọc nhanh» / «Đang lọc» + icon `filter` opacity transition.
- `@media (prefers-reduced-motion: reduce)` — tắt hover transform và shimmer.

---

## 7. Progress bar (optional)

Chỉ khi `card.progress != null && summary.total > 0`:

- Label «Tỷ lệ» + `%` tabular.
- Track `h-1.5 rounded-full bg-slate-100`; fill `kpi-card__bar` width inline, gradient theo tone modifier.

---

## 8. Variants

| Variant | Strip classes |
|---------|----------------|
| Standalone index | `rounded-card border shadow-sm mb-5` |
| Flush under header / in schedule | `shrink-0 border-b border-slate-100` (bỏ `rounded-card mb-5`) |

Page quyết định map emit (`filter-status` string hoặc `{ scope, status }`) sang query Inertia.

---

## 9. Anti-patterns

- KPI tiles Tailwind-only không có lớp shell clip-path.
- Duplicate 5× markup thay vì `cards` computed.
- Summary chỉ tính từ `feedback.data` trang hiện tại.
- Strip đặt dưới toolbar hoặc trong cùng `.card` với bảng.
- Thiếu `aria-pressed` / `aria-label` trên section.

---

## 10. Checklist PR

- [ ] `*SummaryBar.vue` + aggregate `summary` backend
- [ ] Page: active props + emit → Inertia filters
- [ ] Grid responsive 2 → 3 → 5 cột
- [ ] CSS khớp Feedback (copy scoped block)
- [ ] Việt hóa label, hint, empty sub copy
- [ ] Cập nhật `docs/FRONTEND_STRUCTURE.md` khi thêm module mới

---

## 11. Shared component

CSS: `resources/js/shared/styles/kpi-summary-strip.css` · UI: `shared/ui/KpiSummaryStrip.vue`. Module wrappers: `FeedbackSummaryBar`, `DailyReportSummaryBar`, `ProjectPortfolioSummaryBar`, `BlockerSummaryBar`, `AiCostReportSummaryBar`, `NotificationOpsSummaryBar`, `ContractShowSummaryBar`.
