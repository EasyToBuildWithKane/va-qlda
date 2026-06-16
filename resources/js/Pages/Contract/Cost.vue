<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import CostBreakdownChart from '@/modules/contract/components/CostBreakdownChart.vue';
import CostTrendChart from '@/modules/contract/components/CostTrendChart.vue';
import { formatMoneyShort } from '@/modules/contract/composables/useContractFormat.js';

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

    <div class="mx-auto max-w-7xl px-4 py-5 space-y-4">
      <!-- Forecast cards -->
      <div class="grid gap-3 sm:grid-cols-3">
        <div class="card p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
            Dự báo ngân sách năm tới
          </p>
          <p class="mt-1 font-display text-2xl font-bold text-brand">
            {{ formatMoneyShort(cost.forecast.total) }}
          </p>
        </div>
        <div class="card p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
            Tự động gia hạn
          </p>
          <p class="mt-1 font-display text-2xl font-bold text-slate-800">
            {{ formatMoneyShort(cost.forecast.auto_renew) }}
          </p>
        </div>
        <div class="card p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
            Cần xét gia hạn thủ công
          </p>
          <p class="mt-1 font-display text-2xl font-bold text-slate-800">
            {{ formatMoneyShort(cost.forecast.manual) }}
          </p>
        </div>
      </div>

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
