<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    items: { type: Array, default: () => [] },
});

const toneIcon = {
    brand: 'text-brand bg-brand/10 ring-brand/20',
    emerald: 'text-emerald-700 bg-emerald-50 ring-emerald-200/80',
    amber: 'text-amber-700 bg-amber-50 ring-amber-200/80',
    sky: 'text-sky-700 bg-sky-50 ring-sky-200/80',
    violet: 'text-violet-700 bg-violet-50 ring-violet-200/80',
    rose: 'text-rose-700 bg-rose-50 ring-rose-200/80',
    slate: 'text-slate-600 bg-slate-100 ring-slate-200/80',
};

const statusLabel = {
    live: 'Đang dùng',
    dev: 'Đang phát triển',
    planned: 'Sắp ra mắt',
    maintenance: 'Bảo trì',
};

const statusClass = {
    live: 'bg-emerald-50 text-emerald-700 ring-emerald-200/80',
    dev: 'bg-amber-50 text-amber-800 ring-amber-200/80',
    planned: 'bg-slate-100 text-slate-600 ring-slate-200/80',
    maintenance: 'bg-rose-50 text-rose-700 ring-rose-200/80',
};
</script>

<template>
  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
    <Link
      v-for="item in items"
      :key="item.key"
      :href="item.href"
      class="group flex flex-col overflow-hidden rounded-xl bg-white ring-1 ring-slate-200/75 shadow-[0_1px_3px_rgb(15_23_42/0.04)] transition hover:ring-brand/30 hover:shadow-md"
    >
      <div class="flex items-start gap-3 bg-gradient-to-br from-slate-50/90 via-white to-white px-4 py-4">
        <span
          class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ring-1 shadow-sm"
          :class="toneIcon[item.tone] ?? toneIcon.slate"
        >
          <AppIcon
            :name="item.icon"
            :size="18"
          />
        </span>
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2">
            <h3 class="font-display text-sm font-semibold text-slate-800 group-hover:text-brand">
              {{ item.label }}
            </h3>
            <span
              class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1"
              :class="statusClass[item.status] ?? statusClass.planned"
            >
              {{ statusLabel[item.status] ?? item.status }}
            </span>
          </div>
          <p class="mt-1.5 text-[13px] leading-snug text-slate-500">
            {{ item.description }}
          </p>
        </div>
        <AppIcon
          name="chevron-right"
          :size="16"
          class="mt-1 shrink-0 text-slate-300 transition group-hover:text-brand"
        />
      </div>
    </Link>
  </div>
</template>
