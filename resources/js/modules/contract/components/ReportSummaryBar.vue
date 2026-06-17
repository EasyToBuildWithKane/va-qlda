<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import { formatMoneyShort } from '@/modules/contract/composables/useContractFormat.js';

const props = defineProps({
    report: { type: Object, required: true },
});

// byVendor bao trùm toàn bộ hợp đồng (kể cả "Chưa gán NCC") → dùng làm gốc tổng.
const base = computed(() => props.report?.byVendor ?? []);

const cards = computed(() => {
    const rows = base.value;
    const contracts = rows.reduce((s, r) => s + (r.count || 0), 0);
    const annual = rows.reduce((s, r) => s + (Number(r.annual_cost) || 0), 0);
    const lifecycle = rows.reduce((s, r) => s + (Number(r.lifecycle_cost) || 0), 0);

    return [
        {
            key: 'contracts',
            label: 'Tổng hợp đồng',
            value: contracts,
            tone: 'brand',
            icon: 'documents',
            sub: 'Trong báo cáo',
        },
        {
            key: 'vendors',
            label: 'Nhà cung cấp',
            value: rows.length,
            tone: 'emerald',
            icon: 'vendor',
            sub: 'Số NCC có hợp đồng',
        },
        {
            key: 'annual',
            label: 'Tổng chi phí năm',
            value: formatMoneyShort(annual),
            tone: 'violet',
            icon: 'money',
            sub: 'Cộng dồn annual_cost',
        },
        {
            key: 'lifecycle',
            label: 'Chi phí vòng đời',
            value: formatMoneyShort(lifecycle),
            tone: 'sky',
            icon: 'cost',
            sub: 'Cộng dồn lifecycle_cost',
        },
    ];
});
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê báo cáo hợp đồng"
    heading="Tổng quan báo cáo hợp đồng"
    :cards="cards"
    grid-class="grid-cols-2 sm:grid-cols-2 lg:grid-cols-4"
  />
</template>
