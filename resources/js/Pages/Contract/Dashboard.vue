<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import DatagridSegmentedControl from '@/shared/ui/DatagridSegmentedControl.vue';
import ContractDashboardSummaryBar from '@/modules/contract/components/ContractDashboardSummaryBar.vue';
import CategoryDonut from '@/modules/contract/components/CategoryDonut.vue';
import ExpiryMonthChart from '@/modules/contract/components/ExpiryMonthChart.vue';
import TopVendorsChart from '@/modules/contract/components/TopVendorsChart.vue';
import StatusDonut from '@/modules/contract/components/StatusDonut.vue';
import { formatMoneyShort, expiryLabel } from '@/modules/contract/composables/useContractFormat.js';

const props = defineProps({
    metrics: { type: Object, required: true },
    filters: { type: Object, default: () => ({ period: 'year' }) },
    can: { type: Object, default: () => ({}) },
});

const kpiList = computed(() => {
    const raw = props.metrics?.kpis;
    if (Array.isArray(raw)) return raw;
    if (raw && typeof raw === 'object') return Object.values(raw);
    return [];
});

const PERIOD_ITEMS = [
    { key: 'month', label: 'Tháng' },
    { key: 'quarter', label: 'Quý' },
    { key: 'year', label: 'Năm' },
];
const period = ref(props.filters?.period ?? 'year');
watch(period, (p) => {
    router.get('/contracts/dashboard', { period: p }, {
        preserveState: true, preserveScroll: true, replace: true, only: ['metrics', 'filters'],
    });
});

const expiringSoon = computed(() => props.metrics.expiringSoon || []);

const EXPIRY_TONE = (days) => {
    if (days <= 7) return 'text-rose-600';
    if (days <= 15) return 'text-amber-600';
    return 'text-slate-500';
};
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
        <div class="flex items-center gap-2">
          <DatagridSegmentedControl
            v-model="period"
            :items="PERIOD_ITEMS"
            aria-label="Kỳ chi phí duy trì"
          />
          <Link
            href="/contracts"
            class="btn-ghost"
          >
            <AppIcon
              name="documents"
              :size="15"
            /> Danh mục
          </Link>
        </div>
      </PageHeader>
    </template>

    <div class="mx-auto max-w-7xl px-4 py-5">
      <ContractDashboardSummaryBar
        :kpis="kpiList"
        :period="period"
      />

      <!-- Hàng 1: donut nhóm dịch vụ | bar chi phí theo tháng hết hạn -->
      <div class="grid gap-4 lg:grid-cols-2">
        <section class="card p-4">
          <h2 class="mb-2 font-display text-sm font-semibold text-slate-800">
            Phân bổ chi phí theo nhóm dịch vụ
          </h2>
          <CategoryDonut :rows="metrics.costByCategory" />
        </section>

        <section class="card p-4">
          <h2 class="mb-2 font-display text-sm font-semibold text-slate-800">
            Chi phí theo tháng hết hạn
          </h2>
          <ExpiryMonthChart :trend="metrics.costByExpiryMonth" />
        </section>
      </div>

      <!-- Hàng 2: top NCC (cost + dòng tiền) | phân bố trạng thái -->
      <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <section class="card p-4 lg:col-span-2">
          <h2 class="mb-2 font-display text-sm font-semibold text-slate-800">
            Top 10 nhà cung cấp — chi phí & dòng tiền
          </h2>
          <TopVendorsChart :vendors="metrics.topVendors" />
        </section>

        <section class="card p-4">
          <h2 class="mb-2 font-display text-sm font-semibold text-slate-800">
            Phân bố trạng thái hợp đồng
          </h2>
          <StatusDonut :distribution="metrics.statusDistribution" />
        </section>
      </div>

      <!-- Hàng 3: datatable sắp hết hạn ≤30 ngày -->
      <section class="mt-4 card overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
          <h2 class="font-display text-sm font-semibold text-slate-800">
            Hợp đồng sắp hết hạn (≤ 30 ngày)
          </h2>
          <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">{{ expiringSoon.length }} hồ sơ</span>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="border-b border-slate-100 text-left text-[11px] uppercase tracking-wide text-slate-400">
                <th class="px-5 py-2.5 font-medium">
                  Hợp đồng
                </th>
                <th class="px-3 py-2.5 font-medium">
                  Nhà cung cấp
                </th>
                <th class="px-3 py-2.5 font-medium">
                  Ngày hết hạn
                </th>
                <th class="px-3 py-2.5 font-medium">
                  Còn lại
                </th>
                <th class="px-5 py-2.5 text-right font-medium">
                  Chi phí năm
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="c in expiringSoon"
                :key="c.id"
                class="border-b border-slate-50 last:border-0 hover:bg-slate-50/60"
              >
                <td class="px-5 py-3">
                  <Link
                    :href="`/contracts/${c.id}`"
                    class="block max-w-xs truncate font-medium text-slate-800 hover:text-brand"
                  >
                    {{ c.name }}
                  </Link>
                  <span class="text-xs text-slate-400">{{ c.code }}</span>
                </td>
                <td class="px-3 py-3 text-slate-600">
                  {{ c.vendor ?? '—' }}
                </td>
                <td class="px-3 py-3 tabular-nums text-slate-600">
                  {{ c.expiry_date ?? '—' }}
                </td>
                <td
                  class="px-3 py-3 font-medium"
                  :class="EXPIRY_TONE(c.days_until_expiry)"
                >
                  {{ expiryLabel(c.days_until_expiry) }}
                </td>
                <td class="px-5 py-3 text-right font-medium tabular-nums text-slate-700">
                  {{ formatMoneyShort(c.annual_cost) }}
                </td>
              </tr>
              <tr v-if="!expiringSoon.length">
                <td
                  colspan="5"
                  class="px-5 py-10 text-center text-sm text-slate-400"
                >
                  Không có hợp đồng nào sắp hết hạn trong 30 ngày. 🎉
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
