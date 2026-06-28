<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    risk: { type: Object, default: () => ({ risks: [], summary: { high: 0, medium: 0, low: 0 } }) },
});

const levelMeta = {
    high: { label: 'Cao', dot: 'bg-rose-500', chip: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' },
    medium: { label: 'Trung bình', dot: 'bg-amber-500', chip: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' },
    low: { label: 'Thấp', dot: 'bg-slate-400', chip: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' },
};

const summary = computed(() => props.risk?.summary ?? { high: 0, medium: 0, low: 0 });
const risks = computed(() => props.risk?.risks ?? []);
</script>

<template>
  <section class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
    <header class="mb-3 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-rose-100 text-rose-600 dark:bg-rose-950/60 dark:text-rose-300">
          <AppIcon
            name="alert"
            :size="15"
          />
        </span>
        <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-200">
          Đánh giá rủi ro
        </h3>
      </div>
      <div class="flex items-center gap-1.5">
        <span
          v-for="lvl in ['high', 'medium', 'low']"
          :key="lvl"
          class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold tabular-nums"
          :class="levelMeta[lvl].chip"
        >{{ levelMeta[lvl].label }} {{ summary[lvl] }}</span>
      </div>
    </header>

    <ul
      v-if="risks.length"
      class="space-y-2"
    >
      <li
        v-for="(r, i) in risks"
        :key="i"
        class="flex gap-2.5 rounded-lg border border-slate-100 p-2.5 dark:border-slate-800"
      >
        <span
          class="mt-1 inline-block h-2 w-2 shrink-0 rounded-full"
          :class="levelMeta[r.level].dot"
        />
        <div class="min-w-0">
          <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
            {{ r.label }}
          </p>
          <p class="text-xs text-slate-500">
            {{ r.reason }}
          </p>
        </div>
      </li>
    </ul>
    <p
      v-else
      class="text-sm italic text-slate-400"
    >
      Không có rủi ro đáng kể trong tuần.
    </p>
  </section>
</template>
