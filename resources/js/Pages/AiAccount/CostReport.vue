<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import { useAiCostReport } from '@/modules/aiAccount/composables/useAiCostReport';
import { exportAiProposals } from '@/modules/aiAccount/composables/useAiProposalExport';
import { useToast } from '@/shared/composables/useToast';
import { useCostReportUi } from '@/modules/aiAccount/composables/useCostReportUi';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';
import AiPurchaseProposalFormModal from '@/modules/aiAccount/components/AiPurchaseProposalFormModal.vue';
import AiPurchaseProposalRejectModal from '@/modules/aiAccount/components/AiPurchaseProposalRejectModal.vue';
import AiPurchaseProposalApproveModal from '@/modules/aiAccount/components/AiPurchaseProposalApproveModal.vue';
import { useDialog } from '@/composables/useDialog';

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
    loadProposals,
    createProposal,
    approveProposal,
    rejectProposal,
    deleteProposal,
} = useAiCostReport();

const dialog = useDialog();
const toast = useToast();

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
const FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái' },
    { key: 'type', label: 'Loại đề xuất' },
];

const VISIBLE_FILTERS_KEY = 'va-qlda.cost-report.visible-filters';

function loadVisibleFilters() {
    try {
        const saved = JSON.parse(localStorage.getItem(VISIBLE_FILTERS_KEY));
        if (saved && typeof saved === 'object') {
            return {
                status: saved.status !== false,
                type: saved.type !== false,
            };
        }
    } catch {
        /* ignore */
    }
    return { status: true, type: true };
}

const visibleFilters = ref(loadVisibleFilters());
const showFilterPanelDd = ref(false);
const showColDd = ref(false);
const showExportDd = ref(false);
const filterPanelDdRef = ref(null);
const colDdRef = ref(null);
const exportDdRef = ref(null);
const filtersLoading = ref(false);

const proposalFormOpen = ref(false);
const rejectOpen = ref(false);
const approveOpen = ref(false);
const rejecting = ref(null);
const approving = ref(null);

// KPI filter: clicking a card sets statusFilter
const activeKpi = ref(null);

const STATUS_TEXT = {
    draft: 'text-slate-600',
    submitted: 'text-blue-700',
    pending: 'text-amber-700',
    approved: 'text-emerald-700',
    rejected: 'text-rose-700',
    purchased: 'text-violet-700',
    active: 'text-teal-700',
    expired: 'text-slate-500',
};

const activeFilterCount = computed(() => {
    let n = 0;
    if (statusFilter.value !== 'all') n += 1;
    if (typeFilter.value !== 'all') n += 1;
    return n;
});

const filterSummary = computed(() => {
    const parts = [];
    if (statusFilter.value !== 'all') {
        const opt = props.options.proposal_status?.find((o) => o.value === statusFilter.value);
        parts.push(opt?.label ?? statusFilter.value);
    }
    if (typeFilter.value !== 'all') {
        const opt = props.options.proposal_type?.find((o) => o.value === typeFilter.value);
        parts.push(opt?.label ?? typeFilter.value);
    }
    return parts.join(' · ');
});

async function clearFilters() {
    statusFilter.value = 'all';
    typeFilter.value = 'all';
    activeKpi.value = null;
    search.value = '';
    await applyFilters();
}

const enabledFilterControlCount = computed(() =>
    FILTER_CONTROLS.filter((f) => visibleFilters.value[f.key]).length,
);

function persistVisibleFilters() {
    localStorage.setItem(VISIBLE_FILTERS_KEY, JSON.stringify(visibleFilters.value));
}

function onStatusFilterChange() {
    activeKpi.value = statusFilter.value === 'all' ? null : statusFilter.value;
}

function buildProposalFilterParams() {
    const params = {};
    if (statusFilter.value && statusFilter.value !== 'all') {
        params.status = statusFilter.value;
    }
    if (typeFilter.value && typeFilter.value !== 'all') {
        params.proposal_type = typeFilter.value;
    }
    const q = search.value.trim();
    if (q) params.search = q;
    return params;
}

async function applyFilters() {
    filtersLoading.value = true;
    try {
        await loadProposals(buildProposalFilterParams());
    } finally {
        filtersLoading.value = false;
    }
}

const hasFilterRow = computed(() =>
    visibleFilters.value.status || visibleFilters.value.type,
);

const displayedProposals = computed(() => proposals.value ?? []);

function openFilterPanel() {
    showFilterPanelDd.value = !showFilterPanelDd.value;
    if (showFilterPanelDd.value) {
        showColDd.value = false;
        showExportDd.value = false;
    }
}

function openCol() {
    showColDd.value = !showColDd.value;
    if (showColDd.value) {
        showFilterPanelDd.value = false;
        showExportDd.value = false;
    }
}

function openExportMenu() {
    showExportDd.value = !showExportDd.value;
    if (showExportDd.value) {
        showFilterPanelDd.value = false;
        showColDd.value = false;
    }
}

function onToolbarClickOutside(e) {
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) showFilterPanelDd.value = false;
    if (colDdRef.value && !colDdRef.value.contains(e.target)) showColDd.value = false;
    if (exportDdRef.value && !exportDdRef.value.contains(e.target)) showExportDd.value = false;
    if (groupColDdRef.value && !groupColDdRef.value.contains(e.target)) showGroupColDd.value = false;
}

const kpiCards = computed(() => {
    const pc = proposalCounts.value;
    const c = cards.value;
    return [
        { key: 'total', label: 'Tổng phiếu', value: pc.total ?? 0, icon: 'task', tone: 'text-slate-600', bg: 'bg-slate-100' },
        { key: 'pending', label: 'Chờ duyệt', value: pc.pending ?? 0, icon: 'flag', tone: 'text-amber-600', bg: 'bg-amber-50', highlight: (pc.pending ?? 0) > 0 },
        { key: 'approved', label: 'Đã duyệt', value: pc.approved ?? 0, icon: 'done', tone: 'text-emerald-600', bg: 'bg-emerald-50' },
        { key: 'rejected', label: 'Từ chối', value: pc.rejected ?? 0, icon: 'close', tone: 'text-rose-600', bg: 'bg-rose-50' },
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

let searchDebounceTimer;

watch([statusFilter, typeFilter], () => {
    applyFilters();
});

watch(search, () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => applyFilters(), 350);
});

onMounted(() => {
    load();
    document.addEventListener('mousedown', onToolbarClickOutside);
});
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));

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

async function onDeleteProposal(row) {
    const label = row.proposal_code || row.tool_name || 'phiếu này';
    const confirmed = await dialog.confirm({
        title: 'Xoá phiếu đề xuất',
        message: `Bạn có chắc muốn xoá "${label}"? Thao tác không thể hoàn tác.`,
        tone: 'danger',
        confirmText: 'Xoá',
    });
    if (!confirmed) return;
    await deleteProposal(row.id);
}

function runExport(format) {
    showExportDd.value = false;
    const rows = displayedProposals.value;
    if (!rows.length) {
        toast.warning('Không có phiếu để xuất.');
        return;
    }
    const note = [filterSummary.value, search.value.trim() ? `từ khoá «${search.value.trim()}»` : '']
        .filter(Boolean)
        .join(' · ');
    exportAiProposals({
        list: rows,
        columns: COLS,
        visibleKeys: visibleCols.value,
        filterNote: note,
        format,
    });
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
      <div class="border-b border-slate-100 px-5 py-3">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
            <div class="relative min-w-0 flex-1 basis-full sm:basis-auto sm:min-w-[280px] lg:min-w-[360px] lg:max-w-xl">
              <AppIcon
                name="search"
                :size="15"
                class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
              />
              <input
                v-model="search"
                type="text"
                placeholder="Tìm mã phiếu, sản phẩm, người đề xuất, nhà cung cấp…"
                class="input h-9 w-full pl-9 pr-8 text-sm placeholder:text-slate-400"
              >
              <button
                v-if="search"
                type="button"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                title="Xoá từ khoá"
                @click="search = ''"
              >
                <AppIcon
                  name="close"
                  :size="14"
                />
              </button>
            </div>

            <div
              ref="filterPanelDdRef"
              class="relative shrink-0"
            >
              <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-btn border transition select-none"
                :class="showFilterPanelDd
                  ? 'border-brand/40 bg-brand/5 text-brand'
                  : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-800'"
                :title="`Bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length} đang hiển thị)`"
                aria-label="Hiển thị bộ lọc trên thanh công cụ"
                @click="openFilterPanel"
              >
                <AppIcon
                  name="filter"
                  :size="16"
                />
              </button>
              <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 scale-95 -translate-y-1"
                leave-active-class="transition duration-100 ease-in"
                leave-to-class="opacity-0 scale-95 -translate-y-1"
              >
                <div
                  v-if="showFilterPanelDd"
                  class="absolute left-0 top-full z-30 mt-1.5 w-56 origin-top-left rounded-xl border border-slate-200 bg-white shadow-elevation-2 sm:left-auto sm:right-0 sm:origin-top-right"
                >
                  <div class="border-b border-slate-100 px-4 py-2.5">
                    <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Hiển thị trên thanh công cụ</span>
                  </div>
                  <div class="px-2 py-2">
                    <label
                      v-for="f in FILTER_CONTROLS"
                      :key="f.key"
                      class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-1.5 hover:bg-slate-50"
                    >
                      <input
                        v-model="visibleFilters[f.key]"
                        type="checkbox"
                        class="rounded border-slate-300 text-brand focus:ring-brand/30"
                        @change="persistVisibleFilters"
                      >
                      <span class="text-sm text-slate-700">{{ f.label }}</span>
                    </label>
                  </div>
                  <div class="border-t border-slate-100 px-4 py-2.5">
                    <p class="text-[11px] leading-snug text-slate-400">
                      Ô tìm kiếm luôn hiển thị. Bật/tắt từng bộ lọc bạn muốn thấy bên cạnh.
                    </p>
                  </div>
                </div>
              </Transition>
            </div>

            <div
              ref="colDdRef"
              class="relative shrink-0"
            >
              <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-btn border transition select-none"
                :class="showColDd
                  ? 'border-brand/40 bg-brand/5 text-brand'
                  : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-800'"
                title="Cột hiển thị"
                aria-label="Cột hiển thị"
                @click="openCol"
              >
                <AppIcon
                  name="columns"
                  :size="16"
                />
              </button>
              <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 scale-95 -translate-y-1"
                leave-active-class="transition duration-100 ease-in"
                leave-to-class="opacity-0 scale-95 -translate-y-1"
              >
                <div
                  v-if="showColDd"
                  class="absolute left-0 top-full z-30 mt-1.5 w-56 origin-top-left rounded-xl border border-slate-200 bg-white shadow-elevation-2 sm:left-auto sm:right-0 sm:origin-top-right"
                >
                  <div class="border-b border-slate-100 px-4 py-2.5">
                    <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Cột hiển thị</span>
                  </div>
                  <div class="max-h-64 overflow-y-auto px-2 py-2">
                    <label
                      v-for="col in COLS"
                      :key="col.key"
                      class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-1.5 hover:bg-slate-50"
                    >
                      <input
                        v-model="visibleCols[col.key]"
                        type="checkbox"
                        class="rounded border-slate-300 text-brand focus:ring-brand/30"
                      >
                      <span class="text-sm text-slate-700">{{ col.label }}</span>
                    </label>
                  </div>
                  <div class="border-t border-slate-100 px-4 py-2">
                    <p class="text-[11px] text-slate-400">
                      Cột «Thao tác» luôn hiển thị
                    </p>
                  </div>
                </div>
              </Transition>
            </div>
          </div>

          <div class="flex shrink-0 flex-wrap items-center gap-2">
            <div
              ref="exportDdRef"
              class="relative shrink-0"
            >
              <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-btn border border-slate-200 bg-white text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-800"
                :class="showExportDd && 'border-brand/40 bg-brand/5 text-brand'"
                title="Xuất dữ liệu"
                aria-label="Xuất CSV hoặc Excel"
                @click="openExportMenu"
              >
                <AppIcon
                  name="export"
                  :size="16"
                />
              </button>
              <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 scale-95 -translate-y-1"
                leave-active-class="transition duration-100 ease-in"
                leave-to-class="opacity-0 scale-95 -translate-y-1"
              >
                <div
                  v-if="showExportDd"
                  class="absolute right-0 top-full z-30 mt-1.5 w-44 origin-top-right rounded-xl border border-slate-200 bg-white py-1 shadow-elevation-2"
                >
                  <button
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
                    @click="runExport('csv')"
                  >
                    <AppIcon
                      name="export"
                      :size="14"
                      class="text-slate-400"
                    />
                    Xuất CSV
                  </button>
                  <button
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
                    @click="runExport('xlsx')"
                  >
                    <AppIcon
                      name="download"
                      :size="14"
                      class="text-slate-400"
                    />
                    Xuất Excel
                  </button>
                </div>
              </Transition>
            </div>
            <button
              v-if="props.can.propose"
              type="button"
              class="btn-primary h-9 gap-1.5 px-4 text-sm"
              @click="proposalFormOpen = true"
            >
              <AppIcon
                name="add"
                :size="15"
              />
              Thêm phiếu
            </button>
          </div>
        </div>

        <div
          v-if="hasFilterRow"
          class="mt-2.5 flex flex-wrap items-center gap-2 border-t border-slate-50 pt-2.5"
        >
          <select
            v-if="visibleFilters.status"
            v-model="statusFilter"
            class="input h-9 w-[min(100%,11rem)] shrink-0 text-sm sm:w-44"
            aria-label="Lọc theo trạng thái"
            :disabled="filtersLoading"
            @change="onStatusFilterChange"
          >
            <option value="all">
              Trạng thái: Tất cả ({{ proposalCounts.total ?? 0 }})
            </option>
            <option
              v-for="opt in options.proposal_status"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }} ({{ proposalCounts[opt.value] ?? 0 }})
            </option>
          </select>

          <select
            v-if="visibleFilters.type"
            v-model="typeFilter"
            class="input h-9 w-[min(100%,11rem)] shrink-0 text-sm sm:w-48"
            aria-label="Lọc theo loại đề xuất"
            :disabled="filtersLoading"
          >
            <option value="all">
              Loại: Tất cả
            </option>
            <option
              v-for="t in options.proposal_type"
              :key="t.value"
              :value="t.value"
            >
              {{ t.label }}
            </option>
          </select>

          <span
            v-if="filtersLoading"
            class="text-xs text-slate-400"
          >Đang lọc…</span>

          <template v-if="activeFilterCount > 0 || search.trim()">
            <span class="hidden h-5 w-px bg-slate-200 sm:inline" />
            <span class="text-xs text-slate-500">
              <span v-if="filterSummary">{{ filterSummary }}</span>
              <span v-if="search.trim()"><span v-if="filterSummary"> · </span>«{{ search.trim() }}»</span>
            </span>
            <button
              type="button"
              class="text-xs font-medium text-brand hover:underline"
              @click="clearFilters"
            >
              Đặt lại
            </button>
          </template>
        </div>

        <p
          v-else-if="activeFilterCount > 0 || search.trim()"
          class="mt-2.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500"
        >
          <span>Đang áp dụng<span v-if="filterSummary">: {{ filterSummary }}</span><span v-if="search.trim()"> · «{{ search.trim() }}»</span></span>
          <button
            type="button"
            class="font-medium text-brand hover:underline"
            @click="clearFilters"
          >
            Đặt lại
          </button>
        </p>
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
              v-if="displayedProposals.length === 0"
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
              v-for="row in displayedProposals"
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
                <span class="text-sm text-slate-700">{{ row.proposal_type_label ?? '—' }}</span>
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
                  class="text-sm font-medium"
                  :class="STATUS_TEXT[row.status] ?? 'text-slate-600'"
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

              <td class="px-4 py-3">
                <div class="flex items-center justify-center gap-0.5">
                  <a
                    v-if="row.export_pdf_url"
                    :href="row.export_pdf_url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-slate-500 transition hover:border-slate-200 hover:bg-slate-50 hover:text-brand"
                    title="Tải PDF"
                  >
                    <AppIcon
                      name="pdf"
                      :size="16"
                    />
                  </a>
                  <a
                    v-if="row.export_docx_url"
                    :href="row.export_docx_url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-slate-500 transition hover:border-slate-200 hover:bg-slate-50 hover:text-slate-700"
                    title="Tải DOCX"
                  >
                    <AppIcon
                      name="download"
                      :size="16"
                    />
                  </a>
                  <button
                    v-if="row.can_review && props.can.review_proposals"
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-slate-500 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700"
                    title="Duyệt phiếu"
                    @click="openApprove(row)"
                  >
                    <AppIcon
                      name="check"
                      :size="16"
                    />
                  </button>
                  <button
                    v-if="row.can_review && props.can.review_proposals"
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-slate-500 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700"
                    title="Từ chối phiếu"
                    @click="openReject(row)"
                  >
                    <AppIcon
                      name="close"
                      :size="16"
                    />
                  </button>
                  <button
                    v-if="row.can_delete"
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-slate-500 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700"
                    title="Xoá phiếu"
                    @click="onDeleteProposal(row)"
                  >
                    <AppIcon
                      name="delete"
                      :size="16"
                    />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="border-t border-slate-100 px-5 py-2.5 text-xs text-slate-400">
        {{ proposals.length }} / {{ proposalCounts.total ?? proposals.length }} phiếu
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
              class="flex h-9 items-center gap-1.5 rounded-btn border px-3 text-sm font-medium transition"
              :class="showGroupColDd
                ? 'border-brand/40 bg-brand/5 text-brand'
                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
              @click="showGroupColDd = !showGroupColDd"
            >
              <AppIcon
                name="columns"
                :size="14"
              />
              Cột hiển thị
              <AppIcon
                name="chevron-down"
                :size="13"
                class="opacity-50 transition-transform duration-150"
                :class="showGroupColDd && 'rotate-180'"
              />
            </button>
            <div
              v-if="showGroupColDd"
              class="absolute right-0 top-full z-30 mt-1.5 w-52 rounded-xl border border-slate-200 bg-white shadow-elevation-2"
            >
              <div class="border-b border-slate-100 px-4 py-2.5">
                <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Cột hiển thị</span>
              </div>
              <div class="px-2 py-2">
                <label
                  v-for="col in COST_REPORT_GROUP_COLUMNS"
                  :key="col.key"
                  class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-1.5 hover:bg-slate-50"
                >
                  <input
                    type="checkbox"
                    class="rounded border-slate-300 text-brand focus:ring-brand/30"
                    :checked="groupColVisible[col.key]"
                    @change="toggleGroupColumn(col.key)"
                  >
                  <span class="text-sm text-slate-700">{{ col.label }}</span>
                </label>
              </div>
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
