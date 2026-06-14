<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    stats: { type: Object, required: true },
});

const cards = computed(() => {
    const s = props.stats;
    const total = s.total || 1;
    return [
        {
            key: 'total',
            label: 'Tổng sự kiện',
            value: (s.total ?? 0).toLocaleString('vi-VN'),
            tone: 'brand',
            icon: 'notifications',
            sub: 'Toàn hệ thống thông báo',
            interactive: false,
        },
        {
            key: 'unread',
            label: 'Chưa đọc',
            value: (s.unread ?? 0).toLocaleString('vi-VN'),
            tone: 'sky',
            icon: 'notifications',
            sub: total ? `${Math.round(((s.unread ?? 0) / total) * 100)}% tổng` : '',
            progress: total ? Math.round(((s.unread ?? 0) / total) * 100) : null,
            interactive: false,
        },
        {
            key: 'critical',
            label: 'Critical',
            value: s.critical ?? 0,
            tone: 'rose',
            icon: 'alert',
            sub: (s.critical ?? 0) > 0 ? 'Cần xử lý ưu tiên' : 'Không có critical',
            interactive: false,
        },
        {
            key: 'high',
            label: 'High',
            value: s.high ?? 0,
            tone: 'amber',
            icon: 'flag',
            sub: 'Mức ưu tiên cao',
            interactive: false,
        },
        {
            key: 'today',
            label: 'Hôm nay',
            value: s.today ?? 0,
            tone: 'violet',
            icon: 'calendar',
            sub: 'Sự kiện phát sinh trong ngày',
            interactive: false,
        },
        {
            key: 'attention',
            label: 'Cần chú ý',
            value: s.unread_critical ?? 0,
            tone: 'rose',
            icon: 'blockers',
            sub: 'Critical chưa đọc',
            interactive: false,
        },
    ];
});
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê trung tâm thông báo"
    heading="Tổng quan vận hành thông báo"
    hint=""
    grid-class="grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6"
    :cards="cards"
    :progress-denominator="stats.total ?? 0"
  />
</template>
