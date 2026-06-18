<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    stats: { type: Object, required: true },
    unreadCount: { type: Number, default: 0 },
    activeKpi: { type: String, default: '' },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => props.activeKpi || '');

const cards = computed(() => {
    const s = props.stats;
    const total = s.total ?? 0;
    const unread = props.unreadCount ?? s.unread ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    return [
        {
            key: 'total',
            label: 'Tổng thông báo',
            value: total.toLocaleString('vi-VN'),
            tone: 'brand',
            icon: 'notifications',
            sub: total ? 'Hộp thư của bạn' : 'Chưa có thông báo',
            interactive: true,
            payload: { kpi: 'total' },
        },
        {
            key: 'unread',
            label: 'Chưa đọc',
            value: unread.toLocaleString('vi-VN'),
            tone: 'sky',
            icon: 'message',
            sub: total ? `${pct(unread)}% chưa mở` : 'Bấm để lọc',
            progress: pct(unread),
            interactive: true,
            payload: { kpi: 'unread' },
        },
        {
            key: 'critical',
            label: 'Quan trọng chưa đọc',
            value: (s.critical ?? 0).toLocaleString('vi-VN'),
            tone: 'rose',
            icon: 'flag',
            sub: (s.critical ?? 0) > 0 ? 'Cần xử lý ưu tiên' : 'Không có mức quan trọng',
            interactive: true,
            payload: { kpi: 'critical' },
        },
        {
            key: 'today',
            label: 'Hôm nay',
            value: (s.today ?? 0).toLocaleString('vi-VN'),
            tone: 'violet',
            icon: 'calendar',
            sub: 'Phát sinh trong ngày',
            interactive: true,
            payload: { kpi: 'today' },
        },
    ];
});

function onSelect(card) {
    if (card?.payload) {
        emit('quick-filter', card.payload);
    }
}
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê hộp thư thông báo"
    heading="Tổng quan thông báo"
    hint="Thẻ viền nét đứt — bấm lọc nhanh"
    grid-class="grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-4"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="stats.total ?? 0"
    @select="onSelect"
  />
</template>
