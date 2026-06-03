<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
import ProposalRowActions from '@/modules/aiAccount/components/ProposalRowActions.vue';
import AiAccountSectionNav from '@/modules/aiAccount/components/AiAccountSectionNav.vue';
import AiAccountCrossLink from '@/modules/aiAccount/components/AiAccountCrossLink.vue';
import Badge from '@/shared/ui/Badge.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useDialog } from '@/composables/useDialog';

const FILTER_CONTROLS_DEF = [
    { key: 'status', label: 'Trạng thái', default: true },
    { key: 'type', label: 'Loại đề xuất', default: true },
    { key: 'department', label: 'Phòng ban', default: true },
    { key: 'group_function', label: 'Nhóm chức năng', default: true },
    { key: 'purchase_type', label: 'Hình thức mua', default: false },
    { key: 'cost_unit', label: 'Đơn vị chi phí', default: false },
    { key: 'vendor', label: 'Nhà cung cấp', default: false },
    { key: 'tool', label: 'Sản phẩm / công cụ', default: false },
    { key: 'date_range', label: 'Khoảng ngày đề xuất', default: true },
];

const props = defineProps({
    can: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
    proposalDefaults: { type: Object, default: () => ({}) },
    formLookups: { type: Object, default: () => ({}) },
});

const {
    loading,
    proposals,
    proposalCounts,
    proposalCountsFiltered,
    cards,
    byGroup,
    load,
    loadProposals,
    createProposal,
    updateProposal,
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
const departmentFilter = ref('all');
const groupFunctionFilter = ref('all');
const purchaseTypeFilter = ref('all');
const costUnitFilter = ref('all');
const vendorFilter = ref('all');
const toolFilter = ref('all');
const createdFromFilter = ref('');
const createdToFilter = ref('');
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
const VISIBLE_FILTERS_KEY = 'va-qlda.cost-report.visible-filters';

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel: toggleFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(FILTER_CONTROLS_DEF, VISIBLE_FILTERS_KEY);
const showColDd = ref(false);
const showExportDd = ref(false);
const filterPanelDdRef = ref(null);
const colDdRef = ref(null);
const exportDdRef = ref(null);
const filtersLoading = ref(false);

const proposalFormOpen = ref(false);
const editingProposal = ref(null);
const highlightProposalId = ref(null);
const rejectOpen = ref(false);
const approveOpen = ref(false);
const rejecting = ref(null);
const approving = ref(null);

// KPI filter: clicking a card sets statusFilter
const activeKpi = ref(null);

const departmentOptions = computed(() =>
    (props.formLookups.departments ?? []).map((d) => d.name).filter(Boolean),
);

const activeFilterCount = computed(() => {
    let n = 0;
    if (statusFilter.value !== 'all') n += 1;
    if (typeFilter.value !== 'all') n += 1;
    if (departmentFilter.value !== 'all') n += 1;
    if (groupFunctionFilter.value !== 'all') n += 1;
    if (purchaseTypeFilter.value !== 'all') n += 1;
    if (costUnitFilter.value !== 'all') n += 1;
    if (vendorFilter.value !== 'all') n += 1;
    if (toolFilter.value !== 'all') n += 1;
    if (createdFromFilter.value) n += 1;
    if (createdToFilter.value) n += 1;
    return n;
});

function labelFromOptions(options, value) {
    return options?.find((o) => o.value === value)?.label ?? value;
}

const filterSummary = computed(() => {
    const parts = [];
    if (statusFilter.value !== 'all') {
        parts.push(labelFromOptions(props.options.proposal_status, statusFilter.value));
    }
    if (typeFilter.value !== 'all') {
        parts.push(labelFromOptions(props.options.proposal_type, typeFilter.value));
    }
    if (departmentFilter.value !== 'all') {
        parts.push(departmentFilter.value);
    }
    if (groupFunctionFilter.value !== 'all') {
        parts.push(labelFromOptions(props.options.group_function, groupFunctionFilter.value));
    }
    if (purchaseTypeFilter.value !== 'all') {
        parts.push(labelFromOptions(props.options.purchase_type, purchaseTypeFilter.value));
    }
    if (costUnitFilter.value !== 'all') {
        parts.push(labelFromOptions(props.options.cost_unit, costUnitFilter.value));
    }
    if (vendorFilter.value !== 'all') {
        parts.push(vendorFilter.value);
    }
    if (toolFilter.value !== 'all') {
        parts.push(toolFilter.value);
    }
    if (createdFromFilter.value || createdToFilter.value) {
        const from = createdFromFilter.value || '…';
        const to = createdToFilter.value || '…';
        parts.push(`${from} → ${to}`);
    }
    return parts.join(' · ');
});

async function clearFilters() {
    statusFilter.value = 'all';
    typeFilter.value = 'all';
    departmentFilter.value = 'all';
    groupFunctionFilter.value = 'all';
    purchaseTypeFilter.value = 'all';
    costUnitFilter.value = 'all';
    vendorFilter.value = 'all';
    toolFilter.value = 'all';
    createdFromFilter.value = '';
    createdToFilter.value = '';
    activeKpi.value = null;
    search.value = '';
    await applyFilters();
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
    if (departmentFilter.value && departmentFilter.value !== 'all') {
        params.department = departmentFilter.value;
    }
    if (groupFunctionFilter.value && groupFunctionFilter.value !== 'all') {
        params.group_function = groupFunctionFilter.value;
    }
    if (purchaseTypeFilter.value && purchaseTypeFilter.value !== 'all') {
        params.purchase_type = purchaseTypeFilter.value;
    }
    if (costUnitFilter.value && costUnitFilter.value !== 'all') {
        params.cost_unit = costUnitFilter.value;
    }
    if (vendorFilter.value && vendorFilter.value !== 'all') {
        params.vendor = vendorFilter.value;
    }
    if (toolFilter.value && toolFilter.value !== 'all') {
        params.tool_name = toolFilter.value;
    }
    if (createdFromFilter.value) {
        params.created_from = createdFromFilter.value;
    }
    if (createdToFilter.value) {
        params.created_to = createdToFilter.value;
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

const displayedProposals = computed(() => proposals.value ?? []);

const awaitingAccountCount = computed(() =>
    (proposals.value ?? []).filter((p) => p.awaiting_account).length,
);

function rowStatusLabel(row) {
    if (row.awaiting_account) return 'Chờ lập TK';
    return row.status_label;
}

function rowStatusColor(row) {
    if (row.awaiting_account) return 'brand';
    return row.status_color;
}

function openFilterPanel() {
    toggleFilterPanel(() => {
        showColDd.value = false;
        showExportDd.value = false;
    });
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

const displayProposalCounts = computed(() =>
    (activeFilterCount.value > 0 || search.value.trim())
        ? proposalCountsFiltered.value
        : proposalCounts.value,
);

const kpiCards = computed(() => {
    const pc = displayProposalCounts.value;
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

watch(
    [
        statusFilter,
        typeFilter,
        departmentFilter,
        groupFunctionFilter,
        purchaseTypeFilter,
        costUnitFilter,
        vendorFilter,
        toolFilter,
        createdFromFilter,
        createdToFilter,
    ],
    () => {
        applyFilters();
    },
);

watch(search, () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => applyFilters(), 350);
});

async function focusProposalFromQuery() {
    const id = new URLSearchParams(window.location.search).get('proposal');
    if (!id || loading.value) return;

    const row = proposals.value.find((p) => p.id === id);
    if (!row) {
        toast.warning('Không thấy phiếu trong danh sách hiện tại. Hãy đặt lại bộ lọc hoặc tìm theo mã phiếu.');
        return;
    }

    highlightProposalId.value = id;
    await nextTick();
    document.getElementById(`proposal-row-${id}`)?.scrollIntoView({
        behavior: 'smooth',
        block: 'center',
    });
    openEditProposal(row);
}

let proposalFocusDone = false;
watch(loading, (isLoading) => {
    if (!isLoading && !proposalFocusDone && new URLSearchParams(window.location.search).get('proposal')) {
        proposalFocusDone = true;
        focusProposalFromQuery();
    }
});

onMounted(() => {
    load();
    document.addEventListener('mousedown', onToolbarClickOutside);
});
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));

function openCreateProposal() {
    editingProposal.value = null;
    proposalFormOpen.value = true;
}

function openEditProposal(row) {
    editingProposal.value = row;
    proposalFormOpen.value = true;
}

function closeProposalForm() {
    proposalFormOpen.value = false;
    editingProposal.value = null;
}

async function onProposalSubmit(payload) {
    const { id, ...body } = payload;
    if (id) {
        await updateProposal(id, body);
        closeProposalForm();
        return;
    }
    const created = await createProposal(body);
    closeProposalForm();
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
  <Head title="Quản lý AI · Phiếu đề xuất" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Quản lý AI"
        subtitle="Phiếu đề xuất mua sắm · duyệt · theo dõi chi phí dự kiến"
        icon="performance"
        icon-color="brand"
        :badge="proposalCounts.total ?? null"
      >
        <AiAccountSectionNav
          active="proposals"
          :proposals-badge="proposalCounts.pending > 0 ? proposalCounts.pending : null"
        />
      </PageHeader>
    </template>

    <AiAccountCrossLink
      direction="to-accounts"
      :account-count="cards?.total_accounts ?? 0"
      :awaiting-account-count="awaitingAccountCount"
    />

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
            <div class="flex min-w-0 flex-1 basis-full items-center gap-2 lg:basis-auto lg:min-w-0">
              <label
                for="cost-report-search"
                class="shrink-0 text-xs font-medium text-slate-500"
              >
                Tìm kiếm
              </label>
              <div class="relative min-w-0 flex-1 sm:min-w-[200px] lg:min-w-[28rem] xl:min-w-[32rem]">
                <AppIcon
                  name="search"
                  :size="15"
                  class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
                />
                <input
                  id="cost-report-search"
                  v-model="search"
                  type="search"
                  placeholder="Mã phiếu, sản phẩm, người đề xuất, nhà cung cấp…"
                  class="input h-9 w-full pl-9 pr-8 text-sm placeholder:text-slate-400"
                  autocomplete="off"
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
            </div>

            <div
              ref="filterPanelDdRef"
              class="relative shrink-0"
            >
              <button
                type="button"
                class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium transition select-none"
                :class="showFilterPanelDd
                  ? 'border-brand/40 bg-brand/5 text-brand'
                  : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-800'"
                :title="`Bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length} đang hiển thị)`"
                aria-label="Hiển thị bộ lọc trên thanh công cụ"
                @click="openFilterPanel"
              >
                <AppIcon
                  name="filter"
                  :size="15"
                />
                <span>Lọc</span>
              </button>
              <FilterVisibilityDropdown
                v-model="visibleFilters"
                :show="showFilterPanelDd"
                :controls="FILTER_CONTROLS"
                @persist="persistVisibleFilters"
              />
            </div>

            <div
              ref="colDdRef"
              class="relative shrink-0"
            >
              <button
                type="button"
                class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium transition select-none"
                :class="showColDd
                  ? 'border-brand/40 bg-brand/5 text-brand'
                  : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-800'"
                title="Cột hiển thị"
                aria-label="Cột hiển thị"
                @click="openCol"
              >
                <AppIcon
                  name="columns"
                  :size="15"
                />
                <span>Cột</span>
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
                class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-800"
                :class="showExportDd && 'border-brand/40 bg-brand/5 text-brand'"
                title="Xuất CSV hoặc Excel"
                aria-label="Xuất dữ liệu"
                @click="openExportMenu"
              >
                <AppIcon
                  name="export"
                  :size="15"
                />
                <span>Xuất</span>
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
              @click="openCreateProposal"
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
              Trạng thái: Tất cả ({{ displayProposalCounts.total ?? 0 }})
            </option>
            <option
              v-for="opt in options.proposal_status"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }} ({{ displayProposalCounts[opt.value] ?? 0 }})
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

          <select
            v-if="visibleFilters.department"
            v-model="departmentFilter"
            class="input h-9 w-[min(100%,11rem)] shrink-0 text-sm sm:w-52"
            aria-label="Lọc theo phòng ban"
            :disabled="filtersLoading"
          >
            <option value="all">
              Phòng ban: Tất cả
            </option>
            <option
              v-for="name in departmentOptions"
              :key="name"
              :value="name"
            >
              {{ name }}
            </option>
          </select>

          <select
            v-if="visibleFilters.group_function"
            v-model="groupFunctionFilter"
            class="input h-9 w-[min(100%,11rem)] shrink-0 text-sm sm:w-44"
            aria-label="Lọc theo nhóm chức năng"
            :disabled="filtersLoading"
          >
            <option value="all">
              Nhóm: Tất cả
            </option>
            <option
              v-for="g in options.group_function"
              :key="g.value"
              :value="g.value"
            >
              {{ g.label }}
            </option>
          </select>

          <select
            v-if="visibleFilters.purchase_type"
            v-model="purchaseTypeFilter"
            class="input h-9 w-[min(100%,11rem)] shrink-0 text-sm sm:w-40"
            aria-label="Lọc hình thức mua"
            :disabled="filtersLoading"
          >
            <option value="all">
              Mua: Tất cả
            </option>
            <option
              v-for="p in options.purchase_type"
              :key="p.value"
              :value="p.value"
            >
              {{ p.label }}
            </option>
          </select>

          <select
            v-if="visibleFilters.cost_unit"
            v-model="costUnitFilter"
            class="input h-9 w-[min(100%,11rem)] shrink-0 text-sm sm:w-44"
            aria-label="Lọc đơn vị chi phí"
            :disabled="filtersLoading"
          >
            <option value="all">
              Đơn vị: Tất cả
            </option>
            <option
              v-for="u in options.cost_unit"
              :key="u.value"
              :value="u.value"
            >
              {{ u.label }}
            </option>
          </select>

          <select
            v-if="visibleFilters.vendor"
            v-model="vendorFilter"
            class="input h-9 max-w-xs shrink-0 text-sm sm:min-w-[10rem]"
            aria-label="Lọc nhà cung cấp"
            :disabled="filtersLoading"
          >
            <option value="all">
              NCC: Tất cả
            </option>
            <option
              v-for="v in formLookups.vendors"
              :key="v"
              :value="v"
            >
              {{ v }}
            </option>
          </select>

          <select
            v-if="visibleFilters.tool"
            v-model="toolFilter"
            class="input h-9 max-w-xs shrink-0 text-sm sm:min-w-[10rem]"
            aria-label="Lọc sản phẩm"
            :disabled="filtersLoading"
          >
            <option value="all">
              SP: Tất cả
            </option>
            <option
              v-for="t in formLookups.tools"
              :key="t"
              :value="t"
            >
              {{ t }}
            </option>
          </select>

          <template v-if="visibleFilters.date_range">
            <input
              v-model="createdFromFilter"
              type="date"
              class="input h-9 w-[min(100%,10.5rem)] shrink-0 text-sm"
              aria-label="Từ ngày đề xuất"
              :disabled="filtersLoading"
            >
            <span class="text-xs text-slate-400">→</span>
            <input
              v-model="createdToFilter"
              type="date"
              class="input h-9 w-[min(100%,10.5rem)] shrink-0 text-sm"
              aria-label="Đến ngày đề xuất"
              :disabled="filtersLoading"
            >
          </template>

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
        class="proposal-table-wrap overflow-x-auto"
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
              :id="`proposal-row-${row.id}`"
              :key="row.id"
              class="hover:bg-slate-50/60 transition-colors"
              :class="highlightProposalId === row.id && 'bg-brand/5 ring-2 ring-inset ring-brand/25'"
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
                <Badge
                  :label="rowStatusLabel(row)"
                  :color="rowStatusColor(row)"
                />
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

              <td class="px-3 py-3">
                <ProposalRowActions
                  :row="row"
                  :can-review="props.can.review_proposals"
                  @edit="openEditProposal"
                  @approve="openApprove"
                  @reject="openReject"
                  @delete="onDeleteProposal"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="border-t border-slate-100 px-5 py-2.5 text-xs text-slate-400">
        {{ proposals.length }} / {{ displayProposalCounts.total ?? proposals.length }} phiếu
        <span
          v-if="activeFilterCount > 0 || search.trim()"
          class="text-slate-400"
        > · KPI trên: theo bộ lọc</span>
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
      :edit-proposal="editingProposal"
      :options="props.options"
      :proposal-defaults="props.proposalDefaults"
      :form-lookups="props.formLookups"
      @close="closeProposalForm"
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
