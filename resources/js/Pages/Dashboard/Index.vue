<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Chart as ChartJS,
    ArcElement, CategoryScale, LinearScale,
    BarElement, LineElement, PointElement,
    Title, Tooltip, Legend, Filler,
} from 'chart.js';
import { Doughnut, Bar, Line } from 'vue-chartjs';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';

ChartJS.register(
    ArcElement, CategoryScale, LinearScale,
    BarElement, LineElement, PointElement,
    Title, Tooltip, Legend, Filler,
);

const props = defineProps({
    kpi:               { type: Object, default: () => ({}) },
    projectsByStatus:  { type: Array,  default: () => [] },
    tasksByStatus:     { type: Array,  default: () => [] },
    blockersBySeverity:{ type: Array,  default: () => [] },
    completionTrend:   { type: Array,  default: () => [] },
    topProjects:       { type: Array,  default: () => [] },
    tasksByPriority:   { type: Array,  default: () => [] },
});

// ---- Helpers ----------------------------------------------------------
const colorMap = {
    slate:   '#94a3b8',
    sky:     '#0ea5e9',
    violet:  '#8b5cf6',
    emerald: '#10b981',
    rose:    '#f43f5e',
    amber:   '#f59e0b',
};
function tailwindToHex(color) {
    return colorMap[color] ?? '#94a3b8';
}

// ---- KPI cards --------------------------------------------------------
const kpiCards = computed(() => [
    {
        label: 'Dự án đang chạy',
        value: props.kpi.activeProjects ?? 0,
        sub: `/ ${props.kpi.totalProjects ?? 0} tổng`,
        icon: 'projects',
        color: 'text-brand',
        bg: 'bg-rose-50',
    },
    {
        label: 'Tổng công việc',
        value: props.kpi.totalTasks ?? 0,
        sub: `${props.kpi.doneTasks ?? 0} hoàn thành`,
        icon: 'task',
        color: 'text-sky-600',
        bg: 'bg-sky-50',
    },
    {
        label: 'Vướng mắc đang mở',
        value: props.kpi.openBlockers ?? 0,
        sub: 'Cần xử lý',
        icon: 'blockers',
        color: 'text-amber-600',
        bg: 'bg-amber-50',
    },
    {
        label: 'Thành viên hoạt động',
        value: props.kpi.totalMembers ?? 0,
        sub: 'Nhân sự đang làm việc',
        icon: 'people',
        color: 'text-emerald-600',
        bg: 'bg-emerald-50',
    },
    {
        label: 'Công việc quá hạn',
        value: props.kpi.overdueTasks ?? 0,
        sub: 'Chưa hoàn thành, đã trễ',
        icon: 'clock',
        color: 'text-rose-600',
        bg: 'bg-rose-50',
    },
    {
        label: 'Bug đang mở',
        value: props.kpi.openBugs ?? 0,
        sub: 'Cần sửa lỗi',
        icon: 'bug',
        color: 'text-violet-600',
        bg: 'bg-violet-50',
    },
]);

// ---- Project status doughnut -----------------------------------------
const projectStatusChart = computed(() => ({
    labels: props.projectsByStatus.map(r => r.label),
    datasets: [{
        data: props.projectsByStatus.map(r => r.total),
        backgroundColor: props.projectsByStatus.map(r => tailwindToHex(r.color)),
        borderWidth: 2,
        borderColor: '#fff',
        hoverOffset: 6,
    }],
}));

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '65%',
    plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 }, padding: 14 } },
        tooltip: { callbacks: { label: (ctx) => ` ${ctx.label}: ${ctx.parsed}` } },
    },
};

// ---- Task status bar -------------------------------------------------
const taskStatusChart = computed(() => {
    const order = ['todo', 'in_progress', 'in_review', 'done', 'blocked'];
    const sorted = [...props.tasksByStatus].sort(
        (a, b) => order.indexOf(a.status) - order.indexOf(b.status),
    );
    return {
        labels: sorted.map(r => r.label),
        datasets: [{
            label: 'Công việc',
            data: sorted.map(r => r.total),
            backgroundColor: sorted.map(r => tailwindToHex(r.color)),
            borderRadius: 6,
            borderSkipped: false,
        }],
    };
});

const barOptions = (yTitle) => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (ctx) => ` ${ctx.parsed.y} công việc` } },
    },
    scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: !!yTitle, text: yTitle } },
    },
});

// ---- Blocker severity doughnut ---------------------------------------
const blockerSeverityChart = computed(() => ({
    labels: props.blockersBySeverity.map(r => r.label),
    datasets: [{
        data: props.blockersBySeverity.map(r => r.total),
        backgroundColor: props.blockersBySeverity.map(r => tailwindToHex(r.color)),
        borderWidth: 2,
        borderColor: '#fff',
        hoverOffset: 6,
    }],
}));

// ---- Task completion trend line --------------------------------------
const trendChart = computed(() => ({
    labels: props.completionTrend.map(r => r.label),
    datasets: [{
        label: 'Hoàn thành',
        data: props.completionTrend.map(r => r.total),
        borderColor: '#9A0036',
        backgroundColor: 'rgba(154,0,54,0.08)',
        tension: 0.4,
        fill: true,
        pointBackgroundColor: '#9A0036',
        pointRadius: 3,
        pointHoverRadius: 5,
    }],
}));

const lineOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (ctx) => ` ${ctx.parsed.y} task hoàn thành` } },
    },
    scales: {
        x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } },
        y: { beginAtZero: true, ticks: { precision: 0 } },
    },
};

// ---- Task priority bar -----------------------------------------------
const priorityChart = computed(() => {
    const order = ['low', 'medium', 'high', 'urgent'];
    const sorted = [...props.tasksByPriority].sort(
        (a, b) => order.indexOf(a.priority) - order.indexOf(b.priority),
    );
    return {
        labels: sorted.map(r => r.label),
        datasets: [{
            label: 'Công việc',
            data: sorted.map(r => r.total),
            backgroundColor: sorted.map(r => tailwindToHex(r.color)),
            borderRadius: 6,
            borderSkipped: false,
        }],
    };
});

// ---- Completion rate helper -----------------------------------------
const completionRate = computed(() => {
    const total = props.kpi.totalTasks ?? 0;
    const done  = props.kpi.doneTasks  ?? 0;
    return total > 0 ? Math.round(done / total * 100) : 0;
});
</script>

<template>
  <Head title="Bảng điều khiển" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Bảng điều khiển"
        subtitle="Tổng quan hiệu suất & tiến độ hệ thống"
        icon="overview"
        icon-color="brand"
      >
        <template #actions>
          <a
            :href="route('dashboard.team')"
            class="btn-secondary flex items-center gap-1.5 text-sm"
          >
            <AppIcon
              name="people"
              :size="15"
            />
            Nhóm
          </a>
        </template>
      </PageHeader>
    </template>

    <!-- KPI cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4">
      <div
        v-for="card in kpiCards"
        :key="card.label"
        class="card p-4 flex flex-col gap-2"
      >
        <div class="flex items-center justify-between">
          <span class="text-xs text-slate-500 font-medium leading-tight">{{ card.label }}</span>
          <span :class="[card.bg, 'rounded-lg p-1.5']">
            <AppIcon
              :name="card.icon"
              :size="15"
              :class="card.color"
            />
          </span>
        </div>
        <p :class="['font-display text-2xl font-bold', card.color]">
          {{ card.value }}
        </p>
        <p class="text-xs text-slate-400">
          {{ card.sub }}
        </p>
      </div>
    </div>

    <!-- Completion rate banner -->
    <div class="card p-4 mt-4 flex items-center gap-4">
      <div class="flex-1">
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-sm font-medium text-slate-700">Tiến độ hoàn thành toàn hệ thống</span>
          <span class="text-sm font-bold text-brand">{{ completionRate }}%</span>
        </div>
        <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
          <div
            class="h-full rounded-full bg-brand transition-all duration-700"
            :style="{ width: completionRate + '%' }"
          />
        </div>
      </div>
      <div class="text-right shrink-0">
        <p class="text-xl font-display font-bold text-brand">
          {{ kpi.doneTasks }}
        </p>
        <p class="text-xs text-slate-400">
          / {{ kpi.totalTasks }} task
        </p>
      </div>
    </div>

    <!-- Row 1: Project status + Task status -->
    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- Project status donut -->
      <div class="card p-5">
        <h3 class="font-display font-semibold text-slate-800 mb-4 flex items-center gap-2">
          <AppIcon
            name="projects"
            :size="16"
            class="text-brand"
          />
          Trạng thái dự án
        </h3>
        <div class="h-56">
          <Doughnut
            v-if="projectsByStatus.length"
            :data="projectStatusChart"
            :options="doughnutOptions"
          />
          <div
            v-else
            class="h-full flex items-center justify-center text-slate-400 text-sm"
          >
            Chưa có dữ liệu
          </div>
        </div>
      </div>

      <!-- Task status bar -->
      <div class="card p-5">
        <h3 class="font-display font-semibold text-slate-800 mb-4 flex items-center gap-2">
          <AppIcon
            name="task"
            :size="16"
            class="text-sky-600"
          />
          Công việc theo trạng thái
        </h3>
        <div class="h-56">
          <Bar
            v-if="tasksByStatus.length"
            :data="taskStatusChart"
            :options="barOptions()"
          />
          <div
            v-else
            class="h-full flex items-center justify-center text-slate-400 text-sm"
          >
            Chưa có dữ liệu
          </div>
        </div>
      </div>
    </div>

    <!-- Row 2: Completion trend + Blocker severity -->
    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
      <!-- Completion trend line (2/3 width) -->
      <div class="card p-5 md:col-span-2">
        <h3 class="font-display font-semibold text-slate-800 mb-4 flex items-center gap-2">
          <AppIcon
            name="overview"
            :size="16"
            class="text-brand"
          />
          Xu hướng hoàn thành công việc (30 ngày)
        </h3>
        <div class="h-56">
          <Line
            v-if="completionTrend.length"
            :data="trendChart"
            :options="lineOptions"
          />
          <div
            v-else
            class="h-full flex items-center justify-center text-slate-400 text-sm"
          >
            Chưa có dữ liệu
          </div>
        </div>
      </div>

      <!-- Blocker severity donut -->
      <div class="card p-5">
        <h3 class="font-display font-semibold text-slate-800 mb-4 flex items-center gap-2">
          <AppIcon
            name="blockers"
            :size="16"
            class="text-amber-600"
          />
          Mức độ vướng mắc
        </h3>
        <div class="h-56">
          <Doughnut
            v-if="blockersBySeverity.length"
            :data="blockerSeverityChart"
            :options="doughnutOptions"
          />
          <div
            v-else
            class="h-full flex items-center justify-center text-slate-400 text-sm"
          >
            Không có vướng mắc
          </div>
        </div>
      </div>
    </div>

    <!-- Row 3: Task priority + Top projects -->
    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- Task priority -->
      <div class="card p-5">
        <h3 class="font-display font-semibold text-slate-800 mb-4 flex items-center gap-2">
          <AppIcon
            name="flag"
            :size="16"
            class="text-violet-600"
          />
          Công việc theo độ ưu tiên
        </h3>
        <div class="h-52">
          <Bar
            v-if="tasksByPriority.length"
            :data="priorityChart"
            :options="barOptions()"
          />
          <div
            v-else
            class="h-full flex items-center justify-center text-slate-400 text-sm"
          >
            Chưa có dữ liệu
          </div>
        </div>
      </div>

      <!-- Top projects by progress -->
      <div class="card p-5">
        <h3 class="font-display font-semibold text-slate-800 mb-4 flex items-center gap-2">
          <AppIcon
            name="performance"
            :size="16"
            class="text-emerald-600"
          />
          Tiến độ dự án đang chạy
        </h3>
        <div class="space-y-3 overflow-y-auto max-h-52 pr-1">
          <div
            v-for="p in topProjects"
            :key="p.id"
            class="group"
          >
            <div class="flex items-center justify-between mb-1">
              <a
                :href="route('projects.show', p.id)"
                class="text-sm font-medium text-slate-700 group-hover:text-brand truncate max-w-[70%]"
              >
                {{ p.name }}
              </a>
              <span class="text-xs font-bold text-slate-600 shrink-0">{{ p.progress }}%</span>
            </div>
            <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-700"
                :style="{
                  width: p.progress + '%',
                  backgroundColor: p.color || '#9A0036',
                }"
              />
            </div>
          </div>
          <div
            v-if="!topProjects.length"
            class="text-sm text-slate-400 text-center py-6"
          >
            Chưa có dự án đang chạy
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
