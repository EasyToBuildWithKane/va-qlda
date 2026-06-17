<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import { formatMoneyShort } from '@/modules/contract/composables/useContractFormat.js';

const props = defineProps({
    cost: { type: Object, required: true },
});

const cards = computed(() => {
    const c = props.cost;
    const forecast = c.forecast ?? {};
    const yearTotal = Array.isArray(c.byQuarter?.data)
        ? c.byQuarter.data.reduce((s, n) => s + (Number(n) || 0), 0)
        : 0;

    return [
        {
            key: 'this_year',
            label: `Chi phí năm ${c.this_year ?? ''}`,
            value: formatMoneyShort(yearTotal),
            tone: 'brand',
            icon: 'money',
            sub: 'Tổng 4 quý năm nay',
        },
        {
            key: 'forecast',
            label: 'Dự báo năm tới',
            value: formatMoneyShort(forecast.total ?? 0),
            tone: 'violet',
            icon: 'cost',
            sub: 'Ngân sách dự kiến',
        },
        {
            key: 'auto_renew',
            label: 'Tự động gia hạn',
            value: formatMoneyShort(forecast.auto_renew ?? 0),
            tone: 'emerald',
            icon: 'renewal',
            sub: 'Không cần xét duyệt',
        },
        {
            key: 'manual',
            label: 'Cần xét gia hạn',
            value: formatMoneyShort(forecast.manual ?? 0),
            tone: 'amber',
            icon: 'clock',
            sub: 'Cần quyết định thủ công',
        },
    ];
});
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê chi phí hợp đồng"
    heading="Tổng quan chi phí & dự báo ngân sách"
    :cards="cards"
    grid-class="grid-cols-2 sm:grid-cols-2 lg:grid-cols-4"
  />
</template>
