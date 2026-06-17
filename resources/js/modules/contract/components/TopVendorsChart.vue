<script setup>
import { computed } from 'vue';
import {
    Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend,
} from 'chart.js';
import { Bar } from 'vue-chartjs';
import { formatMoneyShort } from '../composables/useContractFormat.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);

const props = defineProps({
    // [{ name, count, annual_cost, cashflow }]
    vendors: { type: Array, default: () => [] },
});

const hasData = computed(() => props.vendors.some((v) => Number(v.annual_cost) > 0 || Number(v.cashflow) > 0));

const data = computed(() => ({
    labels: props.vendors.map((v) => v.name),
    datasets: [
        {
            label: 'Chi phí năm',
            data: props.vendors.map((v) => Number(v.annual_cost) || 0),
            backgroundColor: 'rgba(154,0,54,0.78)',
            hoverBackgroundColor: '#9A0036',
            borderRadius: 4,
            maxBarThickness: 14,
        },
        {
            label: 'Dòng tiền',
            data: props.vendors.map((v) => Number(v.cashflow) || 0),
            backgroundColor: 'rgba(14,165,233,0.7)',
            hoverBackgroundColor: '#0ea5e9',
            borderRadius: 4,
            maxBarThickness: 14,
        },
    ],
}));

const options = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 }, usePointStyle: true } },
        tooltip: { callbacks: { label: (ctx) => ` ${ctx.dataset.label}: ${formatMoneyShort(ctx.parsed.x)}` } },
    },
    scales: {
        x: { beginAtZero: true, ticks: { font: { size: 10 }, callback: (v) => formatMoneyShort(v) }, grid: { color: 'rgba(148,163,184,0.18)' } },
        y: { grid: { display: false }, ticks: { font: { size: 11 } } },
    },
};
</script>

<template>
  <div class="h-80">
    <Bar
      v-if="hasData"
      :data="data"
      :options="options"
    />
    <div
      v-else
      class="flex h-full items-center justify-center text-sm text-slate-400"
    >
      Chưa có dữ liệu nhà cung cấp
    </div>
  </div>
</template>
