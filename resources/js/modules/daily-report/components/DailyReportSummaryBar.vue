<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

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

const activeKey = computed(() => {
    if (props.lateFilter) return 'late';
    if (props.statusFilter === 'reviewed') return 'reviewed';
    if (props.statusFilter === 'submitted') return 'submitted';
    if (props.statusFilter === 'draft') return 'draft';
    if (!props.statusFilter && !props.lateFilter) return 'all';
    return '';
});

const cards = computed(() => {
    const s = props.summary;
    const total = s.total ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    return [
        {
            key: 'all',
            label: 'Tổng báo cáo',
            value: total,
            tone: 'brand',
            icon: 'documents',
            sub: `${s.completion_rate ?? 0}% hoàn thành`,
            trend: trendPill('total'),
            interactive: true,
            action: 'status',
            payload: '',
        },
        {
            key: 'reviewed',
            label: 'Đã duyệt',
            value: s.reviewed ?? 0,
            tone: 'emerald',
            icon: 'check-circle',
            trend: trendPill('reviewed'),
            progress: pct(s.reviewed ?? 0),
            interactive: true,
            action: 'status',
            payload: 'reviewed',
        },
        {
            key: 'submitted',
            label: 'Chờ duyệt',
            value: s.submitted ?? 0,
            tone: 'violet',
            icon: 'clock',
            trend: trendPill('submitted'),
            progress: pct(s.submitted ?? 0),
            interactive: true,
            action: 'status',
            payload: 'submitted',
        },
        {
            key: 'draft',
            label: 'Nháp / trả lại',
            value: s.draft ?? 0,
            tone: 'amber',
            icon: 'edit',
            trend: trendPill('draft'),
            progress: pct(s.draft ?? 0),
            interactive: true,
            action: 'status',
            payload: 'draft',
        },
        {
            key: 'late',
            label: 'Nộp trễ',
            value: s.late ?? 0,
            tone: 'rose',
            icon: 'alert',
            trend: trendPill('late', true),
            progress: pct(s.late ?? 0),
            interactive: true,
            action: 'late',
        },
    ];
});

function onSelect(card) {
    if (card.action === 'late') emit('toggle-late');
    else if (card.action === 'status') emit('set-status', card.payload ?? '');
}
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê báo cáo ngày"
    heading="Tổng quan báo cáo theo bộ lọc"
    hint="Thẻ có viền nét đứt — bấm để lọc nhanh danh sách"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.total ?? 0"
    @select="onSelect"
  />
</template>
