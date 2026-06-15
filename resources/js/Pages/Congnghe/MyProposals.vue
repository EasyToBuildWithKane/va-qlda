<script setup>
import {
    computed, onBeforeUnmount, onMounted, reactive, ref, watch,
} from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import CongnghePageShell from './partials/CongnghePageShell.vue';
import CongngheMyProposalsSummaryBar from './partials/CongngheMyProposalsSummaryBar.vue';
import CongngheMyProposalDetailModal from './partials/CongngheMyProposalDetailModal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import FilterDatePicker from '@/shared/ui/FilterDatePicker.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import {
    acknowledgementStatus,
    attachmentCountText,
    departmentText,
    emailPcnStatus,
    referenceCodeLabel,
    submitterEmailText,
    submitterNameText,
    submittedDateText,
} from './partials/congngheProposalDisplay.js';

const PROPOSAL_CREATE_HREF = '/congnghe/de-xuat';

const PER_PAGE_OPTIONS = [10, 15, 20, 30];
const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái', default: false },
    { key: 'department', label: 'Phòng ban', default: false },
    { key: 'date_range', label: 'Ngày gửi', default: false },
    { key: 'email_sent', label: 'Email PCN', default: false },
    { key: 'acknowledged', label: 'Tiếp nhận', default: false },
    { key: 'has_attachments', label: 'File đính kèm', default: false },
];

const STATUS_TONE = {
    new: 'violet',
    triaged: 'sky',
    in_progress: 'amber',
    done: 'emerald',
    rejected: 'slate',
};

const props = defineProps({
    proposals: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
});

const filterPanelDdRef = ref(null);
const perPage = ref(Number(props.filters.per_page) || props.proposals.meta?.per_page || 15);
const detailOpen = ref(false);
const activeProposal = ref(null);

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
} = useVisibleFilterControls(FILTER_CONTROLS, 'va-qlda.congnghe-my-proposals.filters.v2');

const filterForm = reactive({
    status: props.filters.status ?? '',
    department: props.filters.department ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    email_sent: props.filters.email_sent ?? '',
    acknowledged: props.filters.acknowledged ?? '',
    has_attachments: props.filters.has_attachments ?? '',
    q: props.filters.q ?? '',
});

const activeFilterCount = computed(() => {
    let n = 0;
    if (filterForm.status) n += 1;
    if (filterForm.department) n += 1;
    if (filterForm.from || filterForm.to) n += 1;
    if (filterForm.email_sent) n += 1;
    if (filterForm.acknowledged) n += 1;
    if (filterForm.has_attachments) n += 1;
    if (filterForm.q?.trim()) n += 1;
    return n;
});

const listBadge = computed(() => props.summary.total ?? null);

function clearFilters() {
    filterForm.status = '';
    filterForm.department = '';
    filterForm.from = '';
    filterForm.to = '';
    filterForm.email_sent = '';
    filterForm.acknowledged = '';
    filterForm.has_attachments = '';
    filterForm.q = '';
    navigate(true);
}

function routeParams(resetPage = false) {
    const params = {
        status: filterForm.status || undefined,
        department: filterForm.department || undefined,
        from: filterForm.from || undefined,
        to: filterForm.to || undefined,
        email_sent: filterForm.email_sent || undefined,
        acknowledged: filterForm.acknowledged || undefined,
        has_attachments: filterForm.has_attachments || undefined,
        q: filterForm.q || undefined,
        per_page: perPage.value,
    };
    if (resetPage) params.page = 1;
    return params;
}

function navigate(resetPage = false) {
    router.get(route('congnghe.proposal.mine'), routeParams(resetPage), {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
}

function onQuickFilter(payload) {
    filterForm.status = payload.status ?? '';
}

let qTimer = null;
watch(() => filterForm.q, () => {
    clearTimeout(qTimer);
    qTimer = setTimeout(() => navigate(true), 350);
});

watch(
    () => [
        filterForm.status,
        filterForm.department,
        filterForm.from,
        filterForm.to,
        filterForm.email_sent,
        filterForm.acknowledged,
        filterForm.has_attachments,
    ],
    () => navigate(true),
);
watch(perPage, () => navigate(true));

function onToolbarClickOutside(e) {
    if (e.target.closest?.('[data-filter-visibility-panel]')) return;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));

function openDetail(row) {
    activeProposal.value = row;
    detailOpen.value = true;
}

function closeDetail() {
    activeProposal.value = null;
    detailOpen.value = false;
}

function submitterName(row) {
    return submitterNameText(row.submitter_name);
}

function submitterEmail(row) {
    return submitterEmailText(row.submitter_email);
}

function departmentLabel(row) {
    return departmentText(row.department);
}

function emailPcnLabel(row) {
    return emailPcnStatus(row);
}

function ackLabel(row) {
    return acknowledgementStatus(row);
}
</script>

<template>
  <Head title="Đề xuất đã gửi" />

  <CongnghePageShell>
    <div class="relative z-20 mx-auto max-w-6xl">
      <header class="mb-8 flex flex-col gap-4 sm:mb-10 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0 text-center sm:text-left">
          <Link
            :href="route('congnghe')"
            class="inline-flex items-center gap-1.5 text-xs font-medium text-white/50 transition hover:text-white/80"
          >
            <AppIcon
              name="chevron-left"
              :size="14"
            />
            Cổng Phòng Công nghệ
          </Link>
          <p class="mt-3 font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-cyan-200/55">
            Theo dõi đề xuất
          </p>
          <h1 class="mt-2 font-display text-2xl font-bold text-white sm:text-3xl">
            Đề xuất đã gửi
            <span
              v-if="listBadge"
              class="ml-2 align-middle font-mono text-lg font-semibold text-cyan-200/70"
            >({{ listBadge }})</span>
          </h1>
          <p class="mt-2 max-w-xl text-sm text-white/60">
            Theo dõi trạng thái tiếp nhận và email hệ thống cho từng đề xuất bạn đã gửi.
          </p>
        </div>
        <Link
          :href="PROPOSAL_CREATE_HREF"
          class="relative z-20 inline-flex h-10 shrink-0 items-center justify-center gap-1.5 self-center rounded-xl border border-brand/45 bg-brand/25 px-4 text-sm font-semibold text-white shadow-[0_4px_20px_-6px_rgba(154,0,54,0.65)] transition hover:bg-brand/35 sm:self-auto"
        >
          <AppIcon
            name="add"
            :size="15"
          />
          Gửi đề xuất mới
        </Link>
      </header>

      <CongngheMyProposalsSummaryBar
        :summary="summary"
        :active-status="filterForm.status"
        @quick-filter="onQuickFilter"
      />

      <div class="cn-portal-datagrid overflow-hidden rounded-2xl border border-white/10 bg-[#0a0c16]/90 shadow-[0_8px_40px_-12px_rgba(0,0,0,0.55)] backdrop-blur-xl">
        <div class="border-b border-white/10">
          <div class="px-5 py-4">
            <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
              <div class="min-w-0 w-full basis-full lg:flex-1 lg:basis-auto">
                <DatagridToolbarSearch
                  v-model="filterForm.q"
                  input-id="cn-my-proposals-q"
                  hide-label
                  stretch
                  inline-actions
                  input-height="h-10"
                  placeholder="Tìm mã, tiêu đề, người gửi, email, phòng ban…"
                  aria-label="Tìm đề xuất của tôi"
                />
              </div>
              <div
                ref="filterPanelDdRef"
                class="relative shrink-0"
              >
                <DatagridToolbarActionButton
                  icon="filter"
                  :active="showFilterPanelDd"
                  :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
                  @click="openFilterPanel()"
                >
                  Lọc
                </DatagridToolbarActionButton>
                <FilterVisibilityDropdown
                  v-model="visibleFilters"
                  :show="showFilterPanelDd"
                  :anchor-ref="filterPanelDdRef"
                  :controls="FILTER_CONTROLS"
                  input-id-prefix="cn-my-proposal-filter-vis"
                  @persist="persistVisibleFilters"
                />
              </div>
            </div>
          </div>

          <div
            v-if="hasFilterRow"
            class="grid grid-cols-1 gap-3 border-t border-white/10 px-5 py-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
          >
            <DatagridFilterField v-if="visibleFilters.status">
              <select
                v-model="filterForm.status"
                :class="FILTER_CONTROL_CLASS"
              >
                <option value="">
                  Trạng thái
                </option>
                <option
                  v-for="opt in options.statuses"
                  :key="opt.value"
                  :value="opt.value"
                >
                  {{ opt.label }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.department">
              <select
                v-model="filterForm.department"
                :class="FILTER_CONTROL_CLASS"
              >
                <option value="">
                  Phòng ban
                </option>
                <option
                  v-for="dept in options.departments"
                  :key="dept"
                  :value="dept"
                >
                  {{ dept }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.email_sent">
              <select
                v-model="filterForm.email_sent"
                :class="FILTER_CONTROL_CLASS"
              >
                <option value="">
                  Email PCN
                </option>
                <option value="1">
                  Đã gửi email
                </option>
                <option value="0">
                  Chưa gửi email
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.acknowledged">
              <select
                v-model="filterForm.acknowledged"
                :class="FILTER_CONTROL_CLASS"
              >
                <option value="">
                  Tiếp nhận
                </option>
                <option value="1">
                  Đã ghi nhận
                </option>
                <option value="0">
                  Chưa ghi nhận
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.has_attachments">
              <select
                v-model="filterForm.has_attachments"
                :class="FILTER_CONTROL_CLASS"
              >
                <option value="">
                  File đính kèm
                </option>
                <option value="1">
                  Có file
                </option>
                <option value="0">
                  Không có file
                </option>
              </select>
            </DatagridFilterField>

            <div
              v-if="visibleFilters.date_range"
              class="min-w-0 w-full sm:col-span-2 xl:col-span-2"
            >
              <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:gap-3">
                <FilterDatePicker
                  v-model="filterForm.from"
                  placeholder="Từ ngày"
                  :max-date="filterForm.to || null"
                />
                <FilterDatePicker
                  v-model="filterForm.to"
                  placeholder="Đến ngày"
                  :min-date="filterForm.from || null"
                />
              </div>
            </div>

            <div
              v-if="activeFilterCount"
              class="col-span-full flex justify-end pt-0.5"
            >
              <button
                type="button"
                class="inline-flex h-10 items-center px-2 text-xs font-medium text-brand hover:underline"
                @click="clearFilters"
              >
                Đặt lại bộ lọc
              </button>
            </div>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="border-b border-white/10 bg-white/[0.03] text-xs font-semibold uppercase tracking-wide text-white/45">
              <tr>
                <th class="px-5 py-3">
                  Mã
                </th>
                <th class="px-5 py-3">
                  Tiêu đề
                </th>
                <th class="px-5 py-3">
                  Người gửi
                </th>
                <th class="px-5 py-3">
                  Phòng ban
                </th>
                <th class="px-5 py-3">
                  Trạng thái
                </th>
                <th class="px-5 py-3">
                  Tiếp nhận
                </th>
                <th class="px-5 py-3">
                  Email PCN
                </th>
                <th class="px-5 py-3">
                  File
                </th>
                <th class="px-5 py-3">
                  Ngày gửi
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/[0.06]">
              <tr
                v-for="row in proposals.data"
                :key="row.id"
                class="transition hover:bg-white/[0.04]"
              >
                <td class="px-5 py-3 font-mono text-xs text-white/55">
                  <button
                    type="button"
                    class="font-semibold text-brand hover:underline"
                    @click="openDetail(row)"
                  >
                    {{ referenceCodeLabel(row.reference_code) }}
                  </button>
                </td>
                <td class="max-w-[220px] px-5 py-3">
                  <button
                    type="button"
                    class="line-clamp-2 text-left font-medium text-white/90 hover:text-brand"
                    @click="openDetail(row)"
                  >
                    {{ row.title }}
                  </button>
                </td>
                <td class="px-5 py-3">
                  <div class="font-medium text-white/85">
                    {{ submitterName(row) }}
                  </div>
                  <div class="text-xs text-white/45">
                    {{ submitterEmail(row) }}
                  </div>
                </td>
                <td class="px-5 py-3 text-white/60">
                  {{ departmentLabel(row) }}
                </td>
                <td class="px-5 py-3">
                  <Badge
                    :tone="STATUS_TONE[row.status?.value] ?? 'slate'"
                    size="sm"
                  >
                    {{ row.status?.label }}
                  </Badge>
                </td>
                <td class="px-5 py-3">
                  <Badge
                    :tone="ackLabel(row).tone"
                    size="sm"
                  >
                    {{ ackLabel(row).label }}
                  </Badge>
                </td>
                <td class="px-5 py-3">
                  <Badge
                    :tone="emailPcnLabel(row).tone"
                    size="sm"
                  >
                    {{ emailPcnLabel(row).label }}
                  </Badge>
                  <p class="mt-1 text-[11px] leading-snug text-white/45">
                    {{ emailPcnLabel(row).detail }}
                  </p>
                </td>
                <td class="px-5 py-3 text-sm text-white/60">
                  {{ attachmentCountText(row.attachments_count) }}
                </td>
                <td class="px-5 py-3 tabular-nums text-white/60">
                  {{ submittedDateText(row.created_at) }}
                </td>
              </tr>
              <tr v-if="!proposals.data?.length">
                <td
                  colspan="9"
                  class="px-5 py-12 text-center text-white/50"
                >
                  Bạn chưa gửi đề xuất nào.
                  <Link
                    :href="PROPOSAL_CREATE_HREF"
                    class="ml-1 font-semibold text-brand hover:underline"
                  >
                    Gửi đề xuất mới
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="cn-portal-datagrid__footer">
          <DatagridPaginationFooter
            variant="bar"
            :meta="proposals.meta"
            :per-page="perPage"
            :per-page-options="PER_PAGE_OPTIONS"
            @update:per-page="(v) => { perPage = v; }"
          />
        </div>
      </div>
    </div>

    <CongngheMyProposalDetailModal
      :show="detailOpen"
      :proposal="activeProposal"
      @close="closeDetail"
    />
  </CongnghePageShell>
</template>

<style src="./partials/congnghe-portal-datagrid.css"></style>
