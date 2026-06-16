<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({
            committed: 0,
            done: 0,
            commitmentRate: 0,
            avgScore: 0,
            grade: 'D',
        }),
    },
});

const cards = computed(() => {
    const s = props.summary;
    const committed = s.committed ?? 0;
    const rate = s.commitmentRate ?? 0;

    return [
        {
            key: 'committed',
            label: 'Cam kết',
            value: committed,
            tone: 'brand',
            icon: 'task',
            sub: 'Task trong kỳ audit',
            interactive: false,
        },
        {
            key: 'done',
            label: 'Hoàn thành',
            value: s.done ?? 0,
            tone: 'emerald',
            icon: 'done',
            sub: committed ? `${rate}% cam kết đạt` : '—',
            progress: rate,
            interactive: false,
        },
        {
            key: 'commitment',
            label: 'Tỷ lệ cam kết',
            value: `${rate}%`,
            tone: 'sky',
            icon: 'performance',
            sub: 'Kế hoạch vs kết quả',
            progress: rate,
            interactive: false,
        },
        {
            key: 'avg_score',
            label: 'Hiệu suất TB',
            value: s.avgScore ?? 0,
            suffix: '%',
            tone: 'violet',
            icon: 'talent-score',
            sub: 'Trung bình các tuần',
            interactive: false,
        },
        {
            key: 'grade',
            label: 'Xếp loại',
            value: s.grade ?? '—',
            tone: 'amber',
            icon: 'star',
            sub: 'Theo thang S–D',
            interactive: false,
        },
    ];
});
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê audit nhân sự"
    heading="Tóm tắt audit theo kỳ"
    hint="Timeline chi tiết theo từng tuần bên dưới"
    :cards="cards"
    active-key=""
    :progress-denominator="summary.committed ?? 0"
  />
</template>
