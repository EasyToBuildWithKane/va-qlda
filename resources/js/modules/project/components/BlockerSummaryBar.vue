<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => {
    if (props.filters.recheck_pending) return 'recheck';
    if (props.filters.status === 'resolved') return 'resolved';
    if (props.filters.severity === 'critical') return 'critical';
    if (props.filters.status === 'active') return 'open';
    return '';
});

const cards = computed(() => {
    const s = props.summary;
    const open = s.open ?? 0;
    const totalOpen = open || 1;

    return [
        {
            key: 'open',
            label: 'Đang mở',
            value: open,
            tone: 'amber',
            icon: 'blockers',
            sub: 'Vướng mắc chưa đóng',
            interactive: true,
            payload: { status: 'active', severity: '', recheck_pending: '' },
        },
        {
            key: 'critical',
            label: 'Nghiêm trọng',
            value: s.critical ?? 0,
            tone: 'rose',
            icon: 'alert',
            sub: open ? `${Math.round(((s.critical ?? 0) / totalOpen) * 100)}% đang mở` : 'Bấm để lọc',
            progress: open ? Math.round(((s.critical ?? 0) / totalOpen) * 100) : null,
            interactive: true,
            payload: { status: 'active', severity: 'critical', recheck_pending: '' },
        },
        {
            key: 'resolved',
            label: 'Đã giải quyết',
            value: s.resolved ?? 0,
            tone: 'emerald',
            icon: 'done',
            sub: 'Trạng thái đã xử lý',
            interactive: true,
            payload: { status: 'resolved', severity: '', recheck_pending: '' },
        },
        {
            key: 'recheck',
            label: 'Chờ kiểm tra',
            value: s.recheck_pending ?? 0,
            tone: 'violet',
            icon: 'review-reports',
            sub: 'Cần xác nhận lại',
            interactive: true,
            payload: { status: '', severity: '', recheck_pending: '1' },
        },
    ];
});

function onSelect(card) {
    if (card.payload) emit('quick-filter', card.payload);
}
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê vướng mắc"
    heading="Tổng quan vướng mắc hệ thống"
    hint="Thẻ có viền nét đứt — bấm để lọc nhanh danh sách"
    grid-class="grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-4"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.open ?? 0"
    @select="onSelect"
  />
</template>
