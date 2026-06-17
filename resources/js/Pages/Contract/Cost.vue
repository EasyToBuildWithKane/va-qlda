<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import CostBreakdownChart from '@/modules/contract/components/CostBreakdownChart.vue';
import CostTrendChart from '@/modules/contract/components/CostTrendChart.vue';
import CostSummaryBar from '@/modules/contract/components/CostSummaryBar.vue';

defineProps({
    cost: { type: Object, required: true },
});

const breakdown = ref('byVendor');
const TABS = [
    { key: 'byVendor', label: 'Theo NCC' },
    { key: 'byUnit', label: 'Theo đơn vị' },
    { key: 'byCategory', label: 'Theo nhóm dịch vụ' },
];
</script>

<template>
  <Head title="Chi phí hợp đồng" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Quản lý chi phí"
        subtitle="Phân tích chi phí & dự báo ngân sách"
        icon="money"
        icon-color="brand"
      >
        <Link
          href="/contracts/dashboard"
          class="btn-ghost"
        >
          <AppIcon
            name="overview"
            :size="15"
          /> Dashboard
        </Link>
      </PageHeader>
    </template>

    <div class="mx-auto max-w-7xl px-4 py-5">
      <CostSummaryBar :cost="cost" />

      <div class="grid gap-4 lg:grid-cols-2">
        <!-- Breakdown -->
        <section class="card p-4">
          <div class="mb-3 flex items-center justify-between">
            <h2 class="font-display text-sm font-semibold text-slate-800">
              Phân tích chi phí
            </h2>
            <div class="inline-flex rounded-md border border-slate-200 bg-slate-50 p-0.5">
              <button
                v-for="t in TABS"
                :key="t.key"
                type="button"
                class="rounded px-2.5 py-1 text-xs font-medium"
                :class="breakdown === t.key ? 'bg-white text-brand shadow-sm' : 'text-slate-500'"
                @click="breakdown = t.key"
              >
                {{ t.label }}
              </button>
            </div>
          </div>
          <CostBreakdownChart :rows="cost[breakdown]" />
        </section>

        <!-- Quarter -->
        <section class="card p-4">
          <h2 class="mb-3 font-display text-sm font-semibold text-slate-800">
            Chi phí theo quý ({{ cost.this_year }})
          </h2>
          <CostTrendChart :trend="cost.byQuarter" />
        </section>
      </div>
    </div>
  </AppLayout>
</template>
