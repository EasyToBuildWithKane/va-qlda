<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    stats: { type: Object, required: true },
    activeKpi: { type: String, default: '' },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => props.activeKpi || '');

const cards = computed(() => {
    const s = props.stats;
    const card = (key, label, value, tone, icon, sub, interactive = false) => ({
        key,
        label,
        value: typeof value === 'number' ? value.toLocaleString('vi-VN') : value,
        tone,
        icon,
        sub,
        interactive,
        payload: interactive ? { kpi: key } : null,
    });

    return [
        card('total', 'Tổng sự kiện', s.total ?? 0, 'brand', 'shield', 'Sổ cái truy vết hợp nhất', true),
        card('today', 'Hôm nay', s.today ?? 0, 'sky', 'calendar', 'Phát sinh trong ngày', true),
        card('week', '7 ngày qua', s.week ?? 0, 'violet', 'overview', 'Tuần gần nhất', true),
        card(
            'login_failed',
            'Đăng nhập lỗi',
            s.login_failed ?? 0,
            'rose',
            'flag',
            '7 ngày qua · auth.login_failed',
            true,
        ),
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
    aria-label="Thống kê nhật ký truy vết"
    heading="Tổng quan nhật ký truy vết"
    hint="Thẻ viền nét đứt — bấm lọc nhanh"
    grid-class="grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-4"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="stats.total ?? 0"
    @select="onSelect"
  />
</template>
