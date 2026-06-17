<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    activeLevel: { type: String, default: 'all' },
});

const emit = defineEmits(['select-level']);

const cards = computed(() => {
    const s = props.summary;
    const total = s.total ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    return [
        {
            key: 'all',
            label: 'Tất cả cảnh báo',
            value: total,
            tone: 'brand',
            icon: 'alert',
            sub: total ? 'Cần theo dõi' : 'Không có cảnh báo 🎉',
            interactive: true,
        },
        {
            key: 'critical',
            label: 'Nghiêm trọng',
            value: s.critical ?? 0,
            tone: 'rose',
            icon: 'alert',
            sub: total ? `${pct(s.critical ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.critical ?? 0),
            interactive: true,
        },
        {
            key: 'high',
            label: 'Cao',
            value: s.high ?? 0,
            tone: 'amber',
            icon: 'clock',
            sub: total ? `${pct(s.high ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.high ?? 0),
            interactive: true,
        },
        {
            key: 'medium',
            label: 'Trung bình',
            value: s.medium ?? 0,
            tone: 'sky',
            icon: 'info',
            sub: total ? `${pct(s.medium ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.medium ?? 0),
            interactive: true,
        },
        {
            key: 'low',
            label: 'Thấp',
            value: s.low ?? 0,
            tone: 'slate',
            icon: 'info',
            sub: total ? `${pct(s.low ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.low ?? 0),
            interactive: true,
        },
    ];
});

function onSelect(card) {
    emit('select-level', card.key);
}
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê cảnh báo hợp đồng"
    heading="Tổng quan cảnh báo theo mức độ"
    hint="Thẻ có viền nét đứt — bấm để lọc theo mức độ"
    :cards="cards"
    :active-key="activeLevel"
    :progress-denominator="summary.total ?? 0"
    @select="onSelect"
  />
</template>
