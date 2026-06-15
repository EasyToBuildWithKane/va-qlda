<script setup>
import { computed } from 'vue';
import { Radar } from 'vue-chartjs';
import {
    Chart as ChartJS, RadialLinearScale, PointElement, LineElement, Filler, Tooltip,
} from 'chart.js';
import AppIcon from '@/Components/AppIcon.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';

ChartJS.register(RadialLinearScale, PointElement, LineElement, Filler, Tooltip);

const props = defineProps({
    radar: { type: Array, default: () => [] },
});

const hasData = computed(() => props.radar.some((r) => r.count > 0));

const chartData = computed(() => ({
    labels: props.radar.map((r) => r.label),
    datasets: [{
        label: 'Điểm năng lực',
        data: props.radar.map((r) => r.score),
        backgroundColor: 'rgba(154, 0, 54, 0.14)',
        borderColor: '#9a0036',
        borderWidth: 2,
        pointBackgroundColor: '#9a0036',
        pointBorderColor: '#fff',
        pointRadius: 3,
        pointHoverRadius: 5,
        fill: true,
    }],
}));

const options = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        r: {
            min: 0,
            max: 100,
            ticks: { stepSize: 25, font: { size: 9 }, color: '#94a3b8', backdropColor: 'transparent' },
            grid: { color: 'rgba(148, 163, 184, 0.25)' },
            angleLines: { color: 'rgba(148, 163, 184, 0.25)' },
            pointLabels: { font: { size: 10.5, weight: '600' }, color: '#475569' },
        },
    },
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx) => {
                    const item = props.radar[ctx.dataIndex] ?? {};
                    return `${ctx.parsed.r}/100 · ${item.count ?? 0} kỹ năng`;
                },
            },
        },
    },
};
</script>

<template>
  <section class="rounded-2xl border border-slate-200/70 bg-white shadow-sm">
    <header class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5">
      <div class="grid h-8 w-8 place-items-center rounded-lg bg-brand/10 text-brand">
        <AppIcon
          name="performance"
          :size="16"
        />
      </div>
      <div>
        <h2 class="text-sm font-semibold text-slate-800">
          Bản đồ năng lực
        </h2>
        <p class="text-[12px] text-slate-400">
          Điểm trung bình theo lĩnh vực
        </p>
      </div>
    </header>

    <div class="p-5">
      <div
        v-if="hasData"
        class="relative h-64"
      >
        <Radar
          :data="chartData"
          :options="options"
        />
      </div>
      <EmptyState
        v-else
        icon="performance"
        title="Chưa đủ dữ liệu"
        description="Chấm mức độ (1–5) cho kỹ năng để dựng bản đồ năng lực."
      />
    </div>
  </section>
</template>
