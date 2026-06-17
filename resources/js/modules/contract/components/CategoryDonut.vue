<script setup>
import { computed } from 'vue';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';
import { Doughnut } from 'vue-chartjs';
import { formatMoneyShort } from '../composables/useContractFormat.js';

ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps({
    // [{ label, value, count }]
    rows: { type: Array, default: () => [] },
});

// Bảng màu nhiều sắc cho nhóm dịch vụ.
const PALETTE = [
    '#9A0036', '#0ea5e9', '#10b981', '#f59e0b', '#8b5cf6',
    '#f43f5e', '#14b8a6', '#6366f1', '#ec4899', '#84cc16',
    '#f97316', '#64748b',
];

const hasData = computed(() => props.rows.some((r) => Number(r.value) > 0));

const data = computed(() => ({
    labels: props.rows.map((r) => r.label),
    datasets: [
        {
            data: props.rows.map((r) => Number(r.value) || 0),
            backgroundColor: props.rows.map((_, i) => PALETTE[i % PALETTE.length]),
            borderWidth: 2,
            borderColor: '#ffffff',
        },
    ],
}));

const options = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '62%',
    plugins: {
        legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 }, padding: 10, usePointStyle: true } },
        tooltip: { callbacks: { label: (ctx) => ` ${ctx.label}: ${formatMoneyShort(ctx.parsed)}` } },
    },
};
</script>

<template>
  <div class="h-72">
    <Doughnut
      v-if="hasData"
      :data="data"
      :options="options"
    />
    <div
      v-else
      class="flex h-full items-center justify-center text-sm text-slate-400"
    >
      Chưa có dữ liệu chi phí theo nhóm dịch vụ
    </div>
  </div>
</template>
