<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    mode: {
        type: String,
        default: 'detail',
        validator: (v) => ['list', 'detail'].includes(v),
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    activeKpi: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => props.activeKpi || '');

const cards = computed(() => {
    if (props.mode === 'list') {
        const s = props.summary;
        const card = (key, label, value, tone, icon, sub, filterKey, progress = null) => ({
            key,
            label,
            value,
            tone,
            icon,
            sub,
            interactive: Boolean(filterKey),
            payload: filterKey ? { kpi: filterKey } : null,
            progress,
        });

        return [
            card('total', 'Nhân sự', s.total ?? 0, 'brand', 'members', 'Trong phạm vi lọc'),
            card(
                'avg_commitment',
                'TB cam kết đạt',
                `${s.avgCommitmentRate ?? 0}%`,
                'sky',
                'performance',
                'Trung bình có cam kết',
                null,
                s.avgCommitmentRate ?? 0,
            ),
            card(
                'avg_score',
                'Hiệu suất TB',
                s.avgScore ?? 0,
                'violet',
                'talent-score',
                'Theo kỳ đang chọn',
            ),
            card('excellent', 'Xuất sắc', s.excellentCount ?? 0, 'emerald', 'star', 'Xếp loại S hoặc A', 'excellent'),
            card(
                'needs_improvement',
                'Cần cải thiện',
                s.needsImprovementCount ?? 0,
                'amber',
                'alert',
                'Xếp loại C hoặc D',
                'needs_improvement',
            ),
        ];
    }

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

const stripHeading = computed(() => (
    props.mode === 'list' ? 'Tóm tắt audit theo phạm vi' : 'Tóm tắt audit theo kỳ'
));

const stripHint = computed(() => (
    props.mode === 'list'
        ? 'Thẻ viền nét đứt — bấm lọc nhanh xuất sắc / cần cải thiện'
        : 'Các chỉ số theo thành viên và kỳ đang chọn'
));

function onSelect(card) {
    if (card.payload && 'kpi' in card.payload) {
        const next = props.activeKpi === card.payload.kpi ? '' : card.payload.kpi;
        emit('quick-filter', { kpi: next });
    }
}
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê audit nhân sự"
    eyebrow="Thống kê"
    :heading="stripHeading"
    :hint="stripHint"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="mode === 'detail' ? (summary.committed ?? 0) : (summary.total ?? 0)"
    @select="onSelect"
  />
</template>

<style scoped>
@import '@/shared/styles/kpi-summary-strip.css';
</style>
