<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Chart as ChartJS,
    ArcElement, CategoryScale, LinearScale,
    LineElement, PointElement,
    Title, Tooltip, Legend, Filler,
} from 'chart.js';
import { Doughnut, Line } from 'vue-chartjs';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import ActivityFeed from '@/modules/project/components/Dashboard/ActivityFeed.vue';
import DailyReportCompliancePanel from './partials/DailyReportCompliancePanel.vue';
import ProjectProgressCard from './partials/ProjectProgressCard.vue';
import TaskProgressStatsSection from './partials/TaskProgressStatsSection.vue';

ChartJS.register(
    ArcElement, CategoryScale, LinearScale,
    LineElement, PointElement,
    Title, Tooltip, Legend, Filler,
);

const props = defineProps({
    kpiCards:           { type: Array,  default: () => [] },
    headline:           { type: Object, default: () => ({}) },
    activeProjects:     { type: Array,  default: () => [] },
    tasksByStatus:      { type: Array,  default: () => [] },
    dueToday:           { type: Array,  default: () => [] },
    overdueTasks:       { type: Array,  default: () => [] },
    activityFeed:       { type: Array,  default: () => [] },
    projectsByStatus:   { type: Array,  default: () => [] },
    completionTrend:    { type: Array,  default: () => [] },
    dailyReportCompliance: { type: Object, default: () => ({}) },
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
const tailwindToHex = (color) => colorMap[color] ?? '#94a3b8';

function listProp(value) {
    if (Array.isArray(value)) return value;
    if (value && typeof value === 'object') return Object.values(value);
    return [];
}

// ---- Attention list (overdue first, then due today) ------------------
const attentionTasks = computed(() => [
    ...listProp(props.overdueTasks).map((t) => ({ ...t, _overdue: true })),
    ...listProp(props.dueToday).map((t) => ({ ...t, _overdue: false })),
].slice(0, 10));

function fmtDate(iso) {
    if (!iso) return '—';
    const [, m, d] = iso.split('-');
    return `${d}/${m}`;
}

// ---- Charts -----------------------------------------------------------
const projectStatusChart = computed(() => ({
    labels: listProp(props.projectsByStatus).map(r => r.label),
    datasets: [{
        data: listProp(props.projectsByStatus).map(r => r.total),
        backgroundColor: listProp(props.projectsByStatus).map(r => tailwindToHex(r.color)),
        borderWidth: 2, borderColor: '#fff', hoverOffset: 6,
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

const trendChart = computed(() => ({
    labels: listProp(props.completionTrend).map(r => r.label),
    datasets: [{
        label: 'Hoàn thành',
        data: listProp(props.completionTrend).map(r => r.total),
        borderColor: '#9A0036',
        backgroundColor: 'rgba(154,0,54,0.08)',
        tension: 0.4, fill: true,
        pointBackgroundColor: '#9A0036', pointRadius: 2, pointHoverRadius: 5,
    }],
}));

const lineOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (ctx) => ` ${ctx.parsed.y} công việc hoàn thành` } },
    },
    scales: {
        x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } },
        y: { beginAtZero: true, ticks: { precision: 0 } },
    },
};
</script>

<template>
  <Head title="Bảng điều khiển" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Bảng điều khiển"
        subtitle="Trung tâm điều hành — dự án, tiến độ & tuân thủ báo cáo ngày"
        icon="overview"
        icon-color="brand"
      />
    </template>

    <!-- 1. KPI strip -->
    <KpiSummaryStrip
      :cards="kpiCards"
      heading="Tổng quan hệ thống"
      eyebrow="Chỉ số chính"
      aria-label="Chỉ số tổng quan hệ thống"
      grid-class="grid-cols-2 sm:grid-cols-3 xl:grid-cols-6"
    />

    <TaskProgressStatsSection
      :headline="headline"
      :tasks-by-status="tasksByStatus"
    />

    <!-- Tuân thủ báo cáo ngày theo nhân sự -->
    <DailyReportCompliancePanel
      :compliance="dailyReportCompliance"
      class="mb-4"
    />

    <!-- 3. Active projects -->
    <section class="mb-4">
      <div class="mb-3 flex items-center justify-between">
        <h2 class="flex items-center gap-2 font-display font-semibold text-slate-800">
          <AppIcon
            name="projects"
            :size="17"
            class="text-brand"
          />
          Dự án đang triển khai
        </h2>
        <Link
          href="/projects"
          class="text-xs font-medium text-brand hover:underline"
        >
          Xem tất cả →
        </Link>
      </div>

      <div
        v-if="activeProjects.length"
        class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
      >
        <ProjectProgressCard
          v-for="p in activeProjects"
          :key="p.id"
          :project="p"
        />
      </div>
      <EmptyState
        v-else
        icon="projects"
        title="Chưa có dự án đang triển khai"
        description="Các dự án ở trạng thái 'Đang triển khai' sẽ hiển thị tại đây."
      />
    </section>

    <!-- 4. Charts -->
    <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
      <div class="card p-5 lg:col-span-2">
        <h3 class="mb-4 flex items-center gap-2 font-display font-semibold text-slate-800">
          <AppIcon
            name="performance"
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
        </div>
      </div>

      <div class="card p-5">
        <h3 class="mb-4 flex items-center gap-2 font-display font-semibold text-slate-800">
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
            class="flex h-full items-center justify-center text-sm text-slate-400"
          >
            Chưa có dữ liệu
          </div>
        </div>
      </div>
    </div>

    <!-- 5. Bottom: attention list · activity feed -->
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
      <!-- Attention list -->
      <div class="card p-5">
        <h3 class="mb-4 flex items-center gap-2 font-display font-semibold text-slate-800">
          <AppIcon
            name="clock"
            :size="16"
            class="text-amber-600"
          />
          Công việc cần chú ý
        </h3>
        <ul
          v-if="attentionTasks.length"
          class="space-y-2.5"
        >
          <li
            v-for="t in attentionTasks"
            :key="t.id"
            class="flex items-center gap-2.5"
          >
            <span
              class="h-2 w-2 shrink-0 rounded-full"
              :style="{ backgroundColor: t.project?.color || '#94a3b8' }"
            />
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm text-slate-700">
                {{ t.title }}
              </p>
              <p class="truncate text-[11px] text-slate-400">
                {{ t.project?.name || 'Không thuộc dự án' }}
              </p>
            </div>
            <span
              class="shrink-0 text-[11px] font-semibold"
              :class="t._overdue ? 'text-rose-600' : 'text-amber-600'"
            >
              {{ t._overdue ? 'Trễ ' : '' }}{{ fmtDate(t.dueDate) }}
            </span>
            <Avatar
              v-if="t.assignee"
              :name="t.assignee.name"
              :src="t.assignee.avatar"
              :size="24"
            />
          </li>
        </ul>
        <EmptyState
          v-else
          icon="check-circle"
          title="Không có việc quá hạn"
          description="Không có công việc nào quá hạn hay đến hạn hôm nay."
        />
      </div>

      <!-- Activity feed -->
      <ActivityFeed
        :activities="activityFeed"
        :default-per-page="5"
        per-page-storage-key="va-qlda.dashboard.activity.perPage"
      />
    </div>
  </AppLayout>
</template>
