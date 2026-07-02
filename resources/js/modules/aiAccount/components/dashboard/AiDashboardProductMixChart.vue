<script setup>
import { computed, ref } from 'vue';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    Tooltip,
} from 'chart.js';
import { Bar } from 'vue-chartjs';
import AiDashboardChartPanel from '@/modules/aiAccount/components/dashboard/AiDashboardChartPanel.vue';
import {
    AI_CHART_BRAND,
    AI_CHART_SKY,
    baseChartPlugins,
    countTooltipLabel,
    moneyTooltipLabel,
} from '@/modules/aiAccount/components/dashboard/aiDashboardChartTheme';
import { formatVndCompact } from '@/modules/aiAccount/utils/formatVnd';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip);

const props = defineProps({
    rows: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
});

const viewMode = ref('cost');

const sortedRows = computed(() =>
    [...props.rows].sort((a, b) => (b.cost_monthly ?? 0) - (a.cost_monthly ?? 0)).slice(0, 12),
);

const hasData = computed(() =>
    sortedRows.value.some((r) => (r.cost_monthly ?? 0) > 0 || (r.account_count ?? 0) > 0),
);

const chartData = computed(() => {
    const labels = sortedRows.value.map((r) => r.tool_name);
    if (viewMode.value === 'accounts') {
        return {
            labels,
            datasets: [{
                label: 'Số tài khoản',
                data: sortedRows.value.map((r) => r.account_count ?? 0),
                backgroundColor: AI_CHART_SKY,
                hoverBackgroundColor: '#0c4a8a',
                borderRadius: 6,
                maxBarThickness: 22,
            }],
        };
    }
    return {
        labels,
        datasets: [{
            label: 'Chi phí / tháng',
            data: sortedRows.value.map((r) => r.cost_monthly ?? 0),
            backgroundColor: sortedRows.value.map((_, i) =>
                `rgba(154, 0, 54, ${0.92 - i * 0.04})`,
            ),
            hoverBackgroundColor: AI_CHART_BRAND,
            borderRadius: 6,
            maxBarThickness: 22,
        }],
    };
});

const options = computed(() => ({
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'nearest', axis: 'y', intersect: true },
    plugins: {
        ...baseChartPlugins,
        tooltip: {
            ...baseChartPlugins.tooltip,
            callbacks: {
                label: viewMode.value === 'accounts' ? countTooltipLabel : moneyTooltipLabel,
            },
        },
    },
    scales: {
        x: {
            beginAtZero: true,
            grid: { color: 'rgba(148, 163, 184, 0.2)' },
            ticks: {
                font: { size: 11 },
                color: '#64748b',
                callback: (v) => (viewMode.value === 'accounts' ? v : formatVndCompact(v)),
            },
        },
        y: {
            grid: { display: false },
            ticks: { font: { size: 11 }, color: '#334155' },
        },
    },
}));

const VIEW_MODES = [
    { key: 'cost', label: 'Chi phí' },
    { key: 'accounts', label: 'Số TK' },
];
</script>

<template>
  <AiDashboardChartPanel
    title="Chi phí theo sản phẩm AI"
    subtitle="Top công cụ theo chi phí vận hành hoặc số tài khoản đang quản lý"
    icon="account"
    chart-class="h-[22rem] sm:h-80"
    :loading="loading"
    :empty="!loading && !hasData"
    empty-title="Chưa có sản phẩm AI"
    empty-description="Thêm tài khoản AI hoặc phiếu đề xuất đã duyệt để xem phân bổ theo công cụ."
    empty-icon="account"
  >
    <template #toolbar>
      <div
        class="inline-flex h-10 rounded-lg border border-slate-200 bg-slate-50 p-0.5"
        role="group"
        aria-label="Chọn metric hiển thị"
      >
        <button
          v-for="m in VIEW_MODES"
          :key="m.key"
          type="button"
          class="rounded-md px-3 text-xs font-semibold transition"
          :class="viewMode === m.key ? 'bg-white text-brand shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:text-slate-700'"
          :aria-pressed="viewMode === m.key"
          @click="viewMode = m.key"
        >
          {{ m.label }}
        </button>
      </div>
    </template>

    <Bar
      :data="chartData"
      :options="options"
    />
  </AiDashboardChartPanel>
</template>
