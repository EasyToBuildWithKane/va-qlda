<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import { useAiCostReport } from '@/modules/aiAccount/composables/useAiCostReport';
import { useCostReportUi } from '@/modules/aiAccount/composables/useCostReportUi';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';
import AiPurchaseProposalFormModal from '@/modules/aiAccount/components/AiPurchaseProposalFormModal.vue';
import AiPurchaseProposalRejectModal from '@/modules/aiAccount/components/AiPurchaseProposalRejectModal.vue';
import AiPurchaseProposalApproveModal from '@/modules/aiAccount/components/AiPurchaseProposalApproveModal.vue';

const props = defineProps({
    can: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
    proposalDefaults: { type: Object, default: () => ({}) },
});

const {
    loading,
    proposals,
    proposalCounts,
    cards,
    byGroup,
    load,
    createProposal,
    approveProposal,
    rejectProposal,
} = useAiCostReport();

const { groupColVisible, showGroupColDd, groupColDdRef, toggleGroupColumn, COST_REPORT_GROUP_COLUMNS } = useCostReportUi(proposals);

// ─── filters ───
const search = ref('');
const statusFilter = ref('all');
const typeFilter = ref('all');
const visibleCols = ref({
    proposal_code: true,
    created_at: true,
    proposer_name: true,
    proposer_department: true,
    proposal_type: true,
    tool_name: true,
    vendor_name: false,
    cost_amount: true,
    actual_cost: false,
    status: true,
    reviewed_by_name: false,
    reviewed_at: false,
    end_date: false,
});
const showColSelector = ref(false);
const colSelectorRef = ref(null);

const proposalFormOpen = ref(false);
const rejectOpen = ref(false);
const approveOpen = ref(false);
const rejecting = ref(null);
const approving = ref(null);

// KPI filter: clicking a card sets statusFilter
const activeKpi = ref(null);

const STATUS_COLORS = {
    draft: 'bg-slate-100 text-slate-600',
    submitted: 'bg-blue-100 text-blue-700',
    pending: 'bg-amber-100 text-amber-700',
    approved: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-rose-100 text-rose-700',
    purchased: 'bg-violet-100 text-violet-700',
    active: 'bg-teal-100 text-teal-700',
    expired: 'bg-slate-100 text-slate-500',
};

const kpiCards = computed(() => {
    const pc = proposalCounts.value;
    const c = cards.value;
    return [
        { key: 'total', label: 'Tổng phiếu', value: pc.total ?? 0, icon: 'task', tone: 'text-slate-600', bg: 'bg-slate-100' },
        { key: 'pending', label: 'Chờ duyệt', value: pc.pending ?? 0, icon: 'flag', tone: 'text-amber-600', bg: 'bg-amber-50', highlight: (pc.pending ?? 0) > 0 },
        { key: 'approved', label: 'Đã duyệt', value: pc.approved ?? 0, icon: 'done', tone: 'text-emerald-600', bg: 'bg-emerald-50' },
        { key: 'rejected', label: 'Từ chối', value: pc.rejected ?? 0, icon: 'block', tone: 'text-rose-600', bg: 'bg-rose-50' },
        { key: 'purchased', label: 'Đã mua', value: pc.purchased ?? 0, icon: 'money', tone: 'text-violet-600', bg: 'bg-violet-50' },
        { key: 'active', label: 'Đang dùng', value: pc.active ?? 0, icon: 'account', tone: 'text-teal-600', bg: 'bg-teal-50' },
        { key: 'monthly_cost', label: 'Chi phí/tháng', isMoney: true, amount: c?.monthly_cost_active ?? 0, icon: 'performance', tone: 'text-brand', bg: 'bg-brand-50' },
        { key: 'yearly_cost', label: 'Ước tính/năm', isMoney: true, amount: (c?.monthly_cost_active ?? 0) * 12, icon: 'calendar', tone: 'text-indigo-600', bg: 'bg-indigo-50' },
    ];
});

function onKpiClick(card) {
    if (card.isMoney) return;
    if (activeKpi.value === card.key) {
        activeKpi.value = null;
        statusFilter.value = 'all';
    } else {
        activeKpi.value = card.key;
        statusFilter.value = card.key === 'total' ? 'all' : card.key;
    }
}

const COLS = [
    { key: 'proposal_code', label: 'Mã phiếu' },
    { key: 'created_at', label: 'Ngày đề xuất' },
    { key: 'proposer_name', label: 'Người đề xuất' },
    { key: 'proposer_department', label: 'Phòng ban' },
    { key: 'proposal_type', label: 'Loại đề xuất' },
    { key: 'tool_name', label: 'Nội dung / Sản phẩm' },
    { key: 'vendor_name', label: 'Nhà cung cấp' },
    { key: 'cost_amount', label: 'Chi phí dự kiến' },
    { key: 'actual_cost', label: 'Chi phí thực tế' },
    { key: 'status', label: 'Trạng thái' },
    { key: 'reviewed_by_name', label: 'Người duyệt' },
    { key: 'reviewed_at', label: 'Ngày duyệt' },
    { key: 'end_date', label: 'Hạn sử dụng' },
];

const filteredProposals = computed(() => {
    let list = proposals.value ?? [];
    if (statusFilter.value && statusFilter.value !== 'all') {
        list = list.filter(p => p.status === statusFilter.value);
    }
    if (typeFilter.value && typeFilter.value !== 'all') {
        list = list.filter(p => p.proposal_type === typeFilter.value);
    }
    if (search.value.trim()) {
        const q = search.value.trim().toLowerCase();
        list = list.filter(p =>
            (p.proposal_code ?? '').toLowerCase().includes(q) ||
            (p.tool_name ?? '').toLowerCase().includes(q) ||
            (p.proposer_name ?? '').toLowerCase().includes(q) ||
            (p.vendor_name ?? '').toLowerCase().includes(q) ||
            (p.subject_about ?? '').toLowerCase().includes(q)
        );
    }
    return list;
});

onMounted(() => load());

async function onProposalSubmit(payload) {
    const created = await createProposal(payload);
    proposalFormOpen.value = false;
    if (created?.export_pdf_url) {
        window.open(created.export_pdf_url, '_blank', 'noopener');
    }
}

function openReject(row) { rejecting.value = row; rejectOpen.value = true; }
function openApprove(row) { approving.value = row; approveOpen.value = true; }

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

function exportCsv() {
    const rows = filteredProposals.value;
    if (!rows.length) return;
    const headers = COLS.filter(c => visibleCols.value[c.key]).map(c => c.label);
    const lines = rows.map(r => COLS.filter(c => visibleCols.value[c.key]).map(c => {
        const v = r[c.key] ?? '';
        return `"${String(v).replace(/"/g, '""')}"`;
    }).join(','));
    const csv = [headers.join(','), ...lines].join('\n');
    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'phieu-de-xuat.csv'; a.click();
    URL.revokeObjectURL(url);
}
</script>

<template>
  <Head title="Quản lý Phiếu Đề Xuất" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Quản lý Phiếu Đề Xuất"
        subtitle="Đề xuất mua sắm · AI Tools · SaaS · License · Phần mềm · Dịch vụ"
        icon="performance"
        icon-color="brand"
        :back-href="route('ai-accounts.index')"
      />
    </template>

    <!-- ── KPI Cards ── -->
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-8 sm:gap-3">
      <button
        v-for="card in kpiCards"
        :key="card.key"
        type="button"
        class="card flex items-center gap-3 p-3 text-left transition hover:shadow-md"
        :class="[
          card.highlight ? 'ring-1 ring-amber-300' : '',
          !card.isMoney && activeKpi === card.key ? 'ring-2 ring-brand' : '',
          card.isMoney ? 'cursor-default' : 'cursor-pointer hover:bg-brand-50/30',
        ]"
        @click="onKpiClick(card)"
      >
        <span
          class="grid h-9 w-9 shrink-0 place-items-center rounded-btn"
          :class="card.bg"
        >
          <AppIcon
            :name="card.icon"
            :size="18"
            :class="card.tone"
          />
        </span>
        <div class="min-w-0 flex-1">
          <p class="text-[11px] leading-tight text-slate-500">
            {{ card.label }}
          </p>
          <VndAmount
            v-if="card.isMoney"
            :amount="card.amount"
            class="font-display text-base font-bold"
            :class="card.tone"
          />
          <p
            v-else
            class="font-display text-lg font-bold tabular-nums leading-tight"
            :class="card.tone"
          >
            {{ card.value }}
          </p>
        </div>
      </button>
    </div>

    <!-- ── Proposals Table ── -->
    <div class="card overflow-visible">
      <!-- Toolbar -->
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5">
        <div class="flex flex-wrap items-center gap-2">
          <!-- Search -->
          <div class="relative">
            <AppIcon
              name="search"
              :size="15"
              class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400"
            />
            <input
              v-model="search"
              type="text"
              placeholder="Tìm mã phiếu, sản phẩm, người đề xuất…"
              class="h-9 rounded-btn border border-slate-200 bg-white pl-8 pr-3 text-sm text-slate-700 placeholder-slate-400 focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
              style="min-width:260px"
            >
          </div>
          <!-- Type filter -->
          <select
            v-model="typeFilter"
            class="h-9 rounded-btn border border-slate-200 bg-white px-3 text-sm text-slate-600 focus:border-brand focus:outline-none"
          >
            <option value="all">
              Tất cả loại
            </option>
            <option
              v-for="t in options.proposal_type"
              :key="t.value"
              :value="t.value"
            >
              {{ t.label }}
            </option>
          </select>
          <!-- Status tabs -->
          <div class="flex flex-wrap gap-1">
            <button
              type="button"
              class="rounded-full px-3 py-1 text-xs font-medium transition"
              :class="statusFilter === 'all' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
              @click="statusFilter = 'all'; activeKpi = null"
            >
              Tất cả
            </button>
            <button
              v-for="opt in options.proposal_status"
              :key="opt.value"
              type="button"
              class="rounded-full px-3 py-1 text-xs font-medium transition"
              :class="statusFilter === opt.value ? 'bg-brand text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
              @click="statusFilter = opt.value; activeKpi = opt.value"
            >
              {{ opt.label }}
              <span class="ml-1 opacity-75">({{ proposalCounts[opt.value] ?? 0 }})</span>
            </button>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <!-- Column selector -->
          <div
            ref="colSelectorRef"
            class="relative"
          >
            <button
              type="button"
              class="flex h-9 items-center gap-1.5 rounded-btn border border-slate-200 bg-white px-3 text-sm text-slate-600"
              @click="showColSelector = !showColSelector"
            >
              <AppIcon
                name="columns"
                :size="14"
              />
              Cột
            </button>
            <div
              v-if="showColSelector"
              class="absolute right-0 top-full z-30 mt-1.5 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-elevation-2"
            >
              <button
                v-for="col in COLS"
                :key="col.key"
                type="button"
                class="flex w-full items-center justify-between rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50"
                @click="visibleCols[col.key] = !visibleCols[col.key]"
              >
                {{ col.label }}
                <span
                  class="h-3 w-3 rounded-full"
                  :class="visibleCols[col.key] ? 'bg-brand' : 'bg-slate-200'"
                />
              </button>
            </div>
          </div>
          <!-- Export CSV -->
          <button
            type="button"
            class="flex h-9 items-center gap-1.5 rounded-btn border border-slate-200 bg-white px-3 text-sm text-slate-600 hover:bg-slate-50"
            @click="exportCsv"
          >
            <AppIcon
              name="export"
              :size="14"
            />
            Xuất
          </button>
          <!-- Add -->
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
            Thêm Phiếu Đề Xuất
          </button>
        </div>
      </div>

      <!-- Table -->
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
        <table class="w-full min-w-[900px] text-left text-sm">
          <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th
                v-if="visibleCols.proposal_code"
                class="px-4 py-3"
              >
                Mã phiếu
              </th>
              <th
                v-if="visibleCols.created_at"
                class="px-4 py-3"
              >
                Ngày đề xuất
              </th>
              <th
                v-if="visibleCols.proposer_name"
                class="px-4 py-3"
              >
                Người đề xuất
              </th>
              <th
                v-if="visibleCols.proposer_department"
                class="px-4 py-3"
              >
                Phòng ban
              </th>
              <th
                v-if="visibleCols.proposal_type"
                class="px-4 py-3"
              >
                Loại
              </th>
              <th
                v-if="visibleCols.tool_name"
                class="px-4 py-3"
              >
                Sản phẩm / Nội dung
              </th>
              <th
                v-if="visibleCols.vendor_name"
                class="px-4 py-3"
              >
                Nhà cung cấp
              </th>
              <th
                v-if="visibleCols.cost_amount"
                class="px-4 py-3 text-right"
              >
                Chi phí DK
              </th>
              <th
                v-if="visibleCols.actual_cost"
                class="px-4 py-3 text-right"
              >
                Thực tế
              </th>
              <th
                v-if="visibleCols.status"
                class="px-4 py-3"
              >
                Trạng thái
              </th>
              <th
                v-if="visibleCols.reviewed_by_name"
                class="px-4 py-3"
              >
                Người duyệt
              </th>
              <th
                v-if="visibleCols.reviewed_at"
                class="px-4 py-3"
              >
                Ngày duyệt
              </th>
              <th
                v-if="visibleCols.end_date"
                class="px-4 py-3"
              >
                Hạn dùng
              </th>
              <th class="px-4 py-3 text-center">
                Thao tác
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-if="filteredProposals.length === 0"
              class="text-center"
            >
              <td
                :colspan="COLS.filter(c => visibleCols[c.key]).length + 1"
                class="py-12 text-sm text-slate-400"
              >
                Không có phiếu đề xuất nào.
              </td>
            </tr>
            <tr
              v-for="row in filteredProposals"
              :key="row.id"
              class="hover:bg-slate-50/60"
            >
              <td
                v-if="visibleCols.proposal_code"
                class="px-4 py-3"
              >
                <span class="font-mono text-xs font-semibold text-brand">{{ row.proposal_code ?? '—' }}</span>
              </td>
              <td
                v-if="visibleCols.created_at"
                class="px-4 py-3 text-xs text-slate-500"
              >
                {{ row.created_at ? row.created_at.slice(0, 10) : '—' }}
              </td>
              <td
                v-if="visibleCols.proposer_name"
                class="px-4 py-3"
              >
                <div class="font-medium text-slate-800">
                  {{ row.proposer_name }}
                </div>
                <div
                  v-if="row.proposer_position"
                  class="text-xs text-slate-400"
                >
                  {{ row.proposer_position }}
                </div>
              </td>
              <td
                v-if="visibleCols.proposer_department"
                class="px-4 py-3 text-xs text-slate-600"
              >
                {{ row.proposer_department ?? '—' }}
              </td>
              <td
                v-if="visibleCols.proposal_type"
                class="px-4 py-3"
              >
                <span
                  v-if="row.proposal_type_label"
                  class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"
                >
                  {{ row.proposal_type_label }}
                </span>
                <span
                  v-else
                  class="text-xs text-slate-400"
                >—</span>
              </td>
              <td
                v-if="visibleCols.tool_name"
                class="max-w-[200px] px-4 py-3"
              >
                <div
                  class="truncate font-medium text-slate-800"
                  :title="row.tool_name"
                >
                  {{ row.tool_name }}
                </div>
                <div
                  v-if="row.subject_about"
                  class="truncate text-xs text-slate-400"
                  :title="row.subject_about"
                >
                  {{ row.subject_about }}
                </div>
              </td>
              <td
                v-if="visibleCols.vendor_name"
                class="px-4 py-3 text-sm text-slate-600"
              >
                {{ row.vendor_name ?? '—' }}
              </td>
              <td
                v-if="visibleCols.cost_amount"
                class="px-4 py-3 text-right"
              >
                <VndAmount
                  :amount="row.cost_amount"
                  compact
                  class="text-sm"
                />
                <div class="text-[11px] text-slate-400">
                  / {{ row.cost_unit_label }}
                </div>
              </td>
              <td
                v-if="visibleCols.actual_cost"
                class="px-4 py-3 text-right"
              >
                <VndAmount
                  v-if="row.actual_cost"
                  :amount="row.actual_cost"
                  compact
                  class="text-sm"
                />
                <span
                  v-else
                  class="text-xs text-slate-400"
                >—</span>
              </td>
              <td
                v-if="visibleCols.status"
                class="px-4 py-3"
              >
                <span
                  class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  :class="STATUS_COLORS[row.status] ?? 'bg-slate-100 text-slate-600'"
                >
                  {{ row.status_label }}
                </span>
              </td>
              <td
                v-if="visibleCols.reviewed_by_name"
                class="px-4 py-3 text-xs text-slate-600"
              >
                {{ row.reviewed_by_name ?? '—' }}
              </td>
              <td
                v-if="visibleCols.reviewed_at"
                class="px-4 py-3 text-xs text-slate-500"
              >
                {{ row.reviewed_at ? row.reviewed_at.slice(0, 10) : '—' }}
              </td>
              <td
                v-if="visibleCols.end_date"
                class="px-4 py-3 text-xs text-slate-500"
              >
                {{ row.end_date ?? '—' }}
              </td>

              <!-- Actions -->
              <td class="px-4 py-3">
                <div class="flex items-center justify-center gap-1">
                  <a
                    :href="row.export_pdf_url"
                    target="_blank"
                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-500 hover:bg-rose-50 hover:text-rose-600"
                    title="Xuất PDF"
                  >
                    <AppIcon
                      name="pdf"
                      :size="15"
                    />
                  </a>
                  <a
                    :href="row.export_docx_url"
                    target="_blank"
                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-500 hover:bg-blue-50 hover:text-blue-600"
                    title="Xuất DOCX"
                  >
                    <AppIcon
                      name="download"
                      :size="15"
                    />
                  </a>
                  <button
                    v-if="row.can_review && props.can.review_proposals"
                    type="button"
                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-500 hover:bg-emerald-50 hover:text-emerald-600"
                    title="Duyệt"
                    @click="openApprove(row)"
                  >
                    <AppIcon
                      name="done"
                      :size="15"
                    />
                  </button>
                  <button
                    v-if="row.can_review && props.can.review_proposals"
                    type="button"
                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-500 hover:bg-rose-50 hover:text-rose-600"
                    title="Từ chối"
                    @click="openReject(row)"
                  >
                    <AppIcon
                      name="block"
                      :size="15"
                    />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="border-t border-slate-100 px-5 py-2.5 text-xs text-slate-400">
        {{ filteredProposals.length }} / {{ proposals.length }} phiếu
      </div>
    </div>

    <!-- ── Cost by Group (collapsible) ── -->
    <div class="card mt-5 overflow-visible">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <h2 class="font-semibold text-slate-700">
          Chi phí AI theo nhóm
        </h2>
        <div class="flex items-center gap-2">
          <div
            ref="groupColDdRef"
            class="relative"
          >
            <button
              type="button"
              class="flex h-9 items-center gap-1.5 rounded-btn border border-slate-200 bg-white px-3 text-sm text-slate-600"
              @click="showGroupColDd = !showGroupColDd"
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
      <div class="overflow-x-auto">
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

    <!-- ── Modals ── -->
    <AiPurchaseProposalFormModal
      :show="proposalFormOpen"
      :options="props.options"
      :proposal-defaults="props.proposalDefaults"
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
