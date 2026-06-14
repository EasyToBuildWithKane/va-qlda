<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
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
} from 'chart.js';
import { Line, Bar } from 'vue-chartjs';
import { exportCoachingMonthlyWorkbook } from '@/composables/useCoachingExport';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Title, Tooltip, Legend);

const props = defineProps({
    summary: { type: Object, required: true },
    monthly: { type: Object, required: true },
    month: { type: String, required: true },
    revenueSeries: { type: Array, default: () => [] },
    activeCourses: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const chartData = computed(() => ({
    labels: props.revenueSeries.map((r) => r.month),
    datasets: [
        {
            label: 'Doanh thu (VNĐ)',
            data: props.revenueSeries.map((r) => r.revenue),
            borderColor: '#9A0036',
            backgroundColor: 'rgba(154,0,54,0.1)',
            tension: 0.3,
        },
    ],
}));

const hoursChartData = computed(() => ({
    labels: props.revenueSeries.map((r) => r.month),
    datasets: [{
        label: 'Giờ dạy',
        data: props.revenueSeries.map((r) => r.hours),
        backgroundColor: 'rgba(154,0,54,0.65)',
    }],
}));

function exportExcel() {
    exportCoachingMonthlyWorkbook({
        month: props.month,
        monthly: props.monthly,
        revenueSeries: props.revenueSeries,
    });
}

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        y: { ticks: { callback: (v) => `${(v / 1e6).toFixed(0)}M` } },
    },
};

const hoursChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
};

function vnd(n) {
    if (n == null) return '—';
    return new Intl.NumberFormat('vi-VN').format(n) + ' ₫';
}
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
        class="btn-ghost h-9 px-3 text-sm"
        @click="exportExcel"
      >
        Xuất Excel
      </button>
      <Link
        href="/coaching/courses"
        class="btn-ghost h-9 px-3 text-sm"
      >
        Danh sách khóa
      </Link>
    </PageHeader>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="card p-4">
        <p class="text-xs text-slate-500">
          Tổng khóa học
        </p>
        <p class="font-display text-2xl font-semibold text-slate-800">
          {{ summary.courses_total }}
        </p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-slate-500">
          Khóa đang diễn ra
        </p>
        <p class="font-display text-2xl font-semibold text-brand">
          {{ summary.courses_active }}
        </p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-slate-500">
          Tổng buổi học
        </p>
        <p class="font-display text-2xl font-semibold text-slate-800">
          {{ summary.sessions_total }}
        </p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-slate-500">
          Tổng giờ đào tạo
        </p>
        <p class="font-display text-2xl font-semibold text-slate-800">
          {{ summary.hours_total }}
        </p>
      </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
      <div class="card p-5">
        <h2 class="mb-3 font-display text-sm font-semibold text-slate-700">
          Thống kê tháng {{ month }}
        </h2>
        <dl class="grid grid-cols-2 gap-3 text-sm">
          <div>
            <dt class="text-slate-500">
              Buổi học
            </dt>
            <dd class="font-medium">
              {{ monthly.sessions_total }} ({{ monthly.sessions_completed }} hoàn thành)
            </dd>
          </div>
          <div>
            <dt class="text-slate-500">
              Giờ dạy
            </dt>
            <dd class="font-medium">
              {{ monthly.hours_total }}
            </dd>
          </div>
          <div>
            <dt class="text-slate-500">
              Doanh thu
            </dt>
            <dd class="font-medium text-brand">
              {{ vnd(monthly.revenue_total) }}
            </dd>
          </div>
          <div>
            <dt class="text-slate-500">
              TB / giờ
            </dt>
            <dd class="font-medium">
              {{ monthly.avg_per_hour != null ? vnd(monthly.avg_per_hour) : '—' }}
            </dd>
          </div>
          <div>
            <dt class="text-slate-500">
              TB / buổi
            </dt>
            <dd class="font-medium">
              {{ monthly.avg_per_session != null ? vnd(monthly.avg_per_session) : '—' }}
            </dd>
          </div>
        </dl>
      </div>
      <div class="card p-5">
        <h2 class="mb-3 font-display text-sm font-semibold text-slate-700">
          Doanh thu 12 tháng
        </h2>
        <div class="h-48">
          <Line
            :data="chartData"
            :options="chartOptions"
          />
        </div>
      </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
      <div class="card p-5">
        <h2 class="mb-3 font-display text-sm font-semibold text-slate-700">
          Giờ giảng dạy 12 tháng
        </h2>
        <div class="h-48">
          <Bar
            :data="hoursChartData"
            :options="hoursChartOptions"
          />
        </div>
      </div>
    </div>

    <div
      v-if="activeCourses.length"
      class="card mt-4 p-5"
    >
      <h2 class="mb-3 font-display text-sm font-semibold text-slate-700">
        Tiến độ khóa đang học
      </h2>
      <ul class="space-y-3">
        <li
          v-for="c in activeCourses"
          :key="c.id"
        >
          <div class="flex justify-between text-sm">
            <Link
              :href="`/coaching/courses/${c.id}`"
              class="font-medium text-brand hover:underline"
            >
              {{ c.name }}
            </Link>
            <span>{{ c.progress_percent }}%</span>
          </div>
          <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
            <div
              class="h-full bg-brand"
              :style="{ width: `${c.progress_percent}%` }"
            />
          </div>
        </li>
      </ul>
    </div>
  </AppLayout>
</template>
