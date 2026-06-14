<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';
import { Line, Bar } from 'vue-chartjs';
import { exportCoachingMonthlyWorkbook } from '@/composables/useCoachingExport';
import { currency, hours as fmtHours } from '@/composables/useFormat';
import { useToast } from '@/shared/composables/useToast';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    Filler,
);

const props = defineProps({
    summary: { type: Object, required: true },
    monthly: { type: Object, required: true },
    month: { type: String, required: true },
    revenueSeries: { type: Array, default: () => [] },
    activeCourses: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const toast = useToast();

const chartLabels = computed(() => props.revenueSeries.map((r) => r.month));

const chartData = computed(() => ({
    labels: chartLabels.value,
    datasets: [
        {
            label: 'Doanh thu (VNĐ)',
            data: props.revenueSeries.map((r) => r.revenue),
            borderColor: '#9A0036',
            backgroundColor: 'rgba(154, 0, 54, 0.12)',
            pointBackgroundColor: '#9A0036',
            pointRadius: 4,
            pointHoverRadius: 6,
            tension: 0.35,
            fill: true,
        },
    ],
}));

const hoursChartData = computed(() => ({
    labels: chartLabels.value,
    datasets: [{
        label: 'Giờ dạy',
        data: props.revenueSeries.map((r) => r.hours),
        backgroundColor: 'rgba(154, 0, 54, 0.75)',
        hoverBackgroundColor: '#9A0036',
        borderRadius: 6,
        maxBarThickness: 40,
    }],
}));

const sharedChartPlugins = {
    legend: { display: false },
    tooltip: {
        mode: 'index',
        intersect: false,
        backgroundColor: '#1e293b',
        padding: 12,
        titleFont: { size: 12, weight: '600' },
        bodyFont: { size: 11 },
        callbacks: {},
    },
};

const revenueChartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        ...sharedChartPlugins,
        tooltip: {
            ...sharedChartPlugins.tooltip,
            callbacks: {
                label: (ctx) => ` ${currency(ctx.parsed.y)}`,
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: { font: { size: 11 }, color: '#64748b' },
        },
        y: {
            grid: { color: '#f1f5f9' },
            ticks: {
                font: { size: 11 },
                color: '#64748b',
                callback: (v) => `${(v / 1e6).toFixed(0)}M`,
            },
        },
    },
}));

const hoursChartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        ...sharedChartPlugins,
        tooltip: {
            ...sharedChartPlugins.tooltip,
            callbacks: {
                label: (ctx) => ` ${fmtHours(ctx.parsed.y)}`,
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: { font: { size: 11 }, color: '#64748b' },
        },
        y: {
            grid: { color: '#f1f5f9' },
            ticks: {
                font: { size: 11 },
                color: '#64748b',
                callback: (v) => fmtHours(v),
            },
        },
    },
}));

function exportExcel() {
    exportCoachingMonthlyWorkbook({
        month: props.month,
        monthly: props.monthly,
        revenueSeries: props.revenueSeries,
        summary: props.summary,
    });
    toast.success('Đã xuất file Excel báo cáo coaching.');
}

const kpiCards = computed(() => [
    { key: 'courses', label: 'Tổng khóa học', value: props.summary.courses_total, icon: 'knowledge', accent: false },
    { key: 'active', label: 'Khóa đang diễn ra', value: props.summary.courses_active, icon: 'rocket', accent: true },
    { key: 'sessions', label: 'Tổng buổi học', value: props.summary.sessions_total, icon: 'calendar', accent: false },
    { key: 'hours', label: 'Tổng giờ đào tạo', value: fmtHours(props.summary.hours_total), icon: 'clock', accent: false, raw: true },
]);

const monthlyStats = computed(() => [
    { label: 'Buổi học', value: `${props.monthly.sessions_total} (${props.monthly.sessions_completed} hoàn thành)` },
    { label: 'Giờ dạy', value: fmtHours(props.monthly.hours_total) },
    { label: 'Doanh thu', value: currency(props.monthly.revenue_total), highlight: true },
    { label: 'TB / giờ', value: props.monthly.avg_per_hour != null ? currency(props.monthly.avg_per_hour) : '—' },
    { label: 'TB / buổi', value: props.monthly.avg_per_session != null ? currency(props.monthly.avg_per_session) : '—' },
    { label: 'Học viên', value: props.monthly.students_distinct ?? '—' },
]);
</script>

<template>
  <Head title="Coaching" />
  <AppLayout>
    <PageHeader
      title="Coaching / Mentoring"
      subtitle="Dashboard đào tạo và tài chính"
    >
      <Link
        v-if="can.create"
        href="/coaching/courses/create"
        class="btn-primary h-9 px-3 text-sm"
      >
        Thêm khóa học
      </Link>
      <button
        v-if="can.export"
        type="button"
        class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-btn border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50"
        @click="exportExcel"
      >
        <AppIcon
          name="download"
          :size="15"
        />
        <span>Xuất Excel</span>
      </button>
      <Link
        href="/coaching/courses"
        class="btn-ghost h-9 px-3 text-sm"
      >
        Danh sách khóa
      </Link>
    </PageHeader>

    <div class="mx-auto max-w-7xl space-y-10 pb-10">
      <!-- KPI -->
      <section>
        <h2 class="mb-4 font-display text-xs font-semibold uppercase tracking-wide text-slate-400">
          Tổng quan
        </h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div
            v-for="card in kpiCards"
            :key="card.key"
            class="card flex items-start gap-3 p-5"
          >
            <div
              class="grid h-10 w-10 shrink-0 place-items-center rounded-card"
              :class="card.accent ? 'bg-brand/10 text-brand' : 'bg-slate-100 text-slate-600'"
            >
              <AppIcon
                :name="card.icon"
                :size="20"
              />
            </div>
            <div class="min-w-0">
              <p class="text-xs font-medium text-slate-500">
                {{ card.label }}
              </p>
              <p
                class="mt-1 font-display text-2xl font-semibold tabular-nums"
                :class="card.accent ? 'text-brand' : 'text-slate-800'"
              >
                {{ card.raw ? card.value : card.value }}
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- Tháng hiện tại -->
      <section class="card overflow-hidden">
        <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-4">
          <h2 class="font-display text-sm font-semibold text-slate-800">
            Thống kê tháng {{ month }}
          </h2>
          <p class="mt-0.5 text-xs text-slate-500">
            Chỉ số vận hành và doanh thu trong kỳ hiện tại.
          </p>
        </div>
        <dl class="grid gap-px bg-slate-100 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="stat in monthlyStats"
            :key="stat.label"
            class="bg-white px-6 py-4"
          >
            <dt class="text-xs font-medium text-slate-500">
              {{ stat.label }}
            </dt>
            <dd
              class="mt-1 text-base font-semibold tabular-nums"
              :class="stat.highlight ? 'text-brand' : 'text-slate-800'"
            >
              {{ stat.value }}
            </dd>
          </div>
        </dl>
      </section>

      <!-- Biểu đồ -->
      <section>
        <h2 class="mb-4 font-display text-xs font-semibold uppercase tracking-wide text-slate-400">
          Xu hướng 12 tháng
        </h2>
        <div class="grid gap-6 lg:grid-cols-2">
          <div class="card p-6">
            <div class="mb-4 flex items-start justify-between gap-2">
              <div>
                <h3 class="font-display text-sm font-semibold text-slate-800">
                  Doanh thu
                </h3>
                <p class="text-xs text-slate-500">
                  Di chuột hoặc chạm để xem chi tiết từng tháng.
                </p>
              </div>
            </div>
            <div class="h-[min(22rem,50vh)] min-h-[240px]">
              <Line
                :data="chartData"
                :options="revenueChartOptions"
              />
            </div>
          </div>
          <div class="card p-6">
            <div class="mb-4">
              <h3 class="font-display text-sm font-semibold text-slate-800">
                Giờ giảng dạy
              </h3>
              <p class="text-xs text-slate-500">
                Tổng giờ buổi học đã hoàn thành theo tháng.
              </p>
            </div>
            <div class="h-[min(22rem,50vh)] min-h-[240px]">
              <Bar
                :data="hoursChartData"
                :options="hoursChartOptions"
              />
            </div>
          </div>
        </div>
      </section>

      <!-- Tiến độ -->
      <section
        v-if="activeCourses.length"
        class="card overflow-hidden"
      >
        <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-4">
          <h2 class="font-display text-sm font-semibold text-slate-800">
            Tiến độ khóa đang học
          </h2>
        </div>
        <ul class="divide-y divide-slate-100 px-6 py-2">
          <li
            v-for="c in activeCourses"
            :key="c.id"
            class="py-4"
          >
            <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
              <Link
                :href="`/coaching/courses/${c.id}`"
                class="font-medium text-brand hover:underline"
              >
                <span class="font-mono text-xs text-slate-400">{{ c.code }}</span>
                {{ c.name }}
              </Link>
              <span class="font-semibold tabular-nums text-slate-700">{{ c.progress_percent }}%</span>
            </div>
            <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-slate-100">
              <div
                class="h-full rounded-full bg-brand transition-all"
                :style="{ width: `${c.progress_percent}%` }"
              />
            </div>
          </li>
        </ul>
      </section>
    </div>
  </AppLayout>
</template>
