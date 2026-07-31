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
    live: null,
    dev: 'Đang phát triển',
    planned: 'Sắp ra mắt',
    maintenance: 'Bảo trì',
};

const statusClass = {
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
    return item.count_label || 'Chưa cấu hình';
}

function ctaLabel(item) {
    if (item.status === 'planned') return 'Sắp ra mắt';
    if (item.configured) return item.configured_cta || 'Mở cấu hình';
    return item.empty_cta || 'Bắt đầu cấu hình';
}
</script>

<template>
  <ul class="divide-y divide-slate-100 overflow-hidden rounded-xl ring-1 ring-slate-200/80">
    <li
      v-for="item in items"
      :key="item.key"
    >
      <component
        :is="isClickable(item) ? Link : 'div'"
        v-bind="isClickable(item) ? { href: item.href } : {}"
        class="group flex gap-3.5 px-4 py-4 transition duration-200 sm:gap-4 sm:px-5"
        :class="isClickable(item)
          ? 'cursor-pointer hover:bg-brand/[0.03]'
          : 'opacity-95'"
      >
        <span
          class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1"
          :class="toneIcon[item.tone] ?? toneIcon.slate"
        >
          <AppIcon
            :name="item.icon"
            :size="18"
          />
        </span>

        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-start gap-x-2 gap-y-1">
            <h3
              class="text-sm font-semibold leading-snug text-slate-800"
              :class="isClickable(item) ? 'group-hover:text-brand' : ''"
            >
              {{ item.label }}
            </h3>
            <span
              v-if="statusLabel[item.status]"
              class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1"
              :class="statusClass[item.status] ?? statusClass.planned"
            >
              {{ statusLabel[item.status] }}
            </span>
          </div>

          <p
            v-if="item.description"
            class="mt-1 text-[13px] leading-relaxed text-slate-500"
          >
            {{ item.description }}
          </p>

          <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[12px]">
            <span
              class="font-medium"
              :class="item.configured ? 'text-emerald-700' : 'text-slate-500'"
            >
              {{ configLabel(item) }}
            </span>
            <span
              v-if="isClickable(item)"
              class="inline-flex items-center gap-1 font-semibold text-brand"
            >
              {{ ctaLabel(item) }}
              <AppIcon
                name="chevron-right"
                :size="13"
                class="transition group-hover:translate-x-0.5"
              />
            </span>
          </div>
        </div>

        <div
          v-if="item.count != null"
          class="flex shrink-0 flex-col items-end justify-center"
        >
          <span class="font-display text-xl font-semibold tabular-nums leading-none text-slate-800">
            {{ item.count }}
          </span>
          <span class="mt-1 text-[10px] font-medium uppercase tracking-wide text-slate-400">
            mục
          </span>
        </div>
      </component>
    </li>
  </ul>
</template>
