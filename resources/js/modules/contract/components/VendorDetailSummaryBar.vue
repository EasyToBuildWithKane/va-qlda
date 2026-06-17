<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import { formatMoneyShort } from '@/modules/contract/composables/useContractFormat.js';

const props = defineProps({
    vendor: { type: Object, required: true },
});

const cards = computed(() => {
    const v = props.vendor;
    const contracts = v.contracts_count ?? 0;
    const score = v.review_score;
    const scoreTone = score == null ? 'slate' : (score < 7 ? 'rose' : (score < 8.5 ? 'amber' : 'emerald'));

    return [
        {
            key: 'contracts',
            label: 'Hợp đồng',
            value: contracts,
            tone: 'brand',
            icon: 'documents',
            sub: contracts ? 'Gắn với NCC này' : 'Chưa có hợp đồng',
            interactive: false,
        },
        {
            key: 'annual_cost',
            label: 'Chi phí / năm',
            value: formatMoneyShort(v.total_annual_cost ?? 0),
            valueKind: 'metric',
            tone: 'violet',
            icon: 'money',
            sub: 'Tổng chi phí hợp đồng',
            interactive: false,
        },
        {
            key: 'review_score',
            label: 'Điểm gần nhất',
            value: score != null ? `${score}/10` : 'Chưa đánh giá',
            valueKind: score != null ? 'metric' : 'text',
            tone: scoreTone,
            icon: 'performance',
            sub: score != null && v.is_low_score ? 'Dưới ngưỡng 7 điểm' : 'Theo lần đánh giá mới nhất',
            interactive: false,
        },
        {
            key: 'status',
            label: 'Trạng thái',
            value: v.is_active ? 'Đang hợp tác' : 'Ngừng hoạt động',
            valueKind: 'text',
            tone: v.is_active ? 'emerald' : 'slate',
            icon: 'vendor',
            sub: v.is_active ? 'NCC đang mở' : 'Đã tắt trên hệ thống',
            interactive: false,
        },
    ];
});
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê nhà cung cấp"
    heading="Tổng quan NCC"
    hint="Số liệu tổng hợp theo hợp đồng và đánh giá gần nhất"
    :cards="cards"
    active-key=""
    :progress-denominator="0"
    grid-class="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-4"
  />
</template>

<style scoped>
@import '@/shared/styles/kpi-summary-strip.css';
</style>
