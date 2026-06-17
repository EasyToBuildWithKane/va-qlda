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
            key: 'without_contracts',
            label: 'Chưa có hợp đồng',
            value: s.without_contracts ?? 0,
            tone: 'slate',
            icon: 'vendor',
            sub: 'NCC tiềm năng / dự phòng',
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
            sub: 'Tổng annual_cost theo NCC',
        },
    ];
});
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê nhà cung cấp"
    heading="Tổng quan nhà cung cấp"
    :cards="cards"
    :progress-denominator="summary.total ?? 0"
  />
</template>
