<script setup>
import { computed } from 'vue';

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({
            total: 0, draft: 0, submitted: 0, reviewed: 0, late: 0,
        }),
    },
    statusFilter: { type: String, default: '' },
    lateFilter: { type: Boolean, default: false },
});

const emit = defineEmits(['set-status', 'toggle-late']);

const tiles = computed(() => [
    {
        id: 'all',
        label: 'Tất cả',
        value: props.summary.total ?? 0,
        active: !props.statusFilter && !props.lateFilter,
        onClick: () => emit('set-status', ''),
    },
    {
        id: 'reviewed',
        label: 'Đã duyệt',
        value: props.summary.reviewed ?? 0,
        tone: 'emerald',
        active: props.statusFilter === 'reviewed',
        onClick: () => emit('set-status', 'reviewed'),
    },
    {
        id: 'submitted',
        label: 'Chờ duyệt',
        value: props.summary.submitted ?? 0,
        tone: 'violet',
        active: props.statusFilter === 'submitted',
        onClick: () => emit('set-status', 'submitted'),
    },
    {
        id: 'draft',
        label: 'Nháp / trả lại',
        value: props.summary.draft ?? 0,
        tone: 'amber',
        active: props.statusFilter === 'draft',
        onClick: () => emit('set-status', 'draft'),
    },
    {
        id: 'late',
        label: 'Nộp trễ',
        value: props.summary.late ?? 0,
        tone: 'rose',
        active: props.lateFilter,
        onClick: () => emit('toggle-late'),
    },
]);

const toneClass = (tone, active) => {
    if (!active) return 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50';
    const map = {
        emerald: 'border-emerald-300 bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200',
        violet: 'border-violet-300 bg-violet-50 text-violet-800 ring-1 ring-violet-200',
        amber: 'border-amber-300 bg-amber-50 text-amber-900 ring-1 ring-amber-200',
        rose: 'border-rose-300 bg-rose-50 text-rose-800 ring-1 ring-rose-200',
    };
    return map[tone] ?? 'border-brand/40 bg-brand/5 text-brand ring-1 ring-brand/20';
};
</script>

<template>
  <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
    <button
      v-for="t in tiles"
      :key="t.id"
      type="button"
      class="flex flex-col items-start rounded-card border px-3 py-2.5 text-left transition"
      :class="toneClass(t.tone, t.active)"
      @click="t.onClick()"
    >
      <span class="text-[11px] font-medium opacity-80">{{ t.label }}</span>
      <span class="font-display text-xl font-semibold tabular-nums leading-tight">{{ t.value }}</span>
    </button>
  </div>
</template>
