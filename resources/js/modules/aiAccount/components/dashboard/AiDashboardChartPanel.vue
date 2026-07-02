<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    icon: { type: String, default: 'performance' },
    loading: { type: Boolean, default: false },
    empty: { type: Boolean, default: false },
    emptyTitle: { type: String, default: 'Chưa có dữ liệu biểu đồ' },
    emptyDescription: { type: String, default: null },
    emptyIcon: { type: String, default: 'chart' },
    chartClass: { type: String, default: 'h-72' },
});
</script>

<template>
  <section
    class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm"
    :aria-label="title"
  >
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
      <div class="min-w-0">
        <h2 class="flex items-center gap-2 font-display text-sm font-semibold text-slate-800">
          <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand/10">
            <AppIcon
              :name="icon"
              :size="16"
              class="text-brand"
            />
          </span>
          <span class="truncate">{{ title }}</span>
        </h2>
        <p
          v-if="subtitle"
          class="mt-1 pl-10 text-xs leading-relaxed text-slate-500"
        >
          {{ subtitle }}
        </p>
      </div>
      <div
        v-if="$slots.toolbar"
        class="flex shrink-0 flex-wrap items-center gap-2"
      >
        <slot name="toolbar" />
      </div>
    </header>

    <div
      class="relative px-5 pb-5 pt-4"
      :class="chartClass"
    >
      <div
        v-if="loading && empty"
        class="flex h-full min-h-[12rem] flex-col justify-center gap-3 animate-pulse px-2"
        aria-hidden="true"
      >
        <div class="h-3 w-1/3 rounded bg-slate-100" />
        <div class="mt-4 flex-1 rounded-lg bg-slate-50" />
      </div>
      <EmptyState
        v-else-if="empty"
        class="py-10"
        :title="emptyTitle"
        :description="emptyDescription"
        :icon="emptyIcon"
      />
      <div
        v-else
        class="h-full min-h-[inherit]"
      >
        <div
          v-if="loading"
          class="absolute inset-4 z-10 flex items-center justify-center rounded-lg bg-white/75 backdrop-blur-[2px]"
          aria-live="polite"
        >
          <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
            <span class="h-4 w-4 animate-spin rounded-full border-2 border-brand/30 border-t-brand" />
            Đang cập nhật…
          </div>
        </div>
        <slot />
      </div>
    </div>
  </section>
</template>
