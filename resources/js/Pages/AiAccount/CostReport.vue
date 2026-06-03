<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Badge from '@/shared/ui/Badge.vue';
import { useDialog } from '@/composables/useDialog';
import { useAiCostReport } from '@/modules/aiAccount/composables/useAiCostReport';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';
import AiPurchaseProposalFormModal from '@/modules/aiAccount/components/AiPurchaseProposalFormModal.vue';
import AiPurchaseProposalRejectModal from '@/modules/aiAccount/components/AiPurchaseProposalRejectModal.vue';
import { costUnitSuffix } from '@/modules/aiAccount/utils/formatVnd';

defineProps({
    can: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
});

const dialog = useDialog();
const {
    loading,
    byGroup,
    totals,
    cards,
    proposals,
    proposalCounts,
    load,
    createProposal,
    approveProposal,
    rejectProposal,
} = useAiCostReport();

const proposalFormOpen = ref(false);
const rejectOpen = ref(false);
const rejecting = ref(null);

const kpiCards = computed(() => {
    const c = cards.value;
    const t = totals.value;
    const pc = proposalCounts.value;
    if (!c && !t) return [];
    return [
        { label: 'Tổng tài khoản', value: t?.total_accounts ?? c?.total_accounts ?? 0, icon: 'account', tone: 'text-brand', bg: 'bg-brand-50' },
        { label: 'Đang hoạt động', value: t?.active_accounts ?? c?.active_accounts ?? 0, icon: 'done', tone: 'text-emerald-600', bg: 'bg-emerald-50' },
        {
            label: 'Chi phí active / tháng',
            amount: c?.monthly_cost_active ?? 0,
            icon: 'money',
            tone: 'text-violet-600',
            bg: 'bg-violet-50',
            isMoney: true,
        },
        {
            label: 'Đề xuất chờ duyệt',
            value: pc.pending ?? 0,
            icon: 'flag',
            tone: 'text-amber-600',
            bg: 'bg-amber-50',
            highlight: (pc.pending ?? 0) > 0,
        },
    ];
});

onMounted(() => load());

async function onProposalSubmit(payload) {
    await createProposal(payload);
    proposalFormOpen.value = false;
}

function openReject(row) {
    rejecting.value = row;
    rejectOpen.value = true;
}

async function onRejectSubmit({ rejection_reason }) {
    if (!rejecting.value?.id) return;
    await rejectProposal(rejecting.value.id, rejection_reason);
    rejectOpen.value = false;
    rejecting.value = null;
}

async function onApprove(row) {
    const ok = await dialog.confirm({
        title: 'Duyệt đề xuất',
        message: `Duyệt mua ${row.tool_name}? Sau khi duyệt, tạo tài khoản trên danh sách chính.`,
        confirmText: 'Duyệt',
    });
    if (!ok) return;
    await approveProposal(row.id);
}
</script>

<template>
  <Head title="Báo cáo chi phí AI" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Báo cáo chi phí AI"
        subtitle="Chi tiết chi phí theo nhóm · đề xuất mua và duyệt"
        icon="performance"
        icon-color="violet"
        :badge="totals?.total_accounts ?? null"
        :back-href="route('ai-accounts.index')"
      />
    </template>

    <div
      v-if="cards"
      class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4"
    >
      <div
        v-for="item in kpiCards"
        :key="item.label"
        class="card flex items-center gap-3 p-3.5 sm:p-4"
        :class="item.highlight ? 'ring-1 ring-amber-200' : ''"
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
        <div class="min-w-0 flex-1">
          <p class="text-xs text-slate-500">
            {{ item.label }}
          </p>
          <VndAmount
            v-if="item.isMoney"
            :amount="item.amount"
            suffix=" / tháng"
          />
          <p
            v-else
            class="font-display text-xl font-bold tabular-nums leading-tight"
            :class="item.tone"
          >
            {{ item.value }}
          </p>
        </div>
      </div>
    </div>

    <!-- Chi phí theo nhóm (chi tiết) -->
    <div class="card mb-5 overflow-hidden">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <h2 class="font-semibold text-slate-700">
          Chi phí theo nhóm (chi tiết)
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
        <table class="w-full min-w-[960px] text-left text-sm">
          <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-4 py-3">
                Nhóm
              </th>
              <th class="px-4 py-3 text-center">
                Tổng TK
              </th>
              <th class="px-4 py-3 text-center">
                Active
              </th>
              <th class="px-4 py-3 text-center">
                Sắp HH
              </th>
              <th class="px-4 py-3 text-center">
                Hết hạn
              </th>
              <th class="px-4 py-3 text-center">
                Đã huỷ
              </th>
              <th class="px-4 py-3 text-right">
                CP active/tháng
              </th>
              <th class="px-4 py-3 text-right">
                CP tất cả/tháng
              </th>
              <th class="px-4 py-3 text-right">
                Tỷ trọng
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="row in byGroup"
              :key="row.group"
              class="hover:bg-slate-50/50"
            >
              <td class="px-4 py-3">
                <span
                  class="mr-2 inline-block h-2 w-2 rounded-full align-middle"
                  :style="{ backgroundColor: row.dot_color }"
                />
                <span class="font-medium text-slate-800">{{ row.group }}</span>
              </td>
              <td class="px-4 py-3 text-center tabular-nums text-slate-600">
                {{ row.total_accounts }}
              </td>
              <td class="px-4 py-3 text-center tabular-nums text-emerald-700">
                {{ row.active_accounts }}
              </td>
              <td class="px-4 py-3 text-center tabular-nums text-amber-700">
                {{ row.expiring_soon }}
              </td>
              <td class="px-4 py-3 text-center tabular-nums text-rose-700">
                {{ row.expired }}
              </td>
              <td class="px-4 py-3 text-center tabular-nums text-slate-500">
                {{ row.cancelled }}
              </td>
              <td class="px-4 py-3 text-right">
                <VndAmount
                  :amount="row.cost_monthly_active"
                  compact
                />
              </td>
              <td class="px-4 py-3 text-right">
                <VndAmount
                  :amount="row.cost_monthly"
                  suffix=" / tháng"
                  compact
                />
              </td>
              <td class="px-4 py-3 text-right tabular-nums text-slate-600">
                {{ row.cost_share_percent ?? 0 }}%
              </td>
            </tr>
            <tr
              v-if="totals"
              class="bg-brand-50/40 font-semibold"
            >
              <td class="px-4 py-3 text-slate-800">
                TỔNG
              </td>
              <td class="px-4 py-3 text-center">
                {{ totals.total_accounts }}
              </td>
              <td class="px-4 py-3 text-center">
                {{ totals.active_accounts }}
              </td>
              <td
                colspan="3"
                class="px-4 py-3"
              />
              <td
                colspan="2"
                class="px-4 py-3 text-right"
              >
                <div class="inline-block text-right text-brand">
                  <VndAmount
                    :amount="totals.cost_monthly"
                    suffix=" / tháng"
                  />
                </div>
              </td>
              <td class="px-4 py-3 text-right">
                100%
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Đề xuất mua AI -->
    <div class="card overflow-hidden">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <div>
          <h2 class="font-semibold text-slate-700">
            Đề xuất mua AI
          </h2>
          <p class="mt-0.5 text-xs text-slate-500">
            Chờ duyệt {{ proposalCounts.pending }} · Đã duyệt {{ proposalCounts.approved }} · Từ chối {{ proposalCounts.rejected }}
          </p>
        </div>
        <button
          v-if="can.propose"
          type="button"
          class="btn-primary gap-1.5 text-sm"
          @click="proposalFormOpen = true"
        >
          <AppIcon
            name="add"
            :size="15"
          />
          Đề xuất mua
        </button>
      </div>

      <div
        v-if="loading"
        class="px-5 py-10 text-center text-sm text-slate-500"
      >
        Đang tải…
      </div>
      <div
        v-else-if="proposals.length === 0"
        class="px-5 py-12 text-center text-sm text-slate-500"
      >
        Chưa có đề xuất nào.
        <template v-if="can.propose">
          Bấm «Đề xuất mua» để gửi yêu cầu.
        </template>
      </div>
      <div
        v-else
        class="overflow-x-auto"
      >
        <table class="w-full min-w-[1100px] text-left text-sm">
          <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-4 py-3">
                Công cụ
              </th>
              <th class="px-4 py-3">
                Nhóm
              </th>
              <th class="px-4 py-3">
                License
              </th>
              <th class="px-4 py-3 text-right">
                Chi phí dự kiến
              </th>
              <th class="px-4 py-3 min-w-[12rem]">
                Lý do đề xuất
              </th>
              <th class="px-4 py-3">
                Trạng thái duyệt
              </th>
              <th class="px-4 py-3 min-w-[10rem]">
                Lý do từ chối
              </th>
              <th class="px-4 py-3">
                Người gửi
              </th>
              <th class="px-4 py-3 text-right">
                Thao tác
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="row in proposals"
              :key="row.id"
              class="align-top hover:bg-slate-50/50"
            >
              <td class="px-4 py-3 font-medium text-slate-800">
                {{ row.tool_name }}
              </td>
              <td class="px-4 py-3 text-slate-600">
                {{ row.group_function }}
              </td>
              <td class="px-4 py-3 text-slate-600">
                {{ row.license_type }}
              </td>
              <td class="px-4 py-3 text-right">
                <VndAmount :amount="row.cost_amount" />
                <p class="mt-0.5 text-xs text-slate-500">
                  {{ costUnitSuffix(row.cost_unit) }}
                  <span v-if="row.cost_monthly"> · ~{{ row.cost_monthly.toLocaleString('vi-VN') }}/tháng</span>
                </p>
              </td>
              <td class="px-4 py-3">
                <p
                  class="line-clamp-3 text-slate-600"
                  :title="row.justification"
                >
                  {{ row.justification }}
                </p>
              </td>
              <td class="px-4 py-3">
                <Badge
                  :label="row.status_label"
                  :color="row.status_color"
                />
                <p
                  v-if="row.status === 'approved' && row.reviewed_by_name"
                  class="mt-1 text-xs text-slate-500"
                >
                  {{ row.reviewed_by_name }} · {{ row.reviewed_at }}
                </p>
              </td>
              <td class="px-4 py-3">
                <div
                  v-if="row.status === 'rejected' && row.rejection_reason"
                  class="rounded-lg border border-rose-200 bg-rose-50/80 px-2.5 py-2 text-xs text-rose-900"
                >
                  {{ row.rejection_reason }}
                </div>
                <span
                  v-else
                  class="text-slate-400"
                >—</span>
              </td>
              <td class="px-4 py-3 text-slate-600">
                <p>{{ row.created_by_name }}</p>
                <p class="text-xs text-slate-400">
                  {{ row.created_at }}
                </p>
              </td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <template v-if="row.status === 'pending' && can.review_proposals">
                  <button
                    type="button"
                    class="btn-ghost mr-1 px-2 py-1 text-xs text-emerald-700"
                    @click="onApprove(row)"
                  >
                    Duyệt
                  </button>
                  <button
                    type="button"
                    class="btn-ghost px-2 py-1 text-xs text-rose-600"
                    @click="openReject(row)"
                  >
                    Từ chối
                  </button>
                </template>
                <span
                  v-else
                  class="text-xs text-slate-400"
                >—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <AiPurchaseProposalFormModal
      :show="proposalFormOpen"
      :options="options"
      @close="proposalFormOpen = false"
      @submit="onProposalSubmit"
    />

    <AiPurchaseProposalRejectModal
      :show="rejectOpen"
      :proposal="rejecting"
      @close="rejectOpen = false"
      @submit="onRejectSubmit"
    />
  </AppLayout>
</template>
