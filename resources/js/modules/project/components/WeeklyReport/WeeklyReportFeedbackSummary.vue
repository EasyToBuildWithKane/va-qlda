<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    feedback: { type: Object, default: () => ({ breakdown: [], total: 0 }) },
});

const colorClasses = {
    emerald: 'text-emerald-600 bg-emerald-50 border-emerald-200 dark:bg-emerald-950/40 dark:border-emerald-900',
    sky: 'text-sky-600 bg-sky-50 border-sky-200 dark:bg-sky-950/40 dark:border-sky-900',
    rose: 'text-rose-600 bg-rose-50 border-rose-200 dark:bg-rose-950/40 dark:border-rose-900',
    amber: 'text-amber-600 bg-amber-50 border-amber-200 dark:bg-amber-950/40 dark:border-amber-900',
    violet: 'text-violet-600 bg-violet-50 border-violet-200 dark:bg-violet-950/40 dark:border-violet-900',
};

const breakdown = computed(() => props.feedback?.breakdown ?? []);
</script>

<template>
  <section class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
    <header class="mb-3 flex items-center gap-2">
      <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-violet-100 text-violet-600 dark:bg-violet-950/60 dark:text-violet-300">
        <AppIcon
          name="feedback"
          :size="15"
        />
      </span>
      <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-200">
        Tổng hợp phản hồi
      </h3>
      <span class="ml-auto text-xs text-slate-400">{{ feedback?.total ?? 0 }} tổng</span>
    </header>

    <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
      <div
        v-for="b in breakdown"
        :key="b.key"
        class="rounded-lg border p-2.5 text-center"
        :class="colorClasses[b.color] || colorClasses.sky"
      >
        <div class="font-display text-xl font-semibold tabular-nums">
          {{ b.count }}
        </div>
        <div class="text-[11px] font-medium text-slate-500">
          {{ b.label }}
        </div>
      </div>
    </div>
  </section>
</template>
