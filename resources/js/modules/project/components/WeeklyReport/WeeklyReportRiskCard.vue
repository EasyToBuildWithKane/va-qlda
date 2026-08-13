<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    risk: { type: Object, default: () => ({ risks: [], summary: { high: 0, medium: 0, low: 0 } }) },
});

const levelMeta = {
    high: { label: 'Cao' },
    medium: { label: 'Trung bình' },
    low: { label: 'Thấp' },
};

const summary = computed(() => props.risk?.summary ?? { high: 0, medium: 0, low: 0 });
const risks = computed(() => props.risk?.risks ?? []);
</script>

<template>
  <section class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
    <header class="mb-3 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
          <AppIcon
            name="alert"
            :size="15"
          />
        </span>
        <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-200">
          Đánh giá rủi ro
        </h3>
      </div>
      <p class="text-xs text-slate-500 tabular-nums">
        Cao {{ summary.high }} · Trung bình {{ summary.medium }} · Thấp {{ summary.low }}
      </p>
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
        <div class="min-w-0">
          <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
            {{ levelMeta[r.level]?.label }} · {{ r.label }}
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
