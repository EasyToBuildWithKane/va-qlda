<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import { EMPTY_LABELS } from '@/shared/utils/emptyDisplay';

const props = defineProps({
    kpi: { type: Object, default: () => ({}) },
});

const cards = computed(() => {
    const k = props.kpi || {};
    const n = (v) => Number(v ?? 0);
    const total = n(k.total_tasks);
    const weekDone = n(k.week_completed);
    const weekSp = n(k.week_story_points);
    const hours = n(k.worklog_hours);
    const remaining = n(k.remaining_tasks);
    const pctSprint = n(k.sprint_progress);

    const weekSub = weekDone === 0
        ? 'Chưa hoàn thành hạng mục mới'
        : (weekSp > 0 ? `${weekSp} story points giao trong tuần` : `${weekDone} hạng mục trong cửa sổ tuần`);

    return [
        {
            key: 'week_done',
            label: 'Hoàn thành tuần',
            value: weekDone,
            tone: weekDone ? 'emerald' : 'slate',
            icon: 'check-circle',
            sub: weekSub,
            interactive: false,
        },
        {
            key: 'progress',
            label: 'Tiến độ Sprint',
            value: `${pctSprint}%`,
            tone: 'brand',
            icon: 'overview',
            sub: total ? `${n(k.completed_tasks)}/${total} hạng mục` : EMPTY_LABELS.generic,
            progress: pctSprint,
            interactive: false,
        },
        {
            key: 'value',
            label: 'Giá trị (SP)',
            value: weekSp > 0 ? weekSp : n(k.sprint_story_points),
            tone: 'violet',
            icon: 'sparkles',
            sub: weekSp > 0
                ? 'Story points hoàn thành tuần'
                : (n(k.sprint_story_points) > 0 ? `${n(k.sprint_story_points)} SP cam kết Sprint` : 'Chưa gán story points'),
            interactive: false,
        },
        {
            key: 'hours',
            label: 'Giờ công tuần',
            value: hours,
            tone: hours ? 'sky' : 'slate',
            icon: 'clock',
            sub: hours ? 'Worklog đã ghi nhận' : 'Chưa ghi nhận giờ công',
            interactive: false,
        },
        {
            key: 'remaining',
            label: 'Còn lại',
            value: remaining,
            tone: 'sky',
            icon: 'task',
            sub: n(k.in_progress) ? `${n(k.in_progress)} đang làm / review` : (total ? 'Chưa có việc đang làm' : EMPTY_LABELS.generic),
            interactive: false,
        },
        {
            key: 'overdue',
            label: 'Quá hạn',
            value: n(k.overdue),
            tone: n(k.overdue) ? 'rose' : 'slate',
            icon: 'alert',
            sub: n(k.overdue) ? 'Cần xử lý ưu tiên' : 'Không quá hạn',
            interactive: false,
        },
    ];
});
</script>

<template>
  <KpiSummaryStrip
    variant="embedded"
    aria-label="KPI kết quả tuần"
    eyebrow="Thống kê"
    heading="Kết quả hoàn thành trong tuần"
    hint="Số liệu từ task Sprint — giá trị lấy story points và giờ công"
    :cards="cards"
    :progress-denominator="100"
    grid-class="grid-cols-2 sm:grid-cols-3 lg:grid-cols-6"
  />
</template>
