<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    activeStatus: { type: String, default: '' },
    activeOverdue: { type: Boolean, default: false },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => {
    if (props.activeStatus === 'completed') return 'completed';
    if (props.activeStatus === 'active') return 'active';
    if (!props.activeStatus && !props.activeOverdue) return 'total';
    return '';
});

const cards = computed(() => {
    const s = props.summary;
    const total = s.total ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    return [
        {
            key: 'total',
            label: 'Tổng dự án',
            value: total,
            tone: 'brand',
            icon: 'projects',
            sub: total ? 'Toàn danh mục' : 'Chưa có dự án',
            interactive: true,
            payload: { status: '', overdue: false },
        },
        {
            key: 'active',
            label: 'Đang thực hiện',
            value: s.active ?? 0,
            tone: 'sky',
            icon: 'sprint',
            sub: total ? `${pct(s.active ?? 0)}% danh mục` : 'Bấm để lọc',
            progress: pct(s.active ?? 0),
            interactive: true,
            payload: { status: 'active', overdue: false },
        },
        {
            key: 'completed',
            label: 'Hoàn thành',
            value: s.completed ?? 0,
            tone: 'emerald',
            icon: 'done',
            sub: total ? `${pct(s.completed ?? 0)}% danh mục` : 'Bấm để lọc',
            progress: pct(s.completed ?? 0),
            interactive: true,
            payload: { status: 'completed', overdue: false },
        },
        {
            key: 'overdue',
            label: 'Trễ hạn',
            value: s.overdue ?? 0,
            tone: 'rose',
            icon: 'flag',
            sub: total ? `${pct(s.overdue ?? 0)}% quá hạn` : 'Chỉ số tổng hợp',
            progress: pct(s.overdue ?? 0),
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
    aria-label="Thống kê danh mục dự án"
    heading="Tổng quan portfolio dự án"
    hint="Thẻ có viền nét đứt — bấm để lọc nhanh danh sách"
    grid-class="grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-4"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.total ?? 0"
    @select="onSelect"
  />
</template>
