<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import StatusDonut from '@/modules/contract/components/StatusDonut.vue';
import CostTrendChart from '@/modules/contract/components/CostTrendChart.vue';
import { formatMoneyShort, expiryLabel } from '@/modules/contract/composables/useContractFormat.js';

const props = defineProps({
    metrics: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const KPI_ICON = {
    total_contracts: 'documents',
    total_value: 'budget',
    cost_this_year: 'money',
    budget_next_year: 'cost',
    total_vendors: 'vendor',
};
const KPI_TONE = {
    total_contracts: 'brand',
    total_value: 'violet',
    cost_this_year: 'sky',
    budget_next_year: 'amber',
    total_vendors: 'emerald',
};

const kpiList = computed(() => {
    const raw = props.metrics?.kpis;
    if (Array.isArray(raw)) return raw;
    if (raw && typeof raw === 'object') return Object.values(raw);
    return [];
});

const kpiCards = computed(() => kpiList.value.map((k) => ({
    key: k.key,
    label: k.label,
    value: k.format === 'currency' ? formatMoneyShort(k.value) : k.value,
    icon: KPI_ICON[k.key] ?? 'documents',
    tone: KPI_TONE[k.key] ?? 'brand',
})));

const alerts = computed(() => props.metrics.alerts || []);
const ALERT_LEVEL = {
    critical: 'bg-rose-100 text-rose-700',
    high: 'bg-amber-100 text-amber-700',
    medium: 'bg-sky-100 text-sky-700',
    low: 'bg-slate-100 text-slate-600',
};
const ALERT_TYPE_LABEL = { expired: 'Đã hết hạn', expiring: 'Sắp hết hạn', unpaid: 'Chưa thanh toán' };
</script>

<template>
  <Head title="Dashboard hợp đồng" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Dashboard hợp đồng"
        subtitle="Tổng quan vòng đời, chi phí và cảnh báo"
        icon="budget"
        icon-color="brand"
      >
        <Link
          href="/contracts"
          class="btn-ghost"
        >
          <AppIcon
            name="documents"
            :size="15"
          /> Danh mục
        </Link>
      </PageHeader>
    </template>

    <div class="mx-auto max-w-7xl px-4 py-5">
      <KpiSummaryStrip
        :cards="kpiCards"
        aria-label="Chỉ số hợp đồng"
        eyebrow="Chỉ số chính"
        heading="Tổng quan hợp đồng"
        grid-class="grid-cols-2 sm:grid-cols-3 xl:grid-cols-5"
      />

      <div class="grid gap-4 lg:grid-cols-3">
        <!-- Status donut -->
        <section class="card p-4 lg:col-span-1">
          <h2 class="mb-2 font-display text-sm font-semibold text-slate-800">
            Tình trạng hợp đồng
          </h2>
          <StatusDonut :distribution="metrics.statusDistribution" />
        </section>

        <!-- Cost trend -->
        <section class="card p-4 lg:col-span-2">
          <h2 class="mb-2 font-display text-sm font-semibold text-slate-800">
            Chi phí theo tháng (năm nay)
          </h2>
          <CostTrendChart :trend="metrics.costTrend" />
        </section>
      </div>

      <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <!-- Top vendors -->
        <section class="card p-4 lg:col-span-1">
          <h2 class="mb-3 font-display text-sm font-semibold text-slate-800">
            Top nhà cung cấp
          </h2>
          <ul class="space-y-2">
            <li
              v-for="v in metrics.topVendors"
              :key="v.vendor_id ?? 'none'"
              class="flex items-center justify-between gap-2 text-sm"
            >
              <span class="min-w-0 flex-1 truncate text-slate-700">{{ v.name }}</span>
              <span class="text-xs text-slate-400">{{ v.count }} HĐ</span>
              <span class="shrink-0 font-medium text-slate-700">{{ formatMoneyShort(v.annual_cost) }}</span>
            </li>
            <li
              v-if="!metrics.topVendors?.length"
              class="py-4 text-center text-sm text-slate-400"
            >
              Chưa có dữ liệu.
            </li>
          </ul>
        </section>

        <!-- Alerts -->
        <section class="card p-4 lg:col-span-2">
          <div class="mb-3 flex items-center justify-between">
            <h2 class="font-display text-sm font-semibold text-slate-800">
              Cảnh báo gần đến hạn
            </h2>
            <span class="text-xs text-slate-400">{{ alerts.length }} mục</span>
          </div>
          <ul class="divide-y divide-slate-100">
            <li
              v-for="a in alerts"
              :key="`${a.type}-${a.id}`"
              class="flex items-center gap-3 py-2"
            >
              <span
                class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                :class="ALERT_LEVEL[a.level]"
              >
                {{ ALERT_TYPE_LABEL[a.type] }}
              </span>
              <Link
                :href="`/contracts/${a.id}`"
                class="min-w-0 flex-1 truncate text-sm text-slate-700 hover:text-brand"
              >
                {{ a.name }}
              </Link>
              <span class="shrink-0 text-xs text-slate-400">
                {{ a.type === 'unpaid' ? '—' : expiryLabel(a.days_until_expiry) }}
              </span>
            </li>
            <li
              v-if="!alerts.length"
              class="py-6 text-center text-sm text-slate-400"
            >
              Không có cảnh báo. 🎉
            </li>
          </ul>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
