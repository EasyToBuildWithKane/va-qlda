<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import ProgressBar from '@/shared/ui/ProgressBar.vue';

const props = defineProps({
    summary: { type: Object, required: true },
});

const emit = defineEmits(['navigate']);

const cards = computed(() => {
    const s = props.summary;
    const tasksTotal = s.tasks_total ?? 0;
    const tasksDone = s.tasks_done ?? 0;
    const taskPct = tasksTotal > 0 ? Math.round((tasksDone / tasksTotal) * 100) : 0;
    const progress = s.progress ?? 0;
    const blockersOpen = s.blockers_open ?? 0;
    const blockersTotal = s.blockers_total ?? 0;

    return [
        {
            key: 'progress',
            label: 'Tiến độ tổng',
            value: `${progress}%`,
            tone: 'brand',
            icon: 'overview',
            sub: progress ? 'Theo trạng thái công việc' : 'Chưa có công việc',
            interactive: false,
            showProgressBar: true,
            progressValue: progress,
        },
        {
            key: 'members',
            label: 'Thành viên',
            value: s.members ?? 0,
            tone: 'violet',
            icon: 'members',
            sub: (s.members ?? 0) ? 'Nhân sự dự án & người gán việc' : 'Chưa có nhân sự',
            interactive: true,
            payload: 'members',
        },
        {
            key: 'tasks',
            label: 'Công việc',
            value: tasksTotal,
            tone: 'sky',
            icon: 'task',
            sub: tasksTotal ? `${tasksDone} hoàn thành · ${taskPct}%` : 'Chưa có công việc',
            progress: taskPct,
            interactive: true,
            payload: 'board',
        },
        {
            key: 'sprints',
            label: 'Sprint',
            value: s.sprints_total ?? 0,
            tone: 'emerald',
            icon: 'sprint',
            sub: (s.sprints_active ?? 0) ? `${s.sprints_active} đang chạy` : 'Bấm để mở workspace',
            interactive: true,
            payload: 'sprints',
        },
        {
            key: 'blockers',
            label: 'Vướng mắc mở',
            value: blockersOpen,
            tone: blockersOpen ? 'rose' : 'slate',
            icon: 'blockers',
            sub: blockersTotal ? `${blockersTotal} tổng cộng` : 'Chưa ghi nhận',
            interactive: true,
            payload: 'blockers',
        },
    ];
});

function onSelect(card) {
    if (card.payload) emit('navigate', card.payload);
}
</script>

<template>
  <KpiSummaryStrip
    variant="embedded"
    dense-values
    aria-label="Thống kê tổng quan dự án"
    heading="Chỉ số dự án"
    hint="Thẻ viền nét đứt — bấm để chuyển tab hoặc xem nhân sự"
    :cards="cards"
    active-key=""
    :progress-denominator="summary.tasks_total ?? 0"
    @select="onSelect"
  >
    <template #footer="{ card }">
      <ProgressBar
        v-if="card.showProgressBar"
        :value="card.progressValue"
        class="mt-2"
      />
    </template>
  </KpiSummaryStrip>
</template>

<style scoped>
@import '@/shared/styles/kpi-summary-strip.css';
</style>
