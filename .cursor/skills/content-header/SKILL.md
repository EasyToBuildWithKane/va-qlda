---
name: content-header
description: >-
  VA-QLDA content header: AppLayout #header slot, PageHeader (title, subtitle,
  icon from Navigation.php, badge, back-href rules). Use when adding/editing
  Inertia pages, top bar title, removing back buttons, or aligning headers
  across modules.
---

# Content header — VA-QLDA

Rule: `.cursor/rules/content-header.mdc` (`alwaysApply: true`)

Component: `resources/js/Components/Ui/PageHeader.vue`  
Shell: `resources/js/Layouts/AppLayout.vue` — slot `#header` trong `<header class="h-14 …">`.

---

## Reference map

| Pattern | File |
|---------|------|
| Sidebar index + «Thêm» | `Pages/AiAccount/Index.vue` |
| Badge + workflow action | `Pages/AiAccount/CostReport.vue` |
| Dashboard + nhiều link slot | `Pages/Coaching/Dashboard.vue` |
| AI dashboard (icon `overview`) | `Pages/AiAccount/Dashboard.vue` |
| Analytics (icon `performance`) | `Pages/AiAccount/AnalyticsReport.vue` |
| Lịch + export/create trong header | `Pages/Coaching/Sessions/Schedule.vue` |
| Drill-down + back | `Pages/Project/Show.vue`, `Project/Create.vue`, `DailyReport/Show.vue` |

---

## 1. Page scaffold

```vue
<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
</script>

<template>
  <Head title="Tiêu đề tab" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Tiêu đề trang"
        subtitle="Mô tả một dòng"
        icon="account"
        icon-color="brand"
        :badge="count || null"
      >
        <button
          v-if="can.create"
          type="button"
          class="btn-primary inline-flex h-9 shrink-0 items-center gap-1.5 px-3 text-xs font-semibold"
          @click="openCreate"
        >
          <AppIcon name="add" :size="15" />
          Thêm mục
        </button>
      </PageHeader>
    </template>

    <!-- Nội dung trang — không PageHeader lặp -->
  </AppLayout>
</template>
```

`AppLayout :flush="true"`: dùng khi main cần full viewport (vd. `Project/Show.vue`); header pattern không đổi.

---

## 2. Icon ↔ Navigation

Khi trang có entry trong `app/Support/Navigation.php`, dùng **cùng** `icon` string nav item (map qua `AppIcon.vue`):

| Ví dụ nav | icon |
|-----------|------|
| Dashboard AI | `overview` |
| Báo cáo phân tích AI | `performance` |
| Tài khoản AI | `account` |
| PĐX & ĐNTT | `budget` / `performance` (CostReport dùng `performance`) |
| Coaching hub | `knowledge` |
| Lịch coaching | `calendar` |
| Danh sách buổi | `weekly` |

Thêm page mới → cập nhật `Navigation.php` trước/song song; header dùng icon đó.

---

## 3. Khi nào dùng `back-href`

| Dùng `back-href` | Không dùng |
|------------------|------------|
| Tạo/sửa entity (Create/Edit) | Mọi URL là mục sidebar trực tiếp |
| Show con (session → course, report → list) | Dashboard / analytics / index module |
| Flow thoát khỏi parent rõ ràng | «Quay hub» chỉ vì UX — user đã có nav |

Ví dụ hợp lệ: `Project/Show` → `back-href="/projects"`, `Project/Edit` → back tới show.

Ví dụ **cấm**: `/coaching/sessions/schedule` → `/coaching`; `/ai-accounts/dashboard` → `/ai-accounts`.

---

## 4. PageHeader API (tóm tắt)

| Prop | Type | Ghi chú |
|------|------|---------|
| `title` | string | required |
| `subtitle` | string | optional |
| `icon` | string | `AppIcon` name |
| `iconColor` | string | `brand`, `emerald`, `amber`, … |
| `badge` | string/number | pill brand |
| `backHref` | string | template: `back-href` |

Default slot: actions căn phải (flex trong PageHeader).

---

## 5. Review / sửa header lệch chuẩn

1. Grep `PageHeader` trong page — nếu >1 hoặc nằm ngoài `#header` → gộp lên slot.
2. Grep `back-href` — so với bảng mục 3; gỡ nếu trang là nav cấp 1.
3. So `Navigation.php` — thiếu `icon` → bổ sung.
4. Kiểm tra nút slot: `h-9`, `btn-primary` / `btn-ghost`, copy tiếng Việt.

---

## 6. Liên quan

- Datagrid toolbar nằm **dưới** header, trong card — skill `datagrid-toolbar`.
- Thêm page mới — skill `add-vue-page` (mục content header).
- Doc: `docs/FRONTEND_STRUCTURE.md` § Content header.
