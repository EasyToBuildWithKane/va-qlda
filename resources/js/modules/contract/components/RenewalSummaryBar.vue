<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    groups: { type: Array, default: () => [] },
});

const DAY_TONE = { 7: 'rose', 30: 'amber', 60: 'sky', 90: 'emerald' };
const DAY_ICON = { 7: 'alert', 30: 'clock', 60: 'clock', 90: 'renewal' };

function countOf(group) {
    const c = group.contracts;
    if (Array.isArray(c)) return c.length;
    if (c?.data && Array.isArray(c.data)) return c.data.length;
    if (c && typeof c === 'object') return Object.values(c).length;
    return 0;
}

const total = computed(() => props.groups.reduce((s, g) => s + countOf(g), 0));

const cards = computed(() => {
    const t = total.value;
    const pct = (n) => (t > 0 ? Math.round((n / t) * 100) : 0);

    const bucketCards = [...props.groups]
        .sort((a, b) => (a.days ?? 0) - (b.days ?? 0))
        .map((g) => {
            const n = countOf(g);
            return {
                key: `d${g.days}`,
                label: g.label ?? `Trong ${g.days} ngày`,
                value: n,
                tone: DAY_TONE[g.days] ?? 'slate',
                icon: DAY_ICON[g.days] ?? 'clock',
                sub: t ? `${pct(n)}% sắp hết hạn` : 'Không có',
                progress: pct(n),
            };
        });

    return [
        {
            key: 'total',
            label: 'Sắp đến hạn',
            value: t,
            tone: 'brand',
            icon: 'renewal',
            sub: t ? 'Trong cửa sổ nhắc gia hạn' : 'Không có hợp đồng',
        },
        ...bucketCards,
    ];
});
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê gia hạn hợp đồng"
    heading="Tổng quan hợp đồng sắp đến hạn"
    :cards="cards"
    :progress-denominator="total"
  />
</template>
