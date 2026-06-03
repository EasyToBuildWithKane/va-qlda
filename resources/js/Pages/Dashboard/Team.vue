<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Chart as ChartJS,
    ArcElement, CategoryScale, LinearScale,
    BarElement, LineElement, PointElement,
    Title, Tooltip, Legend, Filler,
} from 'chart.js';
import { Bar, Line } from 'vue-chartjs';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';

ChartJS.register(
    ArcElement, CategoryScale, LinearScale,
    BarElement, LineElement, PointElement,
    Title, Tooltip, Legend, Filler,
);

const props = defineProps({
    members:          { type: Array, default: () => [] },
    weeklyTrend:      { type: Array, default: () => [] },
    projectTaskStats: { type: Array, default: () => [] },
});

// ---- Sort members by total tasks descending -------------------------
const sortBy = ref('total');
const sortedMembers = computed(() => {
    return [...props.members].sort((a, b) => {
        if (sortBy.value === 'rate')  return b.rate  - a.rate;
        if (sortBy.value === 'hours') return b.hours - a.hours;
        return b.total - a.total;
    });
});

// ---- Member task distribution (stacked bar) -------------------------
const statusColors = {
    todo:       '#94a3b8',
    in_progress:'#0ea5e9',
    in_review:  '#8b5cf6',
    done:       '#10b981',
    blocked:    '#f43f5e',
};
const statusLabels = {
    todo:       'Cần làm',
    in_progress:'Đang làm',
    in_review:  'Đang review',
    done:       'Hoàn thành',
    blocked:    'Bị chặn',
};

const memberTaskChart = computed(() => {
    const top = sortedMembers.value.slice(0, 12);
    const statuses = ['todo', 'in_progress', 'in_review', 'done', 'blocked'];
    return {
        labels: top.map(m => m.name.split(' ').slice(-1)[0]),
        datasets: statuses.map(s => ({
            label: statusLabels[s],
            data: top.map(m => m.tasks[s] ?? 0),
            backgroundColor: statusColors[s],
            borderRadius: s === 'blocked' ? 4 : 0,
            stack: 'tasks',
        })),
    };
});

const stackedBarOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 }, padding: 12 } },
        tooltip: {
            callbacks: {
                title: (items) => {
                    const idx = items[0].dataIndex;
                    return sortedMembers.value[idx]?.name ?? '';
                },
            },
        },
    },
    scales: {
        x: { stacked: true, grid: { display: false } },
        y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
    },
};

// ---- Completion rate bar chart --------------------------------------
const completionRateChart = computed(() => {
    const top = sortedMembers.value.slice(0, 12);
    return {
        labels: top.map(m => m.name.split(' ').slice(-1)[0]),
        datasets: [{
            label: 'Tỷ lệ hoàn thành (%)',
            data: top.map(m => m.rate),
            backgroundColor: top.map(m =>
                m.rate >= 80 ? '#10b981' :
                m.rate >= 50 ? '#0ea5e9' :
                m.rate >= 30 ? '#f59e0b' : '#f43f5e',
            ),
            borderRadius: 6,
            borderSkipped: false,
        }],
    };
});

const rateBarOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                title: (items) => {
                    const idx = items[0].dataIndex;
                    return sortedMembers.value[idx]?.name ?? '';
                },
                label: (ctx) => ` Hoàn thành: ${ctx.parsed.y}%`,
            },
        },
    },
    scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, max: 100, ticks: { callback: (v) => v + '%' } },
    },
};

// ---- Worklog hours bar ---------------------------------------------
const hoursChart = computed(() => {
    const top = [...props.members]
        .filter(m => m.hours > 0)
        .sort((a, b) => b.hours - a.hours)
        .slice(0, 12);
    return {
        labels: top.map(m => m.name.split(' ').slice(-1)[0]),
        datasets: [{
            label: 'Giờ làm việc',
            data: top.map(m => m.hours),
            backgroundColor: '#9A0036',
            borderRadius: 6,
            borderSkipped: false,
        }],
        _full: top,
    };
});

const hoursBarOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                title: (items) => {
                    const idx = items[0].dataIndex;
                    return hoursChart.value._full?.[idx]?.name ?? '';
                },
                label: (ctx) => ` ${ctx.parsed.y} giờ (30 ngày)`,
            },
        },
    },
    scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, ticks: { precision: 1 } },
    },
};

// ---- Weekly completion trend ----------------------------------------
const weeklyTrendChart = computed(() => ({
    labels: props.weeklyTrend.map(r => r.label),
    datasets: [{
        label: 'Task hoàn thành',
        data: props.weeklyTrend.map(r => r.total),
        borderColor: '#9A0036',
        backgroundColor: 'rgba(154,0,54,0.1)',
        tension: 0.4,
        fill: true,
        pointBackgroundColor: '#9A0036',
        pointRadius: 4,
        pointHoverRadius: 6,
    }],
}));

const weeklyLineOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (ctx) => ` ${ctx.parsed.y} task hoàn thành` } },
    },
    scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, ticks: { precision: 0 } },
    },
};

// ---- Project task stats (stacked horizontal bar) --------------------
const projectStatsChart = computed(() => {
    const items = props.projectTaskStats.slice(0, 8);
    const statuses = ['todo', 'in_progress', 'in_review', 'done', 'blocked'];
    return {
        labels: items.map(p => p.name.length > 20 ? p.name.substring(0, 20) + '…' : p.name),
        datasets: statuses.map(s => ({
            label: statusLabels[s],
            data: items.map(p => p.byStatus?.[s] ?? 0),
            backgroundColor: statusColors[s],
            stack: 'proj',
        })),
    };
});

const projectStackOptions = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 }, padding: 10 } },
    },
    scales: {
        x: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
        y: { stacked: true, grid: { display: false } },
    },
};

// ---- Summary stats --------------------------------------------------
const totalMembers  = computed(() => props.members.length);
const avgRate       = computed(() => {
    if (!props.members.length) return 0;
    return Math.round(props.members.reduce((s, m) => s + m.rate, 0) / props.members.length);
});
const totalOverdue  = computed(() => props.members.reduce((s, m) => s + m.overdue, 0));
const totalHours    = computed(() => props.members.reduce((s, m) => s + m.hours, 0).toFixed(1));
</script>

<template>
  <Head title="Bảng điều khiển nhóm" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Bảng điều khiển nhóm"
        subtitle="Hiệu suất & phân công công việc trong nhóm"
        icon="team-dashboard"
        icon-color="brand"
      >
        <template #actions>
          <a
            :href="route('dashboard')"
            class="btn-secondary flex items-center gap-1.5 text-sm"
          >
            <AppIcon
              name="overview"
              :size="15"
            />
            Tổng quan
          </a>
        </template>
      </PageHeader>
    </template>

    <!-- Team KPI cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div class="card p-4">
        <p class="text-xs text-slate-500 mb-1">
          Thành viên hoạt động
        </p>
        <p class="text-2xl font-display font-bold text-brand">
          {{ totalMembers }}
        </p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-slate-500 mb-1">
          Tỷ lệ hoàn thành TB
        </p>
        <p
          class="text-2xl font-display font-bold"
          :class="avgRate >= 70 ? 'text-emerald-600' : avgRate >= 40 ? 'text-sky-600' : 'text-rose-600'"
        >
          {{ avgRate }}%
        </p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-slate-500 mb-1">
          Task quá hạn (nhóm)
        </p>
        <p
          class="text-2xl font-display font-bold"
          :class="totalOverdue > 0 ? 'text-rose-600' : 'text-emerald-600'"
        >
          {{ totalOverdue }}
        </p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-slate-500 mb-1">
          Giờ log (30 ngày)
        </p>
        <p class="text-2xl font-display font-bold text-slate-700">
          {{ totalHours }}h
        </p>
      </div>
    </div>

    <!-- Sort toggle -->
    <div class="flex items-center gap-2 mt-4">
      <span class="text-xs text-slate-500 font-medium">Sắp xếp theo:</span>
      <button
        v-for="opt in [['total','Tổng task'],['rate','Tỷ lệ'],['hours','Giờ log']]"
        :key="opt[0]"
        class="px-3 py-1 rounded-full text-xs font-medium border transition-colors"
        :class="sortBy === opt[0]
          ? 'bg-brand text-white border-brand'
          : 'bg-white text-slate-600 border-slate-200 hover:border-brand'"
        @click="sortBy = opt[0]"
      >
        {{ opt[1] }}
      </button>
    </div>

    <!-- Row 1: Stacked task distribution + Completion rate -->
    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="card p-5">
        <h3 class="font-display font-semibold text-slate-800 mb-4 flex items-center gap-2">
          <AppIcon
            name="task"
            :size="16"
            class="text-sky-600"
          />
          Phân bổ công việc theo thành viên
        </h3>
        <div class="h-64">
          <Bar
            v-if="members.length"
            :data="memberTaskChart"
            :options="stackedBarOptions"
          />
          <div
            v-else
            class="h-full flex items-center justify-center text-slate-400 text-sm"
          >
            Chưa có dữ liệu
          </div>
        </div>
      </div>

      <div class="card p-5">
        <h3 class="font-display font-semibold text-slate-800 mb-4 flex items-center gap-2">
          <AppIcon
            name="done"
            :size="16"
            class="text-emerald-600"
          />
          Tỷ lệ hoàn thành theo thành viên
        </h3>
        <div class="h-64">
          <Bar
            v-if="members.length"
            :data="completionRateChart"
            :options="rateBarOptions"
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

    <!-- Row 2: Hours worklog + Weekly trend -->
    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="card p-5">
        <h3 class="font-display font-semibold text-slate-800 mb-4 flex items-center gap-2">
          <AppIcon
            name="worklog"
            :size="16"
            class="text-brand"
          />
          Giờ làm việc (30 ngày qua)
        </h3>
        <div class="h-56">
          <Bar
            v-if="members.some(m => m.hours > 0)"
            :data="hoursChart"
            :options="hoursBarOptions"
          />
          <div
            v-else
            class="h-full flex items-center justify-center text-slate-400 text-sm"
          >
            Chưa có worklog
          </div>
        </div>
      </div>

      <div class="card p-5">
        <h3 class="font-display font-semibold text-slate-800 mb-4 flex items-center gap-2">
          <AppIcon
            name="performance"
            :size="16"
            class="text-brand"
          />
          Hoàn thành theo tuần (6 tuần)
        </h3>
        <div class="h-56">
          <Line
            v-if="weeklyTrend.length"
            :data="weeklyTrendChart"
            :options="weeklyLineOptions"
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

    <!-- Row 3: Project task stats horizontal stacked bar -->
    <div class="mt-4 card p-5">
      <h3 class="font-display font-semibold text-slate-800 mb-4 flex items-center gap-2">
        <AppIcon
          name="projects"
          :size="16"
          class="text-brand"
        />
        Phân bổ công việc theo dự án
      </h3>
      <div :style="{ height: Math.max(200, projectTaskStats.length * 36 + 80) + 'px' }">
        <Bar
          v-if="projectTaskStats.length"
          :data="projectStatsChart"
          :options="projectStackOptions"
        />
        <div
          v-else
          class="h-full flex items-center justify-center text-slate-400 text-sm"
        >
          Chưa có dữ liệu
        </div>
      </div>
    </div>

    <!-- Row 4: Member table -->
    <div class="mt-4 card p-5">
      <h3 class="font-display font-semibold text-slate-800 mb-4 flex items-center gap-2">
        <AppIcon
          name="members"
          :size="16"
          class="text-brand"
        />
        Bảng thống kê thành viên
      </h3>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100">
              <th class="text-left py-2 pr-4 font-semibold text-slate-500 text-xs uppercase tracking-wide">
                Thành viên
              </th>
              <th class="text-center py-2 px-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">
                Tổng
              </th>
              <th class="text-center py-2 px-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">
                Đang làm
              </th>
              <th class="text-center py-2 px-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">
                Hoàn thành
              </th>
              <th class="text-center py-2 px-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">
                Quá hạn
              </th>
              <th class="text-center py-2 px-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">
                Giờ log
              </th>
              <th class="text-right py-2 pl-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">
                Tỷ lệ
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr
              v-for="m in sortedMembers"
              :key="m.id"
              class="hover:bg-slate-50 transition-colors"
            >
              <td class="py-2.5 pr-4">
                <div class="flex items-center gap-2.5">
                  <div class="w-7 h-7 rounded-full bg-brand/10 flex items-center justify-center shrink-0">
                    <img
                      v-if="m.avatar"
                      :src="m.avatar"
                      class="w-7 h-7 rounded-full object-cover"
                    >
                    <span
                      v-else
                      class="text-xs font-bold text-brand"
                    >
                      {{ m.name.charAt(0).toUpperCase() }}
                    </span>
                  </div>
                  <div>
                    <p class="font-medium text-slate-800 text-sm">
                      {{ m.name }}
                    </p>
                    <p class="text-xs text-slate-400">
                      {{ m.role_title }}
                    </p>
                  </div>
                </div>
              </td>
              <td class="text-center py-2.5 px-3 font-medium text-slate-700">
                {{ m.total }}
              </td>
              <td class="text-center py-2.5 px-3 text-sky-600 font-medium">
                {{ (m.tasks.in_progress ?? 0) + (m.tasks.in_review ?? 0) }}
              </td>
              <td class="text-center py-2.5 px-3 text-emerald-600 font-medium">
                {{ m.done }}
              </td>
              <td class="text-center py-2.5 px-3">
                <span
                  v-if="m.overdue > 0"
                  class="inline-block px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 text-xs font-bold"
                >
                  {{ m.overdue }}
                </span>
                <span
                  v-else
                  class="text-slate-300"
                >—</span>
              </td>
              <td class="text-center py-2.5 px-3 text-slate-600">
                {{ m.hours }}h
              </td>
              <td class="text-right py-2.5 pl-3">
                <div class="flex items-center justify-end gap-2">
                  <div class="w-16 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                    <div
                      class="h-full rounded-full"
                      :class="m.rate >= 70 ? 'bg-emerald-500' : m.rate >= 40 ? 'bg-sky-500' : 'bg-rose-400'"
                      :style="{ width: m.rate + '%' }"
                    />
                  </div>
                  <span
                    class="text-xs font-bold w-9 text-right"
                    :class="m.rate >= 70 ? 'text-emerald-600' : m.rate >= 40 ? 'text-sky-600' : 'text-rose-500'"
                  >
                    {{ m.rate }}%
                  </span>
                </div>
              </td>
            </tr>
            <tr v-if="!members.length">
              <td
                colspan="7"
                class="text-center py-8 text-slate-400"
              >
                Chưa có thành viên nào
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
