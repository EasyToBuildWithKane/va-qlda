<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({
            total: 0, draft: 0, submitted: 0, reviewed: 0, late: 0, completion_rate: 0, trend: {},
        }),
    },
    statusFilter: { type: String, default: '' },
    lateFilter: { type: Boolean, default: false },
});

const emit = defineEmits(['set-status', 'toggle-late']);

const trend = computed(() => props.summary.trend ?? {});

/**
 * Build a directional trend descriptor. `invert` flips the good/bad colouring
 * (used for "late", where an increase is bad).
 */
function trendPill(key, invert = false) {
    const v = trend.value[key];
    if (v === null || v === undefined) return { text: 'Mới', tone: 'neutral', arrow: '' };
    if (v === 0) return { text: '0%', tone: 'neutral', arrow: '·' };
    const up = v > 0;
    const good = invert ? !up : up;
    return {
        text: `${up ? '+' : ''}${v}%`,
        tone: good ? 'good' : 'bad',
        arrow: up ? '▲' : '▼',
    };
}

const tiles = computed(() => [
    {
        id: 'all',
        label: 'Tổng báo cáo',
        icon: 'documents',
        value: props.summary.total ?? 0,
        sub: `${props.summary.completion_rate ?? 0}% hoàn thành`,
        trend: trendPill('total'),
        tone: 'brand',
        active: !props.statusFilter && !props.lateFilter,
        onClick: () => emit('set-status', ''),
    },
    {
        id: 'reviewed',
        label: 'Đã duyệt',
        icon: 'check-circle',
        value: props.summary.reviewed ?? 0,
        trend: trendPill('reviewed'),
        tone: 'emerald',
        active: props.statusFilter === 'reviewed',
        onClick: () => emit('set-status', 'reviewed'),
    },
    {
        id: 'submitted',
        label: 'Chờ duyệt',
        icon: 'clock',
        value: props.summary.submitted ?? 0,
        trend: trendPill('submitted'),
        tone: 'violet',
        active: props.statusFilter === 'submitted',
        onClick: () => emit('set-status', 'submitted'),
    },
    {
        id: 'draft',
        label: 'Nháp / trả lại',
        icon: 'edit',
        value: props.summary.draft ?? 0,
        trend: trendPill('draft'),
        tone: 'amber',
        active: props.statusFilter === 'draft',
        onClick: () => emit('set-status', 'draft'),
    },
    {
        id: 'late',
        label: 'Nộp trễ',
        icon: 'alert',
        value: props.summary.late ?? 0,
        trend: trendPill('late', true),
        tone: 'rose',
        active: props.lateFilter,
        onClick: () => emit('toggle-late'),
    },
]);

const TONES = {
    brand: { idle: 'text-brand', activeBox: 'border-brand/40 bg-brand/5 ring-brand/20' },
    emerald: { idle: 'text-emerald-600', activeBox: 'border-emerald-300 bg-emerald-50 ring-emerald-200 dark:bg-emerald-950/30' },
    violet: { idle: 'text-violet-600', activeBox: 'border-violet-300 bg-violet-50 ring-violet-200 dark:bg-violet-950/30' },
    amber: { idle: 'text-amber-600', activeBox: 'border-amber-300 bg-amber-50 ring-amber-200 dark:bg-amber-950/30' },
    rose: { idle: 'text-rose-600', activeBox: 'border-rose-300 bg-rose-50 ring-rose-200 dark:bg-rose-950/30' },
};

function boxClass(t) {
    if (t.active) {
        return `${TONES[t.tone].activeBox} ring-1`;
    }
    return 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600';
}

const PILL_TONE = {
    good: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
    bad: 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300',
    neutral: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
};
</script>

<template>
  <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
    <button
      v-for="t in tiles"
      :key="t.id"
      type="button"
      class="flex flex-col gap-1.5 rounded-card border px-3 py-3 text-left transition"
      :class="boxClass(t)"
      @click="t.onClick()"
    >
      <div class="flex items-center justify-between gap-2">
        <span
          class="grid h-8 w-8 place-items-center rounded-lg bg-slate-50 dark:bg-slate-800"
          :class="TONES[t.tone].idle"
        >
          <AppIcon
            :name="t.icon"
            :size="16"
          />
        </span>
        <span
          v-if="t.trend"
          class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[10px] font-semibold tabular-nums"
          :class="PILL_TONE[t.trend.tone]"
          :title="'So với kỳ trước'"
        >
          <span v-if="t.trend.arrow">{{ t.trend.arrow }}</span>
          {{ t.trend.text }}
        </span>
      </div>
      <div>
        <div class="font-display text-2xl font-semibold leading-tight tabular-nums text-slate-800 dark:text-slate-100">
          {{ t.value }}
        </div>
        <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">
          {{ t.label }}
        </div>
        <div
          v-if="t.sub"
          class="mt-0.5 text-[10px] text-slate-400"
        >
          {{ t.sub }}
        </div>
      </div>
    </button>
  </div>
</template>
