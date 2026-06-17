<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import { formatMoneyShort } from '@/modules/contract/composables/useContractFormat.js';

const props = defineProps({
    summary: { type: Object, required: true },
});

const cards = computed(() => {
    const s = props.summary;
    const contracts = s.contracts ?? 0;
    const vendors = s.vendors ?? 0;
    const annual = Number(s.annual_cost) || 0;
    const lifecycle = Number(s.lifecycle_cost) || 0;
    const annualShare = lifecycle > 0 ? Math.min(100, Math.round((annual / lifecycle) * 100)) : 0;

    return [
        {
            key: 'contracts',
            label: 'Tổng hợp đồng',
            value: contracts,
            tone: 'brand',
            icon: 'documents',
            sub: contracts ? 'Toàn bộ hợp đồng trong báo cáo' : 'Chưa có hợp đồng',
            interactive: false,
        },
        {
            key: 'vendors',
            label: 'Nhà cung cấp',
            value: vendors,
            tone: 'emerald',
            icon: 'vendor',
            sub: contracts ? `${vendors} dòng phân tích theo NCC` : 'Chưa có NCC',
            interactive: false,
        },
        {
            key: 'annual',
            label: 'Tổng chi phí năm',
            value: formatMoneyShort(annual),
            tone: 'violet',
            icon: 'money',
            sub: 'Cộng dồn annual_cost',
            interactive: false,
        },
        {
            key: 'lifecycle',
            label: 'Chi phí vòng đời',
            value: formatMoneyShort(lifecycle),
            tone: 'sky',
            icon: 'cost',
            sub: lifecycle
                ? `Chi phí năm ≈ ${annualShare}% vòng đời`
                : 'Cộng dồn lifecycle_cost',
            progress: lifecycle > 0 ? annualShare : null,
            interactive: false,
        },
    ];
});
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê báo cáo hợp đồng"
    heading="Tổng quan báo cáo hợp đồng"
    hint="Chỉ số tổng hợp — không đổi theo chiều phân tích bảng"
    :cards="cards"
    :progress-denominator="(summary.contracts ?? 0) > 0 ? summary.contracts : (Number(summary.lifecycle_cost) > 0 ? 1 : 0)"
    grid-class="grid-cols-2 sm:grid-cols-2 lg:grid-cols-4"
  />
</template>
