<script setup>
import { computed } from 'vue';
import {
    Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend,
} from 'chart.js';
import { Bar } from 'vue-chartjs';
import { formatMoneyShort } from '../composables/useContractFormat.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);

const props = defineProps({
    // { labels: ['T1'..], data: [..] }
    trend: { type: Object, default: () => ({ labels: [], data: [] }) },
});

const hasData = computed(() => (props.trend.data || []).some((v) => Number(v) > 0));

const data = computed(() => ({
    labels: props.trend.labels || [],
    datasets: [
        {
            label: 'Chi phí hết hạn',
            data: props.trend.data || [],
            backgroundColor: 'rgba(245,158,11,0.75)',
            hoverBackgroundColor: '#f59e0b',
            borderRadius: 4,
            maxBarThickness: 30,
        },
    ],
}));

const options = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (ctx) => ` ${formatMoneyShort(ctx.parsed.y)}` } },
    },
    scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
        y: { beginAtZero: true, ticks: { font: { size: 10 }, callback: (v) => formatMoneyShort(v) }, grid: { color: 'rgba(148,163,184,0.18)' } },
    },
};
</script>

<template>
  <div class="h-72">
    <Bar
      v-if="hasData"
      :data="data"
      :options="options"
    />
    <div
      v-else
      class="flex h-full items-center justify-center text-sm text-slate-400"
    >
      Chưa có dữ liệu chi phí theo tháng hết hạn
    </div>
  </div>
</template>
