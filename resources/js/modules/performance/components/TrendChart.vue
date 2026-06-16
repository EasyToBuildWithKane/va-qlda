<script setup>
import { computed } from 'vue';
import {
    Chart as ChartJS, CategoryScale, LinearScale,
    LineElement, PointElement, BarElement, Tooltip, Legend, Filler,
} from 'chart.js';
import { Line } from 'vue-chartjs';
import { BRAND } from '../composables/useChartTheme.js';
import { baseLineOptions } from '../composables/useChartTheme.js';

ChartJS.register(CategoryScale, LinearScale, LineElement, PointElement, BarElement, Tooltip, Legend, Filler);

const props = defineProps({
    trend: { type: Array, default: () => [] },
});

const data = computed(() => ({
    labels: props.trend.map((b) => b.label),
    datasets: [
        {
            label: 'Tỷ lệ hoàn thành',
            data: props.trend.map((b) => b.completionRate),
            borderColor: BRAND,
            backgroundColor: 'rgba(154,0,54,0.08)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: BRAND,
            pointRadius: 2,
            pointHoverRadius: 5,
            yAxisID: 'y',
        },
        {
            label: 'Tỷ lệ đúng hạn',
            data: props.trend.map((b) => b.onTimeRate),
            borderColor: '#10b981',
            backgroundColor: 'transparent',
            borderDash: [4, 3],
            tension: 0.4,
            pointBackgroundColor: '#10b981',
            pointRadius: 2,
            pointHoverRadius: 5,
            yAxisID: 'y',
        },
    ],
}));

const options = baseLineOptions({
    plugins: {
        legend: {
            display: true,
            position: 'bottom',
            labels: { boxWidth: 10, font: { size: 11 }, padding: 12, usePointStyle: true },
        },
        tooltip: { callbacks: { label: (ctx) => ` ${ctx.dataset.label}: ${ctx.parsed.y}%` } },
    },
    scales: {
        x: { grid: { display: false }, ticks: { maxTicksLimit: 12, font: { size: 10 } } },
        y: { beginAtZero: true, max: 100, ticks: { stepSize: 25, font: { size: 10 }, callback: (v) => v + '%' }, grid: { color: 'rgba(148,163,184,0.18)' } },
    },
});

const hasData = computed(() => props.trend.length > 0);
</script>

<template>
  <div class="h-60">
    <Line
      v-if="hasData"
      :data="data"
      :options="options"
    />
    <div
      v-else
      class="flex h-full items-center justify-center text-sm text-slate-400"
    >
      Chưa có dữ liệu xu hướng
    </div>
  </div>
</template>
