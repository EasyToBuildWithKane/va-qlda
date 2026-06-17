<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import { formatMoneyShort } from '@/modules/contract/composables/useContractFormat.js';

const props = defineProps({
    groups: { type: Array, default: () => [] },
});

function listOf(group) {
    const c = group.contracts;
    if (Array.isArray(c)) return c;
    if (c?.data && Array.isArray(c.data)) return c.data;
    if (c && typeof c === 'object') return Object.values(c);
    return [];
}

const allContracts = computed(() => props.groups.flatMap(listOf));

const total = computed(() => allContracts.value.length);
const critical = computed(() => allContracts.value.filter((c) => (c.days_until_expiry ?? 99) <= 7).length);
const renewValue = computed(() => allContracts.value.reduce(
    (s, c) => s + Number(c.annual_cost_resolved ?? c.annual_cost ?? 0), 0,
));

// Dải KPI tổng quan — KHÔNG lặp lại các cột mốc 7/30/60/90 bên dưới.
const cards = computed(() => [
    {
        key: 'total',
        label: 'Sắp đến hạn',
        value: total.value,
        tone: 'brand',
        icon: 'renewal',
        sub: total.value ? 'Trong cửa sổ nhắc gia hạn' : 'Không có hợp đồng',
    },
    {
        key: 'critical',
        label: 'Khẩn cấp ≤ 7 ngày',
        value: critical.value,
        tone: 'rose',
        icon: 'alert',
        sub: critical.value ? 'Cần xử lý ngay' : 'Không có',
    },
    {
        key: 'value',
        label: 'Giá trị gia hạn',
        value: formatMoneyShort(renewValue.value),
        tone: 'violet',
        icon: 'budget',
        sub: 'Tổng chi phí năm các HĐ',
    },
]);
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê gia hạn hợp đồng"
    heading="Tổng quan hợp đồng sắp đến hạn"
    hint="Chi tiết theo mốc 7 / 30 / 60 / 90 ngày ở bên dưới"
    grid-class="grid-cols-1 gap-3 sm:grid-cols-3"
    :cards="cards"
  />
</template>
