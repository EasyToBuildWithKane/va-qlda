<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import { formatVndCompact } from '@/modules/aiAccount/utils/formatVnd';

const props = defineProps({
    kpis: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
});

function runRateTrendText(changePercent) {
    if (changePercent == null || Number.isNaN(changePercent)) return null;
    if (changePercent === 0) return { text: 'Không đổi so tháng trước', tone: 'neutral', arrow: '·' };
    const arrow = changePercent > 0 ? '▲' : '▼';
    const abs = Math.abs(changePercent);
    const formatted = Number.isInteger(abs) ? abs : abs.toFixed(1);
    return {
        text: `${formatted}% so tháng trước`,
        tone: changePercent > 0 ? 'bad' : 'good',
        arrow,
    };
}

const cards = computed(() => {
    const k = props.kpis;
    return [
        {
            key: 'usage',
            label: 'TK đang sử dụng',
            value: k.accounts_in_use ?? 0,
            tone: 'brand',
            icon: 'account',
            sub: `${k.usage_rate_percent ?? 0}% tỷ lệ sử dụng`,
            interactive: false,
        },
        {
            key: 'license',
            label: 'Sắp / hết hạn',
            value: k.accounts_expiring_soon ?? 0,
            tone: 'amber',
            icon: 'clock',
            sub: `Đã hết hạn: ${k.accounts_expired ?? 0}`,
            interactive: false,
        },
        {
            key: 'cost-month',
            label: 'Chi phí tháng',
            value: formatVndCompact(k.cost_current_month),
            valueKind: 'money',
            tone: 'sky',
            icon: 'budget',
            sub: `${formatVndCompact(k.avg_cost_per_user)}/người`,
            interactive: false,
        },
        {
            key: 'cost-year',
            label: 'Chi phí năm',
            value: formatVndCompact(k.cost_current_year),
            valueKind: 'money',
            tone: 'violet',
            icon: 'performance',
            sub: `Dự kiến: ${formatVndCompact(k.cost_forecast_year_end)}`,
            interactive: false,
        },
        {
            key: 'budget',
            label: 'Ngân sách đã duyệt',
            value: formatVndCompact(k.budget_approved_total),
            valueKind: 'money',
            tone: 'emerald',
            icon: 'budget',
            sub: `Đã TT: ${formatVndCompact(k.budget_paid_total)} · Dùng: ${formatVndCompact(k.budget_used_total)}`,
            interactive: false,
        },
        {
            key: 'run-rate',
            label: 'Vận hành / tháng',
            value: formatVndCompact(k.monthly_run_rate),
            valueKind: 'money',
            tone: 'slate',
            icon: 'cost',
            sub: 'Chi phí vận hành trung bình',
            trend: runRateTrendText(k.monthly_run_rate_change_percent),
            interactive: false,
        },
    ];
});
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê dashboard AI"
    heading="Chỉ số điều hành AI & chi phí"
    hint=""
    grid-class="grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-3"
    :cards="cards"
    :loading="loading"
    loading-text="Đang tải dashboard…"
  />
</template>
