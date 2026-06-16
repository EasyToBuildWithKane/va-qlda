<script setup>
import { computed } from 'vue';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';
import { Doughnut } from 'vue-chartjs';
import { hexFor } from '../config/palette.js';

ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps({
    // [{ value, label, color, count }]
    distribution: { type: Array, default: () => [] },
});

const hasData = computed(() => props.distribution.some((d) => d.count > 0));

const data = computed(() => ({
    labels: props.distribution.map((d) => d.label),
    datasets: [
        {
            data: props.distribution.map((d) => d.count),
            backgroundColor: props.distribution.map((d) => hexFor(d.color)),
            borderWidth: 2,
            borderColor: '#ffffff',
        },
    ],
}));

const options = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '64%',
    plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 }, padding: 12, usePointStyle: true } },
    },
};
</script>

<template>
  <div class="h-60">
    <Doughnut
      v-if="hasData"
      :data="data"
      :options="options"
    />
    <div
      v-else
      class="flex h-full items-center justify-center text-sm text-slate-400"
    >
      Chưa có dữ liệu hợp đồng
    </div>
  </div>
</template>
