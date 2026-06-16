<script setup>
import { computed } from 'vue';
import {
    Chart as ChartJS, CategoryScale, LinearScale,
    BarElement, LineElement, PointElement, Tooltip, Legend, Filler,
} from 'chart.js';
import { Bar } from 'vue-chartjs';
import { BRAND } from '../config/palette.js';
import { formatMoneyShort } from '../composables/useContractFormat.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, LineElement, PointElement, Tooltip, Legend, Filler);

const props = defineProps({
    // { labels: [], data: [] }
    trend: { type: Object, default: () => ({ labels: [], data: [] }) },
});

const hasData = computed(() => (props.trend?.data || []).some((v) => Number(v) > 0));

const data = computed(() => ({
    labels: props.trend?.labels || [],
    datasets: [
        {
            label: 'Chi phí tháng',
            data: props.trend?.data || [],
            backgroundColor: 'rgba(154,0,54,0.75)',
            hoverBackgroundColor: BRAND,
            borderRadius: 4,
            maxBarThickness: 28,
        },
    ],
}));

const options = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: { label: (ctx) => ` ${formatMoneyShort(ctx.parsed.y)}` },
        },
    },
    scales: {
        x: { grid: { display: false }, ticks: { font: { size: 10 } } },
        y: {
            beginAtZero: true,
            ticks: { font: { size: 10 }, callback: (v) => formatMoneyShort(v) },
            grid: { color: 'rgba(148,163,184,0.18)' },
        },
    },
};
</script>

<template>
  <div class="h-60">
    <Bar
      v-if="hasData"
      :data="data"
      :options="options"
    />
    <div
      v-else
      class="flex h-full items-center justify-center text-sm text-slate-400"
    >
      Chưa có dữ liệu chi phí
    </div>
  </div>
</template>
