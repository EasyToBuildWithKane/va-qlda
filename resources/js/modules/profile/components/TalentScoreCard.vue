<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    score: { type: Object, required: true },
});

const total = computed(() => props.score.total);
const components = computed(() => props.score.components || []);

// Circle geometry (r=16 → circumference ≈ 100.53).
const CIRC = 2 * Math.PI * 16;
const dash = computed(() => {
    const pct = total.value ?? 0;
    return `${(pct / 100) * CIRC} ${CIRC}`;
});

const ring = computed(() => {
    const v = total.value ?? 0;
    if (v >= 80) return '#10b981';
    if (v >= 60) return '#0ea5e9';
    if (v >= 40) return '#f59e0b';
    return '#f43f5e';
});

const barTone = {
    sky: 'bg-sky-500',
    emerald: 'bg-emerald-500',
    violet: 'bg-violet-500',
    rose: 'bg-rose-500',
    amber: 'bg-amber-500',
};
</script>

<template>
  <section class="rounded-2xl border border-slate-200/70 bg-white shadow-sm">
    <header class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5">
      <div class="grid h-8 w-8 place-items-center rounded-lg bg-brand/10 text-brand">
        <AppIcon
          name="talent-score"
          :size="16"
        />
      </div>
      <h2 class="text-sm font-semibold text-slate-800">
        Talent Score
      </h2>
    </header>

    <div class="p-5">
      <!-- Gauge -->
      <div class="flex flex-col items-center">
        <div class="relative h-32 w-32">
          <svg
            viewBox="0 0 36 36"
            class="h-full w-full -rotate-90"
          >
            <circle
              cx="18"
              cy="18"
              r="16"
              fill="none"
              stroke="#f1f5f9"
              stroke-width="3.5"
            />
            <circle
              cx="18"
              cy="18"
              r="16"
              fill="none"
              :stroke="ring"
              stroke-width="3.5"
              stroke-linecap="round"
              :stroke-dasharray="dash"
              class="transition-all duration-700 ease-out"
            />
          </svg>
          <div class="absolute inset-0 flex flex-col items-center justify-center">
            <span class="text-3xl font-bold tabular-nums text-slate-900">{{ total ?? '—' }}</span>
            <span class="text-[11px] text-slate-400">/ 100</span>
          </div>
        </div>
        <p class="mt-1 text-[12px] text-slate-400">
          Điểm năng lực tổng hợp
        </p>
      </div>

      <!-- Component breakdown -->
      <div class="mt-5 space-y-2.5">
        <div
          v-for="c in components"
          :key="c.key"
        >
          <div class="mb-1 flex items-center justify-between text-[12px]">
            <span class="text-slate-600">{{ c.label }}</span>
            <span class="font-medium tabular-nums text-slate-500">{{ c.score ?? '—' }}<span v-if="c.score !== null">%</span></span>
          </div>
          <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
            <div
              class="h-full rounded-full transition-all duration-500"
              :class="barTone[c.color] || 'bg-slate-400'"
              :style="{ width: (c.score ?? 0) + '%' }"
            />
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
