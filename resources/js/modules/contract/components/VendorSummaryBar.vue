<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import { formatMoneyShort } from '@/modules/contract/composables/useContractFormat.js';

const props = defineProps({
    summary: { type: Object, required: true },
});

const cards = computed(() => {
    const s = props.summary;
    const total = s.total ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    return [
        {
            key: 'total',
            label: 'Tổng nhà cung cấp',
            value: total,
            tone: 'brand',
            icon: 'vendor',
            sub: total ? 'Đã đăng ký' : 'Chưa có NCC',
        },
        {
            key: 'with_contracts',
            label: 'Đang hợp tác',
            value: s.with_contracts ?? 0,
            tone: 'emerald',
            icon: 'done',
            sub: total ? `${pct(s.with_contracts ?? 0)}% có hợp đồng` : '—',
            progress: pct(s.with_contracts ?? 0),
        },
        {
            key: 'contracts',
            label: 'Tổng hợp đồng',
            value: s.contracts ?? 0,
            tone: 'sky',
            icon: 'documents',
            sub: 'Trên toàn bộ NCC',
        },
        {
            key: 'annual_cost',
            label: 'Chi phí năm',
            value: formatMoneyShort(s.annual_cost ?? 0),
            tone: 'violet',
            icon: 'money',
            sub: 'Tổng chi phí theo NCC',
        },
        {
            key: 'avg_score',
            label: 'Điểm TB đánh giá',
            value: s.avg_score != null ? `${s.avg_score}/10` : '—',
            tone: 'amber',
            icon: 'performance',
            sub: total ? `${s.reviewed ?? 0} NCC đã đánh giá` : '—',
        },
        {
            key: 'low_score',
            label: 'NCC chấm < 7',
            value: s.low_score ?? 0,
            tone: 'rose',
            icon: 'alert',
            sub: 'Cần xem xét lại hợp tác',
        },
    ];
});
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê nhà cung cấp"
    heading="Tổng quan nhà cung cấp"
    grid-class="grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6"
    :cards="cards"
    :progress-denominator="summary.total ?? 0"
  />
</template>
