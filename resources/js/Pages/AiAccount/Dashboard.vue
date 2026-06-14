<script setup>
import { computed, onMounted, watch } from 'vue';
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
import { useAiExecutiveDashboard } from '@/modules/aiAccount/composables/useAiExecutiveDashboard';
import AiExecutiveSummaryStrip from '@/modules/aiAccount/components/AiExecutiveSummaryStrip.vue';
import { formatVnd } from '@/modules/aiAccount/utils/formatVnd';

ChartJS.register(
    ArcElement, CategoryScale, LinearScale,
    BarElement, LineElement, PointElement,
    Title, Tooltip, Legend, Filler,
);

defineProps({
    exchangeRate: { type: Number, default: 25500 },
});

const {
    loading,
    error,
    data,
    granularity,
    comparePreviousYear,
    load,
} = useAiExecutiveDashboard();

onMounted(load);
watch([granularity, comparePreviousYear], load);

const kpis = computed(() => data.value?.kpis ?? {});
const alerts = computed(() => data.value?.alerts ?? []);

const chartColors = ['#9A0036', '#185FA5', '#854F0B', '#534AB7', '#3B6D11', '#0ea5e9', '#f59e0b', '#64748b'];

const lineChart = computed(() => {
    const series = data.value?.cost_over_time;
    if (!series?.labels?.length) return null;
    return {
        labels: series.labels,
        datasets: (series.datasets ?? []).map((ds, i) => ({
            label: ds.label,
            data: ds.data,
            borderColor: chartColors[i % chartColors.length],
            backgroundColor: i === 0 ? 'rgba(154, 0, 54, 0.08)' : 'transparent',
            fill: i === 0,
            tension: 0.35,
            pointRadius: 3,
        })),
    };
});

const productBar = computed(() => {
    const rows = data.value?.by_product ?? [];
    return {
        labels: rows.map((r) => r.tool_name),
        datasets: [
            {
                label: 'Chi phí / tháng (VNĐ)',
                data: rows.map((r) => r.cost_monthly),
                backgroundColor: '#9A0036',
                borderRadius: 6,
            },
            {
                label: 'Số TK',
                data: rows.map((r) => r.account_count),
                backgroundColor: '#185FA5',
                borderRadius: 6,
                yAxisID: 'y1',
            },
        ],
    };
});

const budgetDonut = computed(() => {
    const rows = data.value?.budget_allocation ?? [];
    return {
        labels: rows.map((r) => r.label),
        datasets: [{
            data: rows.map((r) => r.amount),
            backgroundColor: ['#9A0036', '#f59e0b', '#94a3b8'],
            borderWidth: 0,
        }],
    };
});

const statusDonut = computed(() => {
    const rows = data.value?.account_status ?? [];
    return {
        labels: rows.map((r) => r.label),
        datasets: [{
            data: rows.map((r) => r.count),
            backgroundColor: ['#10b981', '#f59e0b', '#f43f5e', '#cbd5e1'],
            borderWidth: 0,
        }],
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } },
};

const lineOptions = {
    ...chartOptions,
    scales: {
        y: {
            ticks: {
                callback: (v) => `${Math.round(v / 1_000_000)}tr`,
            },
        },
    },
};

const productOptions = {
    ...chartOptions,
    scales: {
        y: { ticks: { callback: (v) => `${Math.round(v / 1_000_000)}tr` } },
        y1: { position: 'right', grid: { drawOnChartArea: false } },
    },
};

const top = computed(() => data.value?.top ?? {});

const GRANULARITY_OPTS = [
    { value: 'day', label: 'Ngày' },
    { value: 'month', label: 'Tháng' },
    { value: 'quarter', label: 'Quý' },
    { value: 'year', label: 'Năm' },
];
</script>

<template>
  <Head title="Dashboard AI & Chi phí" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Dashboard quản trị AI"
        subtitle="Tổng quan chi phí, ngân sách và trạng thái tài khoản — dữ liệu từ PĐX & ĐNTT"
        icon="overview"
        icon-color="brand"
      />
    </template>

    <div class="mx-auto max-w-[1600px] space-y-5">
      <div
        v-if="error"
        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
      >
        {{ error }}
      </div>

      <div
        v-if="alerts.length"
        class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3"
      >
        <div
          v-for="alert in alerts"
          :key="alert.code"
          class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm"
          :class="alert.level === 'error'
            ? 'border-rose-200 bg-rose-50 text-rose-900'
            : alert.level === 'warning'
              ? 'border-amber-200 bg-amber-50 text-amber-900'
              : 'border-sky-200 bg-sky-50 text-sky-900'"
        >
          <span aria-hidden="true">🚨</span>
          <div>
            <p class="text-slate-800">
              {{ alert.title }}
            </p>
            <p class="mt-0.5 text-xs opacity-90">
              {{ alert.message }}
            </p>
          </div>
        </div>
      </div>

      <AiExecutiveSummaryStrip
        :kpis="kpis"
        :loading="loading && !data"
      />

      <template v-if="data">
        <div class="rounded-xl border border-slate-200/80 bg-white p-5">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <h2 class="text-sm text-slate-600">
              Chi phí theo thời gian
            </h2>
            <div class="flex flex-wrap items-center gap-2">
              <select
                v-model="granularity"
                class="h-9 rounded-lg border border-slate-200 bg-white px-2 text-xs"
                aria-label="Granularity"
              >
                <option
                  v-for="g in GRANULARITY_OPTS"
                  :key="g.value"
                  :value="g.value"
                >
                  {{ g.label }}
                </option>
              </select>
              <label class="inline-flex items-center gap-1.5 text-xs text-slate-600">
                <input
                  v-model="comparePreviousYear"
                  type="checkbox"
                  class="rounded border-slate-300 text-brand"
                >
                So sánh năm trước
              </label>
            </div>
          </div>
          <div class="h-72">
            <Line
              v-if="lineChart"
              :data="lineChart"
              :options="lineOptions"
            />
          </div>
        </div>

        <div class="rounded-xl border border-slate-200/80 bg-white p-5">
          <h2 class="mb-4 border-b border-slate-100 pb-3 text-sm text-slate-600">
            Chi phí theo sản phẩm AI
          </h2>
          <div class="h-72">
            <Bar
              v-if="productBar.labels.length"
              :data="productBar"
              :options="productOptions"
            />
            <p
              v-else
              class="py-16 text-center text-sm text-slate-400"
            >
              Chưa có dữ liệu
            </p>
          </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div class="rounded-xl border border-slate-200/80 bg-white p-5">
            <h2 class="mb-4 border-b border-slate-100 pb-3 text-sm text-slate-600">
              Phân bổ ngân sách
            </h2>
            <div class="mx-auto h-56 max-w-xs">
              <Doughnut
                v-if="budgetDonut.labels.length"
                :data="budgetDonut"
                :options="chartOptions"
              />
            </div>
          </div>
          <div class="rounded-xl border border-slate-200/80 bg-white p-5">
            <h2 class="mb-4 border-b border-slate-100 pb-3 text-sm text-slate-600">
              Trạng thái tài khoản
            </h2>
            <div class="mx-auto h-56 max-w-xs">
              <Doughnut
                v-if="statusDonut.labels.length"
                :data="statusDonut"
                :options="chartOptions"
              />
            </div>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
          <div class="rounded-xl border border-slate-200/80 bg-white p-5">
            <h3 class="mb-3 border-b border-slate-100 pb-2 text-sm text-slate-600">
              Top chi phí sản phẩm
            </h3>
            <ol class="space-y-2.5 text-sm">
              <li
                v-for="(row, i) in (top.costly_products ?? []).slice(0, 10)"
                :key="row.tool_name"
                class="flex justify-between gap-3 border-b border-slate-50 pb-2 last:border-0"
              >
                <span class="text-slate-600">{{ i + 1 }}. {{ row.tool_name }}</span>
                <span class="shrink-0 tabular-nums text-brand">{{ formatVnd(row.cost_monthly) }}</span>
              </li>
            </ol>
          </div>
          <div class="rounded-xl border border-slate-200/80 bg-white p-5">
            <h3 class="mb-3 border-b border-slate-100 pb-2 text-sm text-slate-600">
              Top người dùng (nhiều TK)
            </h3>
            <ol class="space-y-2.5 text-sm">
              <li
                v-for="(row, i) in (top.users_most_accounts ?? []).slice(0, 10)"
                :key="row.user_name"
                class="flex justify-between gap-3 border-b border-slate-50 pb-2 last:border-0"
              >
                <span class="truncate text-slate-600">{{ i + 1 }}. {{ row.user_name }}</span>
                <span class="shrink-0 tabular-nums text-slate-700">{{ row.account_count }} TK</span>
              </li>
            </ol>
          </div>
          <div class="rounded-xl border border-slate-200/80 bg-white p-5">
            <h3 class="mb-3 border-b border-slate-100 pb-2 text-sm text-slate-600">
              Sắp hết hạn gần nhất
            </h3>
            <ol class="space-y-2.5 text-sm">
              <li
                v-for="row in (top.expiring_soon ?? []).slice(0, 10)"
                :key="row.id"
                class="border-b border-slate-50 pb-2 last:border-0"
              >
                <p class="text-slate-700">
                  {{ row.tool_name }}
                </p>
                <p class="mt-0.5 text-xs text-slate-500">
                  {{ row.expiry_date }} · còn {{ row.days_until_expiry }} ngày
                </p>
              </li>
            </ol>
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>
