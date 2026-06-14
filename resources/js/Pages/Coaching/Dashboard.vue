<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import CoachingWorkspace from '@/modules/coaching/components/CoachingWorkspace.vue';
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
    dailySeries: { type: Array, default: () => [] },
    activeCourses: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const toast = useToast();

const CHART_GRANULARITY_OPTS = [
    { value: 'month', label: 'Theo tháng' },
    { value: 'day', label: 'Theo ngày' },
];

/** Mặc định: doanh thu 12 tháng; giờ dạy chi tiết theo ngày trong kỳ đang xem. */
const revenueGranularity = ref('month');
const hoursGranularity = ref('day');

function seriesForGranularity(granularity) {
    return granularity === 'day' ? props.dailySeries : props.revenueSeries;
}

function labelFromPoint(granularity, point) {
    if (granularity === 'day') return point.label ?? point.day;
    return point.month;
}

const revenueChartLabels = computed(() =>
    seriesForGranularity(revenueGranularity.value).map((r) => labelFromPoint(revenueGranularity.value, r)),
);

const chartData = computed(() => ({
    labels: revenueChartLabels.value,
    datasets: [
        {
            label: 'Doanh thu (VNĐ)',
            data: seriesForGranularity(revenueGranularity.value).map((r) => r.revenue),
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

const hoursChartLabels = computed(() =>
    seriesForGranularity(hoursGranularity.value).map((r) => labelFromPoint(hoursGranularity.value, r)),
);

const hoursChartData = computed(() => ({
    labels: hoursChartLabels.value,
    datasets: [{
        label: 'Giờ dạy',
        data: seriesForGranularity(hoursGranularity.value).map((r) => r.hours),
        backgroundColor: 'rgba(154, 0, 54, 0.75)',
        hoverBackgroundColor: '#9A0036',
        borderRadius: 6,
        maxBarThickness: hoursGranularity.value === 'day' ? 12 : 40,
    }],
}));

const revenueChartSubtitle = computed(() =>
    revenueGranularity.value === 'day'
        ? `Theo từng ngày trong ${monthLabel.value}.`
        : '12 tháng gần nhất — di chuột hoặc chạm để xem chi tiết.',
);

const hoursChartSubtitle = computed(() =>
    hoursGranularity.value === 'day'
        ? `Giờ buổi hoàn thành theo ngày — ${monthLabel.value}.`
        : 'Tổng giờ buổi học đã hoàn thành theo tháng.',
);

const toneClass = {
    brand: 'text-brand bg-rose-50',
    emerald: 'text-emerald-700 bg-emerald-50',
    sky: 'text-sky-700 bg-sky-50',
    amber: 'text-amber-700 bg-amber-50',
    violet: 'text-violet-700 bg-violet-50',
    slate: 'text-slate-600 bg-slate-100',
};

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
    {
        key: 'students',
        label: 'Học viên',
        value: props.summary.students_distinct ?? 0,
        sub: 'Theo khóa (tên hoặc nhân sự)',
        icon: 'people',
        tone: 'emerald',
    },
    {
        key: 'active',
        label: 'Khóa đang diễn ra',
        value: props.summary.courses_active,
        sub: `/ ${props.summary.courses_total} tổng`,
        icon: 'rocket',
        tone: 'brand',
    },
    {
        key: 'sessions',
        label: 'Tổng buổi học',
        value: props.summary.sessions_total,
        sub: `${props.monthly.sessions_completed} hoàn thành tháng này`,
        icon: 'calendar',
        tone: 'sky',
    },
    {
        key: 'hours',
        label: 'Tổng giờ đào tạo',
        value: fmtHours(props.summary.hours_total),
        sub: `${fmtHours(props.monthly.hours_total)} trong tháng`,
        icon: 'clock',
        tone: 'violet',
        isText: true,
    },
]);

const monthlyStats = computed(() => [
    {
        key: 'revenue',
        label: 'Doanh thu tháng',
        value: currency(props.monthly.revenue_total),
        icon: 'budget',
        tone: 'brand',
        highlight: true,
    },
    {
        key: 'students',
        label: 'Học viên (tháng)',
        value: String(props.monthly.students_distinct ?? 0),
        icon: 'people',
        tone: 'emerald',
    },
    {
        key: 'sessions',
        label: 'Buổi học',
        value: `${props.monthly.sessions_total}`,
        sub: `${props.monthly.sessions_completed} hoàn thành`,
        icon: 'calendar',
        tone: 'sky',
    },
    {
        key: 'hours',
        label: 'Giờ dạy',
        value: fmtHours(props.monthly.hours_total),
        icon: 'clock',
        tone: 'violet',
        isText: true,
    },
    {
        key: 'avg_hour',
        label: 'TB / giờ',
        value: props.monthly.avg_per_hour != null ? currency(props.monthly.avg_per_hour) : '—',
        icon: 'performance',
        tone: 'amber',
        isText: true,
    },
    {
        key: 'avg_session',
        label: 'TB / buổi',
        value: props.monthly.avg_per_session != null ? currency(props.monthly.avg_per_session) : '—',
        icon: 'cost',
        tone: 'slate',
        isText: true,
    },
]);

const monthLabel = computed(() => {
    const [y, m] = props.month.split('-').map(Number);
    if (!y || !m) return props.month;
    return `tháng ${m}/${y}`;
});
</script>

<template>
  <Head title="Coaching" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Coaching / Mentoring"
        subtitle="Dashboard đào tạo và tài chính"
        icon="knowledge"
        icon-color="brand"
      >
        <Link
          v-if="can.create"
          :href="`${route('coaching.courses.index')}?create=1`"
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
    </template>

    <CoachingWorkspace>
      <div class="space-y-6 pb-10">
        <!-- KPI -->
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <div
            v-for="card in kpiCards"
            :key="card.key"
            class="rounded-xl border border-slate-200/70 bg-gradient-to-br from-white to-slate-50/80 p-4"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-[11px] font-medium tracking-wide text-slate-500">
                  {{ card.label }}
                </p>
                <p
                  class="mt-1.5 font-display text-2xl font-semibold tabular-nums text-slate-800"
                  :class="card.tone === 'brand' ? 'text-brand' : ''"
                >
                  {{ card.value }}
                </p>
                <p class="mt-1 text-xs text-slate-400">
                  {{ card.sub }}
                </p>
              </div>
              <span
                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                :class="toneClass[card.tone]"
              >
                <AppIcon
                  :name="card.icon"
                  :size="18"
                />
              </span>
            </div>
          </div>
        </div>

        <!-- Tháng hiện tại -->
        <section class="overflow-hidden rounded-xl border border-brand/15 bg-gradient-to-r from-brand/[0.06] via-white to-white">
          <div class="flex flex-wrap items-end justify-between gap-4 border-b border-brand/10 px-5 py-4 sm:px-6">
            <div>
              <h2 class="font-display text-sm font-semibold text-slate-800">
                Kỳ {{ monthLabel }}
              </h2>
              <p class="mt-0.5 text-xs text-slate-500">
                Chỉ số vận hành và doanh thu trong tháng đang xem.
              </p>
            </div>
            <p class="font-display text-2xl font-bold tabular-nums text-brand">
              {{ currency(monthly.revenue_total) }}
            </p>
          </div>
          <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3 sm:p-5">
            <div
              v-for="stat in monthlyStats"
              :key="stat.key"
              class="flex items-center gap-3 rounded-lg border border-slate-100 bg-white/90 px-4 py-3 shadow-sm"
            >
              <span
                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                :class="toneClass[stat.tone]"
              >
                <AppIcon
                  :name="stat.icon"
                  :size="16"
                />
              </span>
              <div class="min-w-0">
                <p class="text-[11px] font-medium text-slate-500">
                  {{ stat.label }}
                </p>
                <p
                  class="text-sm font-semibold tabular-nums"
                  :class="stat.highlight ? 'text-brand' : 'text-slate-800'"
                >
                  {{ stat.value }}
                </p>
                <p
                  v-if="stat.sub"
                  class="text-[11px] text-slate-400"
                >
                  {{ stat.sub }}
                </p>
              </div>
            </div>
          </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
          <!-- Biểu đồ doanh thu -->
          <div class="card p-5 lg:col-span-1">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
              <div>
                <h3 class="font-display text-sm font-semibold text-slate-800">
                  Doanh thu
                </h3>
                <p class="text-xs text-slate-500">
                  {{ revenueChartSubtitle }}
                </p>
              </div>
              <div
                class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5"
                role="group"
                aria-label="Khoảng thống kê doanh thu"
              >
                <button
                  v-for="opt in CHART_GRANULARITY_OPTS"
                  :key="`rev-${opt.value}`"
                  type="button"
                  class="rounded-md px-2.5 py-1 text-[11px] font-medium transition-colors"
                  :class="revenueGranularity === opt.value ? 'bg-white text-brand shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                  @click="revenueGranularity = opt.value"
                >
                  {{ opt.label }}
                </button>
              </div>
            </div>
            <div class="h-[min(20rem,45vh)] min-h-[220px]">
              <Line
                :data="chartData"
                :options="revenueChartOptions"
              />
            </div>
          </div>

          <!-- Biểu đồ giờ + tiến độ -->
          <div class="flex flex-col gap-6">
            <div class="card p-5">
              <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h3 class="font-display text-sm font-semibold text-slate-800">
                    Giờ giảng dạy
                  </h3>
                  <p class="text-xs text-slate-500">
                    {{ hoursChartSubtitle }}
                  </p>
                </div>
                <div
                  class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5"
                  role="group"
                  aria-label="Khoảng thống kê giờ dạy"
                >
                  <button
                    v-for="opt in CHART_GRANULARITY_OPTS"
                    :key="`hrs-${opt.value}`"
                    type="button"
                    class="rounded-md px-2.5 py-1 text-[11px] font-medium transition-colors"
                    :class="hoursGranularity === opt.value ? 'bg-white text-brand shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    @click="hoursGranularity = opt.value"
                  >
                    {{ opt.label }}
                  </button>
                </div>
              </div>
              <div class="h-[min(14rem,32vh)] min-h-[180px]">
                <Bar
                  :data="hoursChartData"
                  :options="hoursChartOptions"
                />
              </div>
            </div>

            <section
              v-if="activeCourses.length"
              class="card flex-1 overflow-hidden"
            >
              <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3">
                <h3 class="font-display text-sm font-semibold text-slate-800">
                  Tiến độ khóa đang học
                </h3>
              </div>
              <ul class="divide-y divide-slate-100 px-5">
                <li
                  v-for="c in activeCourses"
                  :key="c.id"
                  class="py-3.5"
                >
                  <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <Link
                      :href="route('coaching.courses.show', { course: c.id })"
                      class="min-w-0 font-medium text-brand hover:underline"
                    >
                      <span class="mr-1.5 font-mono text-[10px] text-slate-400">{{ c.code }}</span>
                      {{ c.name }}
                    </Link>
                    <span class="shrink-0 rounded-full bg-brand/10 px-2 py-0.5 text-xs font-semibold tabular-nums text-brand">
                      {{ c.progress_percent }}%
                    </span>
                  </div>
                  <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div
                      class="h-full rounded-full bg-brand transition-all"
                      :style="{ width: `${c.progress_percent}%` }"
                    />
                  </div>
                </li>
              </ul>
            </section>
            <div
              v-else
              class="card flex flex-1 flex-col items-center justify-center gap-2 p-8 text-center"
            >
              <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                <AppIcon
                  name="rocket"
                  :size="24"
                />
              </span>
              <p class="text-sm font-medium text-slate-600">
                Chưa có khóa đang diễn ra
              </p>
              <p class="text-xs text-slate-400">
                Tạo khóa mới hoặc đổi trạng thái khóa sang «Đang học».
              </p>
            </div>
          </div>
        </div>
      </div>
    </CoachingWorkspace>
  </AppLayout>
</template>
