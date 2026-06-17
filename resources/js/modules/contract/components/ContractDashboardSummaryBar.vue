<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import { formatVndDisplay } from '../composables/useContractFormat.js';

const props = defineProps({
    kpis: { type: Array, default: () => [] },
    period: { type: String, default: 'year' },
});

const META = {
    total_contracts: { tone: 'brand', icon: 'documents', sub: 'Tổng hồ sơ đang quản lý' },
    maintenance: { tone: 'sky', icon: 'money' },
    total_value: { tone: 'violet', icon: 'budget' },
    expiring_30: { tone: 'amber', icon: 'clock', sub: 'Cần lên kế hoạch gia hạn' },
    deployment: { tone: 'emerald', icon: 'cost' },
    low_score: { tone: 'rose', icon: 'performance', sub: 'Đánh giá gần nhất dưới 7' },
    cashflow_year: { tone: 'sky', icon: 'budget' },
    overdue_15: { tone: 'rose', icon: 'alert', sub: 'Quá hạn hoặc còn dưới 15 ngày' },
};

const PERIOD_LABEL = { month: 'tháng này', quarter: 'quý này', year: 'năm nay' };

const cards = computed(() => props.kpis.map((k) => {
    const meta = META[k.key] ?? { tone: 'slate', icon: 'documents' };
    if (k.format === 'currency') {
        const d = formatVndDisplay(k.value);
        return {
            key: k.key,
            label: k.label,
            value: d.primary,
            tone: meta.tone,
            icon: meta.icon,
            sub: d.secondary
                ? `${d.secondary.replace(/[()]/g, '')}${k.key === 'maintenance' ? ` · ${PERIOD_LABEL[props.period]}` : ''}`
                : meta.sub,
        };
    }
    return {
        key: k.key,
        label: k.label,
        value: k.value,
        tone: meta.tone,
        icon: meta.icon,
        sub: meta.sub,
    };
}));
</script>

<template>
  <KpiSummaryStrip
    aria-label="Chỉ số điều hành hợp đồng"
    eyebrow="Chỉ số chính"
    heading="Tổng quan vòng đời & chi phí hợp đồng"
    grid-class="grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
    :cards="cards"
  />
</template>
