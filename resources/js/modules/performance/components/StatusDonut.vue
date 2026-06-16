<script setup>
import { computed } from 'vue';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';
import { Doughnut } from 'vue-chartjs';
import { tailwindToHex } from '../composables/useChartTheme.js';

ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps({
    distribution: { type: Array, default: () => [] },
});

const total = computed(() => props.distribution.reduce((s, r) => s + r.total, 0));

const data = computed(() => ({
    labels: props.distribution.map((r) => r.label),
    datasets: [{
        data: props.distribution.map((r) => r.total),
        backgroundColor: props.distribution.map((r) => tailwindToHex(r.color)),
        borderWidth: 2,
        borderColor: '#fff',
        hoverOffset: 6,
    }],
}));

const options = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '68%',
    plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 }, padding: 12, usePointStyle: true } },
        tooltip: {
            callbacks: {
                label: (ctx) => {
                    const pct = total.value ? Math.round((ctx.parsed / total.value) * 100) : 0;
                    return ` ${ctx.label}: ${ctx.parsed} (${pct}%)`;
                },
            },
        },
    },
};
</script>

<template>
  <div class="relative h-56">
    <Doughnut
      v-if="total > 0"
      :data="data"
      :options="options"
    />
    <div
      v-else
      class="flex h-full items-center justify-center text-sm text-slate-400"
    >
      Chưa có công việc trong kỳ
    </div>
    <div
      v-if="total > 0"
      class="pointer-events-none absolute inset-x-0 top-[38%] -translate-y-1/2 text-center"
    >
      <p class="font-display text-2xl font-bold tabular-nums text-slate-900">
        {{ total }}
      </p>
      <p class="text-[10px] uppercase tracking-wide text-slate-400">
        công việc
      </p>
    </div>
  </div>
</template>
