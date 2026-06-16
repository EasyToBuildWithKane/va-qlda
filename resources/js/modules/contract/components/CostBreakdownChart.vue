<script setup>
import { computed } from 'vue';
import {
    Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend,
} from 'chart.js';
import { Bar } from 'vue-chartjs';
import { BRAND } from '../config/palette.js';
import { formatMoneyShort } from '../composables/useContractFormat.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);

const props = defineProps({
    // [{ label, annual_cost, count }]
    rows: { type: Array, default: () => [] },
});

const hasData = computed(() => props.rows.some((r) => Number(r.annual_cost) > 0));

const data = computed(() => ({
    labels: props.rows.map((r) => r.label),
    datasets: [
        {
            label: 'Chi phí năm',
            data: props.rows.map((r) => Number(r.annual_cost) || 0),
            backgroundColor: 'rgba(154,0,54,0.75)',
            hoverBackgroundColor: BRAND,
            borderRadius: 4,
            maxBarThickness: 22,
        },
    ],
}));

const options = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (ctx) => ` ${formatMoneyShort(ctx.parsed.x)}` } },
    },
    scales: {
        x: { beginAtZero: true, ticks: { font: { size: 10 }, callback: (v) => formatMoneyShort(v) }, grid: { color: 'rgba(148,163,184,0.18)' } },
        y: { grid: { display: false }, ticks: { font: { size: 11 } } },
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
      Chưa có dữ liệu chi phí
    </div>
  </div>
</template>
