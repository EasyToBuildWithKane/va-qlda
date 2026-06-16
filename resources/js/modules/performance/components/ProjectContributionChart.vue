<script setup>
import { computed } from 'vue';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip } from 'chart.js';
import { Bar } from 'vue-chartjs';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip);

const props = defineProps({
    contribution: { type: Array, default: () => [] },
});

const data = computed(() => ({
    labels: props.contribution.map((p) => p.name),
    datasets: [{
        label: 'Số công việc',
        data: props.contribution.map((p) => p.total),
        backgroundColor: props.contribution.map((p) => p.color || '#9A0036'),
        borderRadius: 6,
        maxBarThickness: 26,
    }],
}));

const options = {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx) => {
                    const row = props.contribution[ctx.dataIndex];
                    return ` ${ctx.parsed.x} việc · ${row.done} xong · ${row.storyPoints} SP`;
                },
            },
        },
    },
    scales: {
        x: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: 'rgba(148,163,184,0.18)' } },
        y: { grid: { display: false }, ticks: { font: { size: 11 } } },
    },
};
</script>

<template>
  <div class="h-60">
    <Bar
      v-if="contribution.length"
      :data="data"
      :options="options"
    />
    <div
      v-else
      class="flex h-full items-center justify-center text-sm text-slate-400"
    >
      Chưa có đóng góp dự án trong kỳ
    </div>
  </div>
</template>
