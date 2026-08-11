<script setup>
import { computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useAiCostReport } from '@/modules/aiAccount/composables/useAiCostReport';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';

defineProps({
    can: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
    exchangeRate: { type: Number, default: 25500 },
});

const { loading, byGroup, totals, cards, load } = useAiCostReport();

onMounted(() => {
    load();
});

const monthlyTotal = computed(() => cards.value?.monthly_cost_all ?? totals.value?.cost_monthly ?? 0);
const accountTotal = computed(() => cards.value?.total_accounts ?? totals.value?.total_accounts ?? 0);
</script>

<template>
  <Head title="Chi phí AI" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Chi phí AI"
        subtitle="Tổng hợp chi phí theo nhóm chức năng từ tài khoản AI"
        icon="budget"
        icon-color="brand"
        :badge="accountTotal || null"
      >
        <Link
          :href="route('ai-accounts.index')"
          class="btn-ghost inline-flex h-9 items-center gap-1.5 border border-slate-200 px-3 text-xs font-medium"
        >
          <AppIcon
            name="account"
            :size="15"
          />
          Tài khoản AI
        </Link>
      </PageHeader>
    </template>

    <section
      class="mb-5 rounded-card border border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white p-5 shadow-sm"
      aria-label="Tóm tắt chi phí AI"
    >
      <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
        Thống kê
      </p>
      <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            Tổng TK
          </p>
          <p class="mt-1 font-display text-2xl tabular-nums text-slate-900">
            {{ accountTotal }}
          </p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            Đang hoạt động
          </p>
          <p class="mt-1 font-display text-2xl tabular-nums text-emerald-700">
            {{ cards?.active_accounts ?? 0 }}
          </p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            Sắp / hết hạn
          </p>
          <p class="mt-1 font-display text-2xl tabular-nums text-amber-700">
            {{ (cards?.expiring_soon ?? 0) + (cards?.expired ?? 0) }}
          </p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            Chi phí / tháng
          </p>
          <p class="mt-1 font-display text-xl tabular-nums text-brand">
            <VndAmount :amount="monthlyTotal" />
          </p>
        </div>
      </div>
    </section>

    <div class="card overflow-hidden shadow-sm">
      <div class="border-b border-slate-100 px-5 py-4">
        <h2 class="text-sm font-semibold text-slate-800">
          Chi phí theo nhóm
        </h2>
        <p class="mt-0.5 text-xs text-slate-500">
          Quy đổi về chi phí tháng theo đơn vị trên từng tài khoản.
        </p>
      </div>

      <div
        v-if="loading"
        class="px-5 py-14 text-center text-sm text-slate-500"
      >
        Đang tải…
      </div>
      <div
        v-else-if="byGroup.length === 0"
        class="px-5 py-14 text-center text-sm text-slate-500"
      >
        Chưa có tài khoản AI để tính chi phí.
      </div>
      <div
        v-else
        class="overflow-x-auto"
      >
        <table class="w-full min-w-[640px] text-left text-sm">
          <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-5 py-3">
                Nhóm
              </th>
              <th class="px-5 py-3">
                Tài khoản
              </th>
              <th class="px-5 py-3">
                Hoạt động
              </th>
              <th class="px-5 py-3">
                Sắp hết hạn
              </th>
              <th class="px-5 py-3">
                Hết hạn
              </th>
              <th class="px-5 py-3">
                Chi phí / tháng
              </th>
              <th class="px-5 py-3">
                Tỷ trọng
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="row in byGroup"
              :key="row.group"
              class="hover:bg-slate-50/70"
            >
              <td class="px-5 py-3">
                <span class="inline-flex items-center gap-2 font-medium text-slate-800">
                  <span
                    class="h-2.5 w-2.5 rounded-full"
                    :style="{ backgroundColor: row.dot_color }"
                  />
                  {{ row.group_label }}
                </span>
              </td>
              <td class="px-5 py-3 tabular-nums">
                {{ row.total_accounts }}
              </td>
              <td class="px-5 py-3 tabular-nums text-emerald-700">
                {{ row.active_accounts }}
              </td>
              <td class="px-5 py-3 tabular-nums text-amber-700">
                {{ row.expiring_soon }}
              </td>
              <td class="px-5 py-3 tabular-nums text-rose-700">
                {{ row.expired }}
              </td>
              <td class="px-5 py-3">
                <VndAmount :amount="row.cost_monthly" />
              </td>
              <td class="px-5 py-3 tabular-nums text-slate-600">
                {{ row.cost_share_percent ?? 0 }}%
              </td>
            </tr>
          </tbody>
          <tfoot class="border-t border-slate-200 bg-slate-50/80">
            <tr>
              <td
                class="px-5 py-3 font-semibold text-slate-800"
                colspan="5"
              >
                Tổng
              </td>
              <td class="px-5 py-3 font-semibold text-brand">
                <VndAmount :amount="monthlyTotal" />
              </td>
              <td class="px-5 py-3 tabular-nums text-slate-500">
                100%
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
