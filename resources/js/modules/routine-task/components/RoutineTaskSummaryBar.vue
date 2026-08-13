<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import { hoursLabel } from '@/modules/routine-task/composables/useRoutineTasks';

const props = defineProps({
    summary: { type: Object, required: true },
    activeStatus: { type: String, default: '' },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => {
    if (props.activeStatus === 'todo') return 'todo';
    if (props.activeStatus === 'in_progress') return 'in_progress';
    if (props.activeStatus === 'done') return 'done';
    if (!props.activeStatus) return 'total';
    return '';
});

const cards = computed(() => {
    const s = props.summary ?? {};
    const total = s.total ?? 0;
    const todo = s.todo ?? 0;
    const inProgress = s.in_progress ?? 0;
    const done = s.done ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);
    const etHours = Number(s.estimate_hours ?? 0);
    const actualHours = Number(s.actual_hours ?? 0);
    const todayHours = hoursLabel(s.hours_today) ?? '0h';

    return [
        {
            key: 'total',
            label: 'Tổng việc',
            value: total,
            tone: 'brand',
            icon: 'list',
            sub: total ? 'Nhật ký công việc' : 'Chưa có việc nào',
            interactive: true,
            payload: { status: '' },
        },
        {
            key: 'todo',
            label: 'Cần làm',
            value: todo,
            tone: 'slate',
            icon: 'task',
            sub: total ? `${pct(todo)}% tổng` : 'Bấm để lọc',
            progress: pct(todo),
            interactive: true,
            payload: { status: 'todo' },
        },
        {
            key: 'in_progress',
            label: 'Đang làm',
            value: inProgress,
            tone: 'sky',
            icon: 'sprint',
            sub: total ? `${pct(inProgress)}% tổng` : 'Bấm để lọc',
            progress: pct(inProgress),
            interactive: true,
            payload: { status: 'in_progress' },
        },
        {
            key: 'done',
            label: 'Hoàn thành',
            value: done,
            tone: 'emerald',
            icon: 'done',
            sub: total ? `${pct(done)}% tổng` : 'Bấm để lọc',
            progress: pct(done),
            interactive: true,
            payload: { status: 'done' },
        },
        {
            key: 'estimate',
            label: 'Giờ ET',
            value: etHours,
            suffix: 'h',
            tone: 'violet',
            icon: 'timer',
            sub: 'Tổng giờ ước tính',
            interactive: false,
        },
        {
            key: 'actual',
            label: 'Giờ thực tế',
            value: actualHours,
            suffix: 'h',
            tone: 'amber',
            icon: 'clock',
            sub: `Hôm nay ${todayHours}`,
            interactive: false,
        },
    ];
});

function onSelect(card) {
    if (card.payload) emit('quick-filter', card.payload);
}
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê việc thường xuyên"
    heading="Nhật ký công việc hằng ngày"
    hint="Thẻ có viền nét đứt — bấm để lọc nhanh danh sách"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.total ?? 0"
    grid-class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6"
    @select="onSelect"
  />
</template>

<style scoped>
@import '@/shared/styles/kpi-summary-strip.css';
</style>
