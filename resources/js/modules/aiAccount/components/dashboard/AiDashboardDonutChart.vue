<script setup>
import { computed } from 'vue';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';
import { Doughnut } from 'vue-chartjs';
import AiDashboardChartPanel from '@/modules/aiAccount/components/dashboard/AiDashboardChartPanel.vue';
import {
    DONUT_PALETTE,
    baseChartPlugins,
} from '@/modules/aiAccount/components/dashboard/aiDashboardChartTheme';
import { formatVndCompact } from '@/modules/aiAccount/utils/formatVnd';

ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    icon: { type: String, default: 'chart' },
    rows: { type: Array, default: () => [] },
    valueKey: { type: String, default: 'amount' },
    palette: { type: String, default: 'budget' },
    centerCaption: { type: String, default: 'Tổng' },
    loading: { type: Boolean, default: false },
    emptyTitle: { type: String, default: 'Chưa có dữ liệu' },
    emptyDescription: { type: String, default: null },
});

const values = computed(() => props.rows.map((r) => Number(r[props.valueKey] ?? r.count ?? r.amount ?? 0)));

const hasData = computed(() => values.value.some((v) => v > 0));

const total = computed(() => values.value.reduce((s, v) => s + v, 0));

const centerPrimary = computed(() => {
    if (props.valueKey === 'amount') {
        return formatVndCompact(total.value);
    }
    return String(total.value);
});

const chartData = computed(() => ({
    labels: props.rows.map((r) => r.label),
    datasets: [{
        data: values.value,
        backgroundColor: DONUT_PALETTE[props.palette] ?? DONUT_PALETTE.budget,
        borderWidth: 3,
        borderColor: '#ffffff',
        hoverOffset: 8,
    }],
}));

const options = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '68%',
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                boxWidth: 10,
                usePointStyle: true,
                pointStyle: 'circle',
                padding: 14,
                font: { size: 11 },
                generateLabels(chart) {
                    const ds = chart.data.datasets[0];
                    const meta = chart.getDatasetMeta(0);
                    return chart.data.labels.map((label, i) => {
                        const value = ds.data[i];
                        const pct = total.value > 0 ? Math.round((value / total.value) * 100) : 0;
                        return {
                            text: `${label} · ${pct}%`,
                            fillStyle: ds.backgroundColor[i],
                            hidden: meta.data[i]?.hidden,
                            index: i,
                        };
                    });
                },
            },
            onClick(_evt, legendItem, legend) {
                const idx = legendItem.index;
                const chart = legend.chart;
                chart.toggleDataVisibility(idx);
                chart.update();
            },
        },
        tooltip: {
            ...baseChartPlugins.tooltip,
            callbacks: {
                label: (ctx) => {
                    const v = ctx.parsed;
                    const pct = total.value > 0 ? Math.round((v / total.value) * 100) : 0;
                    const formatted = props.valueKey === 'amount' ? formatVndCompact(v) : `${v} TK`;
                    return ` ${ctx.label}: ${formatted} (${pct}%)`;
                },
            },
        },
    },
};
</script>

<template>
  <AiDashboardChartPanel
    :title="title"
    :subtitle="subtitle"
    :icon="icon"
    chart-class="min-h-[17rem]"
    :loading="loading"
    :empty="!loading && !hasData"
    :empty-title="emptyTitle"
    :empty-description="emptyDescription"
  >
    <div class="relative mx-auto h-64 max-w-sm">
      <Doughnut
        :data="chartData"
        :options="options"
      />
      <div
        class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center pb-10 text-center"
        aria-hidden="true"
      >
        <p class="font-display text-xl font-semibold tabular-nums text-slate-800">
          {{ centerPrimary }}
        </p>
        <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">
          {{ centerCaption }}
        </p>
      </div>
    </div>
  </AiDashboardChartPanel>
</template>
