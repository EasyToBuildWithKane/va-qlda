<script setup>
import { computed } from 'vue';
import {
    Chart as ChartJS,
    ArcElement, CategoryScale, LinearScale,
    BarElement, Title, Tooltip, Legend,
} from 'chart.js';
import { Doughnut, Bar } from 'vue-chartjs';
import AppIcon from '@/Components/AppIcon.vue';

ChartJS.register(
    ArcElement, CategoryScale, LinearScale,
    BarElement, Title, Tooltip, Legend,
);

const props = defineProps({
    headline: { type: Object, default: () => ({}) },
    tasksByStatus: { type: Array, default: () => [] },
});

const colorMap = {
    slate: '#94a3b8',
    sky: '#0ea5e9',
    violet: '#8b5cf6',
    emerald: '#10b981',
    rose: '#f43f5e',
    amber: '#f59e0b',
    brand: '#9A0036',
};
const tailwindToHex = (color) => colorMap[color] ?? '#94a3b8';

const completionRate = computed(() => props.headline.completionRate ?? 0);
const doneTasks = computed(() => props.headline.doneTasks ?? 0);
const totalTasks = computed(() => props.headline.totalTasks ?? 0);
const remainingTasks = computed(() => Math.max(0, totalTasks.value - doneTasks.value));

const completionDoughnut = computed(() => ({
    labels: ['Hoàn thành', 'Còn lại'],
    datasets: [{
        data: [doneTasks.value, remainingTasks.value],
        backgroundColor: [tailwindToHex('emerald'), '#e2e8f0'],
        borderWidth: 2,
        borderColor: '#fff',
        hoverOffset: 4,
    }],
}));

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '72%',
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx) => {
                    const pct = totalTasks.value
                        ? Math.round((ctx.parsed / totalTasks.value) * 100)
                        : 0;
                    return ` ${ctx.label}: ${ctx.parsed} (${pct}%)`;
                },
            },
        },
    },
};

const statusBarChart = computed(() => ({
    labels: props.tasksByStatus.map((r) => r.label),
    datasets: [{
        label: 'Số công việc',
        data: props.tasksByStatus.map((r) => r.total),
        backgroundColor: props.tasksByStatus.map((r) => tailwindToHex(r.color)),
        borderRadius: 6,
        maxBarThickness: 40,
    }],
}));

const barOptions = {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (ctx) => ` ${ctx.parsed.x} công việc` } },
    },
    scales: {
        x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(148,163,184,0.2)' } },
        y: { grid: { display: false } },
    },
};

const hasData = computed(() => totalTasks.value > 0 || props.tasksByStatus.some((r) => r.total > 0));
</script>

<template>
  <section
    class="relative mb-4 overflow-x-hidden rounded-card border border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white px-4 py-4 shadow-sm sm:px-5 sm:py-5"
    aria-label="Tiến độ hoàn thành công việc toàn hệ thống"
  >
    <div class="relative z-[1] mb-4 flex flex-wrap items-end justify-between gap-2">
      <div>
        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
          Thống kê
        </p>
        <h2 class="font-display text-base font-semibold text-slate-900 sm:text-lg">
          Tiến độ công việc
        </h2>
      </div>
      <p
        v-if="totalTasks"
        class="text-[11px] text-slate-500"
      >
        {{ doneTasks }} / {{ totalTasks }} công việc đã xong
      </p>
    </div>

    <div
      v-if="hasData"
      class="relative z-[1] grid grid-cols-1 gap-4 lg:grid-cols-3"
    >
      <div class="card flex flex-col p-4 lg:col-span-1">
        <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-800">
          <AppIcon
            name="performance"
            :size="15"
            class="text-brand"
          />
          Tỷ lệ hoàn thành
        </h3>
        <div class="relative mx-auto h-44 w-full max-w-[200px] flex-1">
          <Doughnut
            :data="completionDoughnut"
            :options="doughnutOptions"
          />
          <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
            <span class="font-display text-2xl font-bold tabular-nums text-slate-900">{{ completionRate }}%</span>
            <span class="text-[10px] uppercase tracking-wide text-slate-400">Toàn hệ thống</span>
          </div>
        </div>
      </div>

      <div class="card p-4 lg:col-span-2">
        <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-800">
          <AppIcon
            name="task"
            :size="15"
            class="text-brand"
          />
          Phân bố theo trạng thái
        </h3>
        <div class="h-52 sm:h-56">
          <Bar
            v-if="tasksByStatus.length"
            :data="statusBarChart"
            :options="barOptions"
          />
        </div>
      </div>
    </div>

    <p
      v-else
      class="relative z-[1] py-8 text-center text-sm text-slate-400"
    >
      Chưa có công việc trong hệ thống
    </p>
  </section>
</template>
