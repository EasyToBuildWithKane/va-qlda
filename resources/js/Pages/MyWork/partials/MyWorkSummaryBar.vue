<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    activeHighlight: { type: String, default: '' },
    heading: { type: String, default: 'Tổng quan việc của tôi' },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => {
    const h = props.activeHighlight;
    if (['overdue', 'today', 'inProgress', 'open'].includes(h)) return h;
    if (!h) return 'open';
    return '';
});

const cards = computed(() => {
    const s = props.summary;
    const open = s.open ?? 0;
    const pct = (n) => (open > 0 ? Math.round((n / open) * 100) : 0);

    return [
        {
            key: 'open',
            label: 'Đang mở',
            value: open,
            tone: 'brand',
            icon: 'task',
            sub: open ? 'Toàn bộ việc chưa hoàn thành' : 'Không có việc đang mở',
            interactive: true,
            payload: { highlight: 'open' },
        },
        {
            key: 'overdue',
            label: 'Quá hạn',
            value: s.overdue ?? 0,
            tone: 'rose',
            icon: 'alert',
            sub: open ? `${pct(s.overdue ?? 0)}% việc mở` : 'Bấm để lọc',
            progress: pct(s.overdue ?? 0),
            interactive: true,
            payload: { highlight: 'overdue' },
        },
        {
            key: 'today',
            label: 'Đến hạn hôm nay',
            value: s.dueToday ?? 0,
            tone: 'amber',
            icon: 'calendar-clock',
            sub: open ? `${pct(s.dueToday ?? 0)}% việc mở` : 'Bấm để lọc',
            progress: pct(s.dueToday ?? 0),
            interactive: true,
            payload: { highlight: 'today' },
        },
        {
            key: 'inProgress',
            label: 'Đang làm',
            value: s.inProgress ?? 0,
            tone: 'sky',
            icon: 'sprint',
            sub: open ? `${pct(s.inProgress ?? 0)}% việc mở` : 'Bấm để lọc',
            progress: pct(s.inProgress ?? 0),
            interactive: true,
            payload: { highlight: 'inProgress' },
        },
        {
            key: 'hours',
            label: 'Giờ log hôm nay',
            value: s.hoursLoggedToday ?? 0,
            tone: 'emerald',
            icon: 'worklog',
            sub: 'Tổng giờ ghi nhận trong ngày',
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
    aria-label="Thống kê việc được giao"
    eyebrow="Thống kê"
    :heading="heading"
    hint="Thẻ viền nét đứt — bấm lọc nhanh theo nhóm thời gian"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.open ?? 0"
    @select="onSelect"
  />
</template>
