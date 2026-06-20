<script setup>
/**
 * Dải cảnh báo liên-module — chip hành động cho các hạng mục cần chú ý
 * (quá hạn · chờ duyệt · sắp hết hạn …). Ẩn hoàn toàn khi không có cảnh báo.
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    alerts: { type: Array, default: () => [] },
});

const items = computed(() => props.alerts ?? []);

const toneIcon = {
    brand: 'text-brand bg-brand/10 ring-brand/20',
    emerald: 'text-emerald-700 bg-emerald-50 ring-emerald-200/80',
    amber: 'text-amber-700 bg-amber-50 ring-amber-200/80',
    sky: 'text-sky-700 bg-sky-50 ring-sky-200/80',
    violet: 'text-violet-700 bg-violet-50 ring-violet-200/80',
    rose: 'text-rose-700 bg-rose-50 ring-rose-200/80',
    slate: 'text-slate-600 bg-slate-100 ring-slate-200/80',
};

const toneValue = {
    brand: 'text-brand',
    emerald: 'text-emerald-700',
    amber: 'text-amber-700',
    sky: 'text-sky-700',
    violet: 'text-violet-700',
    rose: 'text-rose-700',
    slate: 'text-slate-800',
};

const alertChip = {
    brand: 'bg-brand/5 ring-brand/15 hover:bg-brand/[0.09]',
    emerald: 'bg-emerald-50/90 ring-emerald-200/70 hover:bg-emerald-50',
    amber: 'bg-amber-50/90 ring-amber-200/70 hover:bg-amber-50',
    sky: 'bg-sky-50/90 ring-sky-200/70 hover:bg-sky-50',
    violet: 'bg-violet-50/90 ring-violet-200/70 hover:bg-violet-50',
    rose: 'bg-rose-50/90 ring-rose-200/70 hover:bg-rose-50',
    slate: 'bg-slate-50 ring-slate-200/80 hover:bg-slate-50',
};
</script>

<template>
  <section
    v-if="items.length"
    class="overflow-hidden rounded-card border border-slate-200/80 bg-white shadow-sm"
    aria-label="Hạng mục cần chú ý"
  >
    <header class="flex items-center gap-2 border-b border-slate-100/90 px-5 py-3.5">
      <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-rose-50 text-rose-600 ring-1 ring-rose-200/80">
        <AppIcon
          name="notifications"
          :size="15"
        />
      </span>
      <div>
        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-rose-500/90">
          Cần chú ý
        </p>
        <h2 class="font-display text-sm font-semibold leading-tight text-slate-800">
          {{ items.length }} hạng mục cần xử lý
        </h2>
      </div>
    </header>

    <ul class="flex flex-wrap gap-2.5 p-4">
      <li
        v-for="item in items"
        :key="item.key"
        class="min-w-[13rem] flex-1"
      >
        <Link
          :href="item.href"
          class="group flex h-full items-center gap-3 rounded-xl px-3.5 py-2.5 ring-1 transition hover:shadow-sm"
          :class="alertChip[item.tone] ?? alertChip.slate"
        >
          <span
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ring-1"
            :class="toneIcon[item.tone] ?? toneIcon.slate"
          >
            <AppIcon
              :name="item.icon"
              :size="16"
            />
          </span>
          <span class="min-w-0 flex-1 text-left">
            <span class="block truncate text-[12px] font-medium text-slate-700">{{ item.label }}</span>
            <span
              class="font-display text-xl font-bold leading-tight tabular-nums"
              :class="toneValue[item.tone] ?? toneValue.slate"
            >
              {{ item.value }}
            </span>
          </span>
          <AppIcon
            name="chevron-right"
            :size="15"
            class="shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand/70"
          />
        </Link>
      </li>
    </ul>
  </section>
</template>
