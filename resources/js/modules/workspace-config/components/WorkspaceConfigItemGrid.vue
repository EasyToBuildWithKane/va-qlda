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

const toneBar = {
    brand: 'bg-brand',
    emerald: 'bg-emerald-500',
    amber: 'bg-amber-500',
    sky: 'bg-sky-500',
    violet: 'bg-violet-500',
    rose: 'bg-rose-500',
    slate: 'bg-slate-400',
};

const toneWash = {
    brand: 'from-brand/[0.07]',
    emerald: 'from-emerald-50',
    amber: 'from-amber-50',
    sky: 'from-sky-50',
    violet: 'from-violet-50',
    rose: 'from-rose-50',
    slate: 'from-slate-50',
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

function isClickable(item) {
    return item.href && item.href !== '#' && item.status !== 'planned';
}

function configLabel(item) {
    if (item.status === 'planned') return 'Sắp triển khai';
    if (item.configured) return item.count_label || 'Đã cấu hình';
    return item.count_label || 'Chưa cấu hình nội dung';
}
</script>

<template>
  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
    <component
      :is="isClickable(item) ? Link : 'div'"
      v-for="item in items"
      :key="item.key"
      v-bind="isClickable(item) ? { href: item.href } : {}"
      class="group relative flex flex-col overflow-hidden rounded-2xl bg-white shadow-[0_1px_3px_rgb(15_23_42/0.04)] ring-1 transition duration-200"
      :class="isClickable(item)
        ? 'ring-slate-200/75 hover:ring-brand/30 hover:shadow-md cursor-pointer'
        : 'ring-slate-200/60 opacity-95'"
    >
      <div
        class="absolute inset-x-0 top-0 h-1"
        :class="toneBar[item.tone] ?? toneBar.slate"
        aria-hidden="true"
      />
      <div
        class="flex flex-1 flex-col bg-gradient-to-br via-white to-white px-4 py-4"
        :class="toneWash[item.tone] ?? toneWash.slate"
      >
        <div class="flex items-start gap-3">
          <span
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl shadow-sm ring-1"
            :class="toneIcon[item.tone] ?? toneIcon.slate"
          >
            <AppIcon
              :name="item.icon"
              :size="20"
            />
          </span>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-1.5">
              <h3
                class="font-display text-sm font-semibold text-slate-800"
                :class="isClickable(item) ? 'group-hover:text-brand' : ''"
              >
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
            v-if="isClickable(item)"
            name="chevron-right"
            :size="16"
            class="mt-1 shrink-0 text-slate-300 transition group-hover:text-brand"
          />
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100/90 pt-3">
          <span
            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-medium ring-1"
            :class="item.configured
              ? 'bg-emerald-50 text-emerald-700 ring-emerald-200/80'
              : 'bg-slate-50 text-slate-500 ring-slate-200/80'"
          >
            <AppIcon
              :name="item.configured ? 'done' : 'system-config'"
              :size="12"
            />
            {{ configLabel(item) }}
          </span>
          <span
            v-if="item.count != null"
            class="ml-auto font-display text-lg font-semibold tabular-nums text-slate-800"
          >
            {{ item.count }}
          </span>
        </div>
      </div>
    </component>
  </div>
</template>
