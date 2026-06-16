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
    const hasGrade = committed > 0 && s.grade;

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
            sub: committed ? `${rate}% cam kết đạt` : 'Chưa có cam kết',
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
            value: hasGrade ? s.grade : 'Chưa có',
            tone: 'amber',
            icon: 'star',
            sub: hasGrade ? 'Theo thang S–D' : 'Cần có cam kết trong kỳ',
            interactive: false,
        },
    ];
});
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê audit nhân sự"
    eyebrow="Thống kê"
    heading="Tóm tắt audit theo kỳ"
    hint="Các chỉ số theo thành viên và kỳ đang chọn"
    :cards="cards"
    active-key=""
    :progress-denominator="summary.committed ?? 0"
  />
</template>
