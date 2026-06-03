<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import { useAiCostReport } from '@/modules/aiAccount/composables/useAiCostReport';
import { useCostReportUi } from '@/modules/aiAccount/composables/useCostReportUi';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';
import AiCostReportProposalList from '@/modules/aiAccount/components/AiCostReportProposalList.vue';
import AiPurchaseProposalFormModal from '@/modules/aiAccount/components/AiPurchaseProposalFormModal.vue';
import AiPurchaseProposalRejectModal from '@/modules/aiAccount/components/AiPurchaseProposalRejectModal.vue';
import AiPurchaseProposalApproveModal from '@/modules/aiAccount/components/AiPurchaseProposalApproveModal.vue';

const props = defineProps({
    can: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
});

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
    updateProposalNotes,
} = useAiCostReport();

const {
    proposalStatusTab,
    proposalExpanded,
    detailExpanded,
    proposalColVisible,
    groupColVisible,
    showProposalColDd,
    showGroupColDd,
    proposalColDdRef,
    groupColDdRef,
    proposalGroups,
    toggleProposalColumn,
    toggleGroupColumn,
    toggleProposalGroup,
    toggleDetail,
    expandAllProposalGroups,
    collapseAllProposalGroups,
    PROPOSAL_COLUMNS,
    COST_REPORT_GROUP_COLUMNS,
} = useCostReportUi(proposals);

const proposalFormOpen = ref(false);
const rejectOpen = ref(false);
const approveOpen = ref(false);
const rejecting = ref(null);
const approving = ref(null);

const statusTabs = [
    { key: 'all', label: 'Tất cả' },
    { key: 'pending', label: 'Chờ duyệt' },
    { key: 'approved', label: 'Đã duyệt' },
    { key: 'rejected', label: 'Từ chối' },
];

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

function openApprove(row) {
    approving.value = row;
    approveOpen.value = true;
}

async function onRejectSubmit({ rejection_reason }) {
    if (!rejecting.value?.id) return;
    await rejectProposal(rejecting.value.id, rejection_reason);
    rejectOpen.value = false;
    rejecting.value = null;
}

async function onApproveSubmit({ review_notes }) {
    if (!approving.value?.id) return;
    await approveProposal(approving.value.id, review_notes);
    approveOpen.value = false;
    approving.value = null;
}

async function onSaveNotes({ id, review_notes }) {
    await updateProposalNotes(id, review_notes);
}
</script>

<template>
  <Head title="Báo cáo chi phí AI" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Báo cáo chi phí AI"
        subtitle="Chi phí theo nhóm · đề xuất mua (gom nhóm)"
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

    <!-- Chi phí theo nhóm -->
    <div class="card mb-5 overflow-visible">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <h2 class="font-semibold text-slate-700">
          Chi phí theo nhóm
        </h2>
        <div class="flex flex-wrap items-center gap-2">
          <div
            ref="groupColDdRef"
            class="relative"
          >
            <button
              type="button"
              class="flex h-9 items-center gap-1.5 rounded-btn border border-slate-200 bg-white px-3 text-sm text-slate-600"
              @click="showGroupColDd = !showGroupColDd; showProposalColDd = false"
            >
              <AppIcon
                name="columns"
                :size="14"
              />
              Cột
            </button>
            <div
              v-if="showGroupColDd"
              class="absolute right-0 top-full z-30 mt-1.5 w-52 rounded-xl border border-slate-200 bg-white p-2 shadow-elevation-2"
            >
              <button
                v-for="col in COST_REPORT_GROUP_COLUMNS"
                :key="col.key"
                type="button"
                class="flex w-full items-center justify-between rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50"
                @click="toggleGroupColumn(col.key)"
              >
                {{ col.label }}
                <span
                  class="h-3 w-3 rounded-full"
                  :class="groupColVisible[col.key] ? 'bg-brand' : 'bg-slate-200'"
                />
              </button>
            </div>
          </div>
          <Link
            :href="route('ai-accounts.index')"
            class="btn-ghost h-9 gap-1.5 border border-slate-200 text-sm"
          >
            <AppIcon
              name="back"
              :size="16"
            />
            Danh sách TK
          </Link>
        </div>
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
        <table class="w-full min-w-[640px] text-left text-sm">
          <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-5 py-3">
                Nhóm
              </th>
              <th
                v-if="groupColVisible.counts"
                class="px-4 py-3 text-center"
              >
                Số lượng
              </th>
              <th
                v-if="groupColVisible.cost_active"
                class="px-4 py-3 text-right"
              >
                CP active
              </th>
              <th
                v-if="groupColVisible.cost_all"
                class="px-4 py-3 text-right"
              >
                CP tất cả
              </th>
              <th
                v-if="groupColVisible.share"
                class="px-4 py-3 text-right"
              >
                %
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
                  class="mr-2 inline-block h-2 w-2 rounded-full"
                  :style="{ backgroundColor: row.dot_color }"
                />
                <span class="font-medium text-slate-800">{{ row.group }}</span>
              </td>
              <td
                v-if="groupColVisible.counts"
                class="px-4 py-3 text-center text-xs text-slate-600"
              >
                <span class="tabular-nums">{{ row.total_accounts }}</span> TK
                <span class="text-emerald-600">· {{ row.active_accounts }} active</span>
                <span
                  v-if="row.expiring_soon"
                  class="text-amber-600"
                > · {{ row.expiring_soon }} sắp HH</span>
              </td>
              <td
                v-if="groupColVisible.cost_active"
                class="px-4 py-3 text-right"
              >
                <VndAmount
                  :amount="row.cost_monthly_active"
                  compact
                />
              </td>
              <td
                v-if="groupColVisible.cost_all"
                class="px-4 py-3 text-right"
              >
                <VndAmount
                  :amount="row.cost_monthly"
                  suffix=" / tháng"
                  compact
                />
              </td>
              <td
                v-if="groupColVisible.share"
                class="px-4 py-3 text-right tabular-nums"
              >
                {{ row.cost_share_percent ?? 0 }}%
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Đề xuất mua -->
    <div class="card overflow-visible">
      <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="font-semibold text-slate-700">
            Đề xuất mua AI
          </h2>
          <p class="mt-0.5 text-xs text-slate-500">
            Gom theo nhóm chức năng · mở dòng để xem chi tiết
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="btn-ghost border border-slate-200 px-2.5 py-1.5 text-xs"
            @click="expandAllProposalGroups"
          >
            Mở nhóm
          </button>
          <button
            type="button"
            class="btn-ghost border border-slate-200 px-2.5 py-1.5 text-xs"
            @click="collapseAllProposalGroups"
          >
            Đóng nhóm
          </button>
          <div
            ref="proposalColDdRef"
            class="relative"
          >
            <button
              type="button"
              class="flex h-9 items-center gap-1.5 rounded-btn border border-slate-200 bg-white px-3 text-sm text-slate-600"
              @click="showProposalColDd = !showProposalColDd; showGroupColDd = false"
            >
              <AppIcon
                name="columns"
                :size="14"
              />
              Cột hiển thị
            </button>
            <div
              v-if="showProposalColDd"
              class="absolute right-0 top-full z-30 mt-1.5 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-elevation-2"
            >
              <button
                v-for="col in PROPOSAL_COLUMNS"
                :key="col.key"
                type="button"
                class="flex w-full items-center justify-between rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50"
                @click="toggleProposalColumn(col.key)"
              >
                {{ col.label }}
                <span
                  class="h-3 w-3 rounded-full"
                  :class="proposalColVisible[col.key] ? 'bg-brand' : 'bg-slate-200'"
                />
              </button>
            </div>
          </div>
          <button
            v-if="props.can.propose"
            type="button"
            class="btn-primary h-9 gap-1.5 text-sm"
            @click="proposalFormOpen = true"
          >
            <AppIcon
              name="add"
              :size="15"
            />
            Đề xuất
          </button>
        </div>
      </div>

      <div class="flex flex-wrap gap-1 border-b border-slate-100 px-5 py-2">
        <button
          v-for="tab in statusTabs"
          :key="tab.key"
          type="button"
          class="rounded-full px-3 py-1 text-xs font-medium transition"
          :class="proposalStatusTab === tab.key
            ? 'bg-brand text-white'
            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
          @click="proposalStatusTab = tab.key"
        >
          {{ tab.label }}
          <span
            v-if="tab.key !== 'all'"
            class="ml-1 opacity-80"
          >({{ proposalCounts[tab.key] ?? 0 }})</span>
        </button>
      </div>

      <AiCostReportProposalList
        :loading="loading"
        :groups="proposalGroups"
        :expanded="proposalExpanded"
        :detail-expanded="detailExpanded"
        :col-visible="proposalColVisible"
        :can="props.can"
        @toggle-group="toggleProposalGroup"
        @toggle-detail="toggleDetail"
        @approve="openApprove"
        @reject="openReject"
        @save-notes="onSaveNotes"
      />
    </div>

    <AiPurchaseProposalFormModal
      :show="proposalFormOpen"
      :options="props.options"
      @close="proposalFormOpen = false"
      @submit="onProposalSubmit"
    />
    <AiPurchaseProposalRejectModal
      :show="rejectOpen"
      :proposal="rejecting"
      @close="rejectOpen = false"
      @submit="onRejectSubmit"
    />
    <AiPurchaseProposalApproveModal
      :show="approveOpen"
      :proposal="approving"
      @close="approveOpen = false"
      @submit="onApproveSubmit"
    />
  </AppLayout>
</template>
