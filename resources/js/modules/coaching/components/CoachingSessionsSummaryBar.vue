<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import { hours as fmtHours } from '@/composables/useFormat';

const props = defineProps({
    summary: { type: Object, required: true },
    activeStatus: { type: String, default: '' },
});

const emit = defineEmits(['filter-status']);

const activeKey = computed(() => {
    if (props.activeStatus === 'completed') return 'completed';
    if (props.activeStatus === 'pending') return 'pending';
    if (!props.activeStatus) return 'total';
    return props.activeStatus;
});

const cards = computed(() => {
    const s = props.summary;
    const total = s.total ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    return [
        {
            key: 'total',
            label: 'Tổng buổi',
            value: total,
            tone: 'brand',
            icon: 'weekly',
            sub: total ? `${s.courses ?? 0} khóa · theo bộ lọc` : 'Chưa có buổi phù hợp',
            interactive: true,
            payload: '',
        },
        {
            key: 'courses',
            label: 'Khóa học',
            value: s.courses ?? 0,
            tone: 'sky',
            icon: 'knowledge',
            sub: 'Số khóa có buổi trong kết quả',
            interactive: false,
        },
        {
            key: 'hours',
            label: 'Tổng giờ',
            value: fmtHours(s.hours_total ?? 0),
            tone: 'violet',
            icon: 'clock',
            sub: 'Giờ ghi nhận trên buổi lọc',
            interactive: false,
        },
        {
            key: 'completed',
            label: 'Hoàn thành',
            value: s.completed ?? 0,
            tone: 'emerald',
            icon: 'done',
            sub: total ? `${pct(s.completed ?? 0)}% tổng buổi` : 'Bấm để lọc',
            progress: pct(s.completed ?? 0),
            interactive: true,
            payload: 'completed',
        },
        {
            key: 'pending',
            label: 'Chưa học',
            value: s.pending ?? 0,
            tone: 'slate',
            icon: 'task',
            sub: total ? `${pct(s.pending ?? 0)}% tổng buổi` : 'Bấm để lọc',
            progress: pct(s.pending ?? 0),
            interactive: true,
            payload: 'pending',
        },
    ];
});

function onSelect(card) {
    if (card.payload !== undefined && card.interactive) {
        emit('filter-status', card.payload);
    }
}
</script>

<template>
  <KpiSummaryStrip
    variant="embedded"
    aria-label="Thống kê buổi học theo bộ lọc"
    heading="Chỉ số theo bộ lọc hiện tại"
    hint="Thẻ có viền nét đứt — bấm để lọc nhanh"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.total ?? 0"
    @select="onSelect"
  />
</template>
