<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import { EMPTY_LABELS } from '@/shared/utils/emptyDisplay.js';

const props = defineProps({
    summary: { type: Object, required: true },
    activeScoreBand: { type: String, default: '' },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => {
    if (props.activeScoreBand === 'low') return 'low';
    if (props.activeScoreBand === 'mid') return 'mid';
    if (props.activeScoreBand === 'high') return 'high';
    if (!props.activeScoreBand) return 'total';
    return '';
});

const cards = computed(() => {
    const s = props.summary;
    const total = s.total ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    return [
        {
            key: 'total',
            label: 'Lần đánh giá',
            value: total,
            tone: 'brand',
            icon: 'performance',
            sub: total ? 'Toàn bộ nhật ký' : 'Chưa có bản ghi',
            interactive: true,
            payload: { scoreBand: '' },
        },
        {
            key: 'avg',
            label: 'Điểm trung bình',
            value: s.avg_score != null ? `${s.avg_score}/10` : 'Chưa có điểm TB',
            valueKind: s.avg_score != null ? 'metric' : 'text',
            tone: 'amber',
            icon: 'star',
            sub: s.scored_count ? `Trên ${s.scored_count} lần có điểm` : EMPTY_LABELS.generic,
            interactive: false,
        },
        {
            key: 'high',
            label: 'Điểm ≥ 8,5',
            value: s.high_count ?? 0,
            tone: 'emerald',
            icon: 'done',
            sub: total ? `${pct(s.high_count ?? 0)}% tổng lần` : 'Bấm để lọc',
            progress: pct(s.high_count ?? 0),
            interactive: true,
            payload: { scoreBand: 'high' },
        },
        {
            key: 'low',
            label: 'Điểm dưới 7',
            value: s.low_count ?? 0,
            tone: 'rose',
            icon: 'alert',
            sub: total ? `${pct(s.low_count ?? 0)}% tổng lần` : 'Bấm để lọc',
            progress: pct(s.low_count ?? 0),
            interactive: true,
            payload: { scoreBand: 'low' },
        },
    ];
});

function onSelect(card) {
    if (card.payload) emit('quick-filter', card.payload);
}
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê nhật ký đánh giá NCC"
    heading="Nhật ký đánh giá"
    hint="Thẻ viền nét đứt — bấm để lọc nhanh theo mức điểm"
    variant="standalone"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.total ?? 0"
    grid-class="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-4"
    @select="onSelect"
  />
</template>

<style scoped>
@import '@/shared/styles/kpi-summary-strip.css';
</style>
