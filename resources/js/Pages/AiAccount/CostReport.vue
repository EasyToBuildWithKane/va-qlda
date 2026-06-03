<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import { useAiAccounts } from '@/modules/aiAccount/composables/useAiAccounts';
import { formatVnd } from '@/modules/aiAccount/utils/formatVnd';

const { fetchSummary } = useAiAccounts();
const loading = ref(true);
const byGroup = ref([]);
const totals = ref(null);
const cards = ref(null);

const kpiCards = computed(() => {
    const c = cards.value;
    const t = totals.value;
    if (!c && !t) return [];
    return [
        { label: 'Tổng tài khoản', value: t?.total_accounts ?? c?.total_accounts ?? 0, icon: 'account', tone: 'text-brand', bg: 'bg-brand-50' },
        { label: 'Đang hoạt động', value: t?.active_accounts ?? c?.active_accounts ?? 0, icon: 'done', tone: 'text-emerald-600', bg: 'bg-emerald-50' },
        {
            label: 'Chi phí / tháng (tất cả)',
            value: formatVnd(t?.cost_monthly ?? c?.monthly_cost_all ?? 0),
            icon: 'cost',
            tone: 'text-slate-700',
            bg: 'bg-slate-100',
            isText: true,
        },
        {
            label: 'Chi phí active / tháng',
            value: formatVnd(c?.monthly_cost_active ?? 0),
            icon: 'money',
            tone: 'text-violet-600',
            bg: 'bg-violet-50',
            isText: true,
        },
    ];
});

onMounted(async () => {
    loading.value = true;
    try {
        const data = await fetchSummary();
        byGroup.value = data.by_group ?? [];
        totals.value = data.totals ?? null;
        cards.value = data.cards ?? null;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
  <Head title="Báo cáo chi phí AI" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Báo cáo chi phí AI"
        subtitle="Tổng hợp chi phí quy đổi theo tháng theo nhóm chức năng"
        icon="performance"
        icon-color="violet"
        :badge="totals?.total_accounts ?? null"
        :back-href="route('ai-accounts.index')"
      />
    </template>

    <div
      v-if="cards"
      class="mb-5 grid grid-cols-2 gap-4 lg:grid-cols-4"
    >
      <div
        v-for="item in kpiCards"
        :key="item.label"
        class="card flex items-center gap-3 p-4"
      >
        <span
          class="grid h-10 w-10 shrink-0 place-items-center rounded-btn"
          :class="item.bg"
        >
          <AppIcon
            :name="item.icon"
            :size="20"
            :class="item.tone"
          />
        </span>
        <div class="min-w-0">
          <p class="truncate text-xs text-slate-500">
            {{ item.label }}
          </p>
          <p
            class="font-display font-bold leading-tight"
            :class="[item.tone, item.isText ? 'text-base sm:text-lg' : 'text-xl']"
          >
            {{ item.value }}
          </p>
        </div>
      </div>
    </div>

    <div class="card overflow-hidden">
      <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
        <h2 class="font-semibold text-slate-700">
          Chi phí theo nhóm
        </h2>
        <Link
          :href="route('ai-accounts.index')"
          class="btn-ghost gap-1.5 border border-slate-200 text-sm"
        >
          <AppIcon
            name="back"
            :size="16"
          />
          Danh sách tài khoản
        </Link>
      </div>

      <div
        v-if="loading"
        class="px-5 py-12 text-center text-sm text-slate-500"
      >
        Đang tải…
      </div>
      <div
        v-else
        class="overflow-x-auto"
      >
        <table class="w-full min-w-[480px] text-left text-sm">
          <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-5 py-3">
                Nhóm
              </th>
              <th class="px-5 py-3">
                Tài khoản
              </th>
              <th class="px-5 py-3">
                Active
              </th>
              <th class="px-5 py-3 text-right">
                Chi phí/tháng
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="row in byGroup"
              :key="row.group"
              class="hover:bg-slate-50/50"
            >
              <td class="px-5 py-3">
                <span
                  class="mr-2 inline-block h-2 w-2 rounded-full align-middle"
                  :style="{ backgroundColor: row.dot_color }"
                />
                <span class="font-medium text-slate-800">{{ row.group }}</span>
              </td>
              <td class="px-5 py-3 text-slate-600">
                {{ row.total_accounts }}
              </td>
              <td class="px-5 py-3 text-slate-600">
                {{ row.active_accounts }}
              </td>
              <td class="px-5 py-3 text-right font-medium text-slate-800">
                {{ formatVnd(row.cost_monthly) }}
              </td>
            </tr>
            <tr
              v-if="totals"
              class="bg-brand-50/40 font-semibold"
            >
              <td class="px-5 py-3 text-slate-800">
                TỔNG
              </td>
              <td class="px-5 py-3">
                {{ totals.total_accounts }}
              </td>
              <td class="px-5 py-3">
                {{ totals.active_accounts }}
              </td>
              <td class="px-5 py-3 text-right text-brand">
                {{ formatVnd(totals.cost_monthly) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
