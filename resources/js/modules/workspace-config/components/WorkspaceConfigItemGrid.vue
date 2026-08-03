<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    items: { type: Array, default: () => [] },
});

const toneIcon = {
    brand: 'text-brand bg-brand/10',
    emerald: 'text-emerald-700 bg-emerald-100',
    amber: 'text-amber-700 bg-amber-100',
    sky: 'text-sky-700 bg-sky-100',
    violet: 'text-violet-700 bg-violet-100',
    rose: 'text-rose-700 bg-rose-100',
    slate: 'text-slate-600 bg-slate-100',
};

const statusLabel = {
    live: null,
    dev: 'Đang phát triển',
    planned: 'Sắp ra mắt',
    maintenance: 'Bảo trì',
};

const statusTone = {
    dev: 'text-amber-700',
    planned: 'text-slate-500',
    maintenance: 'text-rose-700',
};

function isClickable(item) {
    return item.href && item.href !== '#' && item.status !== 'planned';
}

function configLabel(item) {
    if (item.status === 'planned') return 'Sắp triển khai';
    if (item.configured) return item.count_label || 'Đã cấu hình';
    return item.count_label || 'Chưa cấu hình';
}
</script>

<template>
  <ul class="space-y-2">
    <li
      v-for="item in items"
      :key="item.key"
    >
      <component
        :is="isClickable(item) ? Link : 'div'"
        v-bind="isClickable(item) ? { href: item.href } : {}"
        class="group flex gap-3.5 rounded-2xl px-4 py-4 transition duration-200 sm:gap-4 sm:px-5"
        :class="isClickable(item)
          ? 'cursor-pointer bg-slate-50 hover:bg-white hover:shadow-[0_8px_24px_-12px_rgb(15_23_42/0.18)]'
          : 'bg-slate-50/80 opacity-90'"
      >
        <span
          class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
          :class="toneIcon[item.tone] ?? toneIcon.slate"
        >
          <AppIcon
            :name="item.icon"
            :size="18"
          />
        </span>

        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
            <h3
              class="text-sm font-semibold leading-snug text-slate-800"
              :class="isClickable(item) ? 'group-hover:text-brand' : ''"
            >
              {{ item.label }}
            </h3>
            <span
              v-if="statusLabel[item.status]"
              class="text-[11px] font-medium"
              :class="statusTone[item.status] ?? statusTone.planned"
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

          <p
            class="mt-2 text-[12px] font-medium"
            :class="item.configured ? 'text-emerald-700' : 'text-slate-500'"
          >
            {{ configLabel(item) }}
          </p>
        </div>

        <div
          v-if="item.count != null"
          class="flex shrink-0 flex-col items-end justify-center"
        >
          <span class="font-display text-xl font-semibold tabular-nums leading-none text-slate-800">
            {{ item.count }}
          </span>
          <span class="mt-1 text-[11px] font-medium text-slate-400">
            mục
          </span>
        </div>
      </component>
    </li>
  </ul>
</template>
