<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({
            committed: 0,
            done: 0,
            in_progress: 0,
            overdue: 0,
            completion_rate: 0,
            on_time_rate: 0,
            avg_score: 0,
            grade: 'D',
        }),
    },
});

const cards = computed(() => {
    const s = props.summary;
    const committed = s.committed ?? 0;
    const done = s.done ?? 0;
    const pctDone = s.completion_rate ?? 0;
    const pctOnTime = s.on_time_rate ?? 0;

    return [
        {
            key: 'committed',
            label: 'Task được giao',
            value: committed,
            tone: 'brand',
            icon: 'task',
            sub: committed ? 'Trong kỳ đang chọn' : 'Chưa có dữ liệu',
            interactive: false,
        },
        {
            key: 'done',
            label: 'Hoàn thành',
            value: done,
            tone: 'emerald',
            icon: 'done',
            sub: committed ? `${pctDone}% tỷ lệ hoàn thành` : 'Chưa có task hoàn thành',
            progress: pctDone,
            interactive: false,
        },
        {
            key: 'in_progress',
            label: 'Đang thực hiện',
            value: s.in_progress ?? 0,
            tone: 'sky',
            icon: 'worklog',
            sub: 'Snapshot hiện tại',
            interactive: false,
        },
        {
            key: 'on_time',
            label: 'Đúng hạn',
            value: `${pctOnTime}%`,
            tone: 'violet',
            icon: 'target',
            sub: done ? 'Trên task đã xong' : 'Chưa có task đã xong',
            progress: pctOnTime,
            interactive: false,
        },
        {
            key: 'avg_score',
            label: 'Hiệu suất TB',
            value: s.avg_score ?? 0,
            suffix: '%',
            tone: 'amber',
            icon: 'talent-score',
            sub: s.grade ? `Xếp loại ${s.grade}` : 'Nhóm trong phạm vi',
            interactive: false,
        },
    ];
});
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê hiệu suất"
    heading="Tổng quan hiệu suất theo bộ lọc"
    hint="Các chỉ số đồng bộ với bộ lọc phía dưới"
    :cards="cards"
    active-key=""
    :progress-denominator="summary.committed ?? 0"
  />
</template>
