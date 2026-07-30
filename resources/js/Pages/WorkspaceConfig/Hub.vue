<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import WorkspaceConfigItemGrid from '@/modules/workspace-config/components/WorkspaceConfigItemGrid.vue';

defineProps({
    items: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({ total: 0, live: 0 }) },
});
</script>

<template>
  <Head title="Cấu hình workspace" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Cấu hình workspace"
        subtitle="Quản trị cấu hình nghiệp vụ workspace — đánh giá và các mục mở rộng sau này"
        icon="system-config"
        icon-color="brand"
        :badge="summary.total || null"
      />
    </template>

    <section
      class="relative mb-5 overflow-hidden rounded-card border border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white shadow-sm"
      aria-label="Danh mục cấu hình workspace"
    >
      <div
        class="pointer-events-none absolute -right-6 top-0 h-full w-1/2 bg-gradient-to-l from-brand/[0.04] to-transparent"
        aria-hidden="true"
      />
      <header class="relative border-b border-slate-100/80 px-5 py-4">
        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
          Module
        </p>
        <h2 class="mt-1 font-display text-base font-semibold text-slate-800">
          Mục cấu hình
        </h2>
        <p class="mt-1 text-sm text-slate-500">
          Chọn một mục để mở màn hình quản trị chi tiết. Các mục mới sẽ xuất hiện tại đây khi được triển khai.
        </p>
      </header>
      <div class="relative p-4 md:p-5">
        <WorkspaceConfigItemGrid :items="items" />
        <p
          v-if="!items.length"
          class="rounded-xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-500 ring-1 ring-slate-200/70"
        >
          Chưa có mục cấu hình nào bạn được phép xem.
        </p>
      </div>
    </section>
  </AppLayout>
</template>
