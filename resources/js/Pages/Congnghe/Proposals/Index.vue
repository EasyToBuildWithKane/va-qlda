<script setup>
import {
    computed, onBeforeUnmount, onMounted, reactive, ref, watch,
} from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import CongngheSoftwareProposalsSummaryBar from '@/Pages/Congnghe/partials/CongngheSoftwareProposalsSummaryBar.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import DatagridSegmentedControl from '@/shared/ui/DatagridSegmentedControl.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { useToast } from '@/shared/composables/useToast';
import { date, datetime } from '@/composables/useFormat';

const PER_PAGE_OPTIONS = [10, 15, 20, 30];
const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái', default: false },
    { key: 'department', label: 'Phòng ban', default: false },
    { key: 'email_pending', label: 'Chưa gửi email', default: false },
];

const TABLE_COLUMNS = [
    { key: 'code', label: 'Mã' },
    { key: 'title', label: 'Tiêu đề' },
    { key: 'submitter', label: 'Người gửi' },
    { key: 'status', label: 'Trạng thái' },
    { key: 'created_at', label: 'Ngày gửi' },
    { key: 'processed_at', label: 'Ngày xử lý' },
    { key: 'attachments', label: 'Đính kèm', default: false },
];

const GROUP_MODES = [
    { key: 'department', label: 'Theo phòng ban', icon: 'building', title: 'Nhóm theo phòng ban người gửi' },
    { key: 'none', label: 'Danh sách', icon: 'list', title: 'Danh sách phẳng' },
];

const props = defineProps({
    proposals: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const toast = useToast();
const filterPanelDdRef = ref(null);
const colDdRef = ref(null);
const perPage = ref(Number(props.filters.per_page) || props.proposals.meta?.per_page || 20);
const statusUpdating = ref(new Set());

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
} = useVisibleFilterControls(FILTER_CONTROLS, 'va-qlda.congnghe-proposals.filters.v2');

const {
    visibleCols,
    showColDd,
    persistVisibleColumns,
    openColPanel,
    isColVisible,
    TABLE_COLUMNS: columnDefs,
} = useVisibleColumns(TABLE_COLUMNS, 'va-qlda.congnghe-proposals.columns.v1');

const filterForm = reactive({
    status: props.filters.status ?? '',
    department: props.filters.department ?? '',
    email_pending: props.filters.email_pending ? '1' : '',
    group: props.filters.group === 'none' ? 'none' : 'department',
    q: props.filters.q ?? '',
});

const activeEmailPending = computed(() => filterForm.email_pending === '1');
const listBadge = computed(() => props.summary.new ?? props.summary.total ?? null);
const groupByDepartment = computed(() => filterForm.group === 'department');

const tableColspan = computed(() =>
    columnDefs.filter((c) => isColVisible(c.key)).length,
);

const tableEntries = computed(() => {
    const data = props.proposals.data ?? [];
    if (!groupByDepartment.value) {
        return data.map((row) => ({ type: 'data', row }));
    }

    const counts = {};
    for (const row of data) {
        const d = departmentKey(row);
        counts[d] = (counts[d] ?? 0) + 1;
    }

    const entries = [];
    let lastDept = null;
    for (const row of data) {
        const d = departmentKey(row);
        if (d !== lastDept) {
            entries.push({
                type: 'group',
                key: d,
                label: d,
                count: counts[d] ?? 0,
            });
            lastDept = d;
        }
        entries.push({ type: 'data', row });
    }
    return entries;
});

function departmentKey(row) {
    const value = String(row.department ?? '').trim();
    return value !== '' ? value : 'Khác';
}

function routeParams(resetPage = false) {
    const params = {
        status: filterForm.status || undefined,
        department: filterForm.department || undefined,
        email_pending: filterForm.email_pending || undefined,
        group: filterForm.group === 'none' ? 'none' : undefined,
        q: filterForm.q || undefined,
        per_page: perPage.value,
    };
    if (resetPage) params.page = 1;
    return params;
}

function navigate(resetPage = false) {
    router.get(route('congnghe.proposals.index'), routeParams(resetPage), {
        preserveState: true,
        replace: true,
    });
}

let qTimer = null;
watch(() => filterForm.q, () => {
    clearTimeout(qTimer);
    qTimer = setTimeout(() => navigate(true), 350);
});

watch(
    () => [filterForm.status, filterForm.email_pending, filterForm.department],
    () => navigate(true),
);

watch(() => filterForm.group, () => navigate(true));
watch(perPage, () => navigate(true));

function onQuickFilter({ status, email_pending }) {
    filterForm.status = status ?? '';
    filterForm.email_pending = email_pending ? '1' : '';
}

function onToolbarClickOutside(e) {
    if (e.target.closest?.('[data-filter-visibility-panel]')) return;
    if (e.target.closest?.('[data-column-visibility-panel]')) return;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
    if (colDdRef.value && !colDdRef.value.contains(e.target)) {
        showColDd.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));

function processedAtLabel(row) {
    if (!row.processed_at) return '—';
    const d = date(row.processed_at);
    return d !== '—' ? d : datetime(row.processed_at);
}

function updateStatus(row, value) {
    if (!row.can?.update || value === row.status?.value) return;
    statusUpdating.value.add(row.id);
    router.put(route('congnghe.proposals.update', row.id), { status: value }, {
        preserveScroll: true,
        onFinish: () => {
            statusUpdating.value.delete(row.id);
        },
        onSuccess: () => {
            toast.success(page.props.flash?.success ?? 'Đã cập nhật trạng thái.');
        },
    });
}
</script>

<template>
  <Head title="Đề xuất phần mềm" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Đề xuất phần mềm"
        subtitle="Quản lý đề xuất giải pháp phần mềm gửi tới Phòng Công nghệ"
        icon="template"
        icon-color="brand"
        :badge="listBadge"
      />
    </template>

    <CongngheSoftwareProposalsSummaryBar
      :summary="summary"
      :active-status="filterForm.status"
      :active-email-pending="activeEmailPending"
      @quick-filter="onQuickFilter"
    />

    <div class="card overflow-hidden">
      <div class="border-b border-slate-100 px-5 py-4">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              v-model="filterForm.q"
              hide-label
              stretch
              inline-actions
              input-height="h-10"
              placeholder="Tìm mã, tiêu đề, người gửi, email…"
              aria-label="Tìm đề xuất"
            />
          </div>
          <div class="flex shrink-0 items-center gap-2">
            <div
              ref="filterPanelDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                label="Lọc"
                icon="filter"
                :active="showFilterPanelDd"
                :badge="enabledFilterControlCount > 0 ? enabledFilterControlCount : null"
                @click="openFilterPanel(() => { showColDd = false; })"
              />
              <FilterVisibilityDropdown
                v-model="visibleFilters"
                :show="showFilterPanelDd"
                :anchor-ref="filterPanelDdRef"
                :controls="FILTER_CONTROLS"
                input-id-prefix="cn-proposal-filter-vis"
                @persist="persistVisibleFilters"
              />
            </div>
            <div
              ref="colDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                label="Cột"
                icon="columns"
                :active="showColDd"
                title="Cột hiển thị"
                @click="openColPanel(() => { showFilterPanelDd = false; })"
              />
              <ColumnVisibilityDropdown
                v-model="visibleCols"
                :show="showColDd"
                :columns="columnDefs"
                :anchor-ref="colDdRef"
                input-id-prefix="cn-proposal-col-vis"
                @persist="persistVisibleColumns"
              />
            </div>
          </div>
          <div class="ml-auto flex shrink-0 items-center gap-2">
            <DatagridSegmentedControl
              v-model="filterForm.group"
              :items="GROUP_MODES"
              aria-label="Cách nhóm danh sách"
              icon-only-below-sm
            />
          </div>
        </div>

        <div
          v-if="hasFilterRow"
          class="grid grid-cols-1 gap-3 border-t border-slate-100 px-0 pt-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
        >
          <DatagridFilterField v-if="visibleFilters.status">
            <label
              for="cn-proposal-filter-status"
              class="sr-only"
            >Trạng thái</label>
            <select
              id="cn-proposal-filter-status"
              v-model="filterForm.status"
              :class="FILTER_CONTROL_CLASS"
              @change="filterForm.email_pending = ''"
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
            <label
              for="cn-proposal-filter-department"
              class="sr-only"
            >Phòng ban</label>
            <select
              id="cn-proposal-filter-department"
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
          <DatagridFilterField v-if="visibleFilters.email_pending">
            <label
              for="cn-proposal-filter-email"
              class="sr-only"
            >Email</label>
            <select
              id="cn-proposal-filter-email"
              v-model="filterForm.email_pending"
              :class="FILTER_CONTROL_CLASS"
              @change="filterForm.status = ''"
            >
              <option value="">
                Chưa gửi email
              </option>
              <option value="1">
                Chưa gửi ({{ summary.email_pending ?? 0 }})
              </option>
            </select>
          </DatagridFilterField>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-left text-sm">
          <thead class="border-b border-slate-100 bg-slate-50/80 text-xs font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th
                v-if="isColVisible('code')"
                class="px-5 py-3"
              >
                Mã
              </th>
              <th
                v-if="isColVisible('title')"
                class="px-5 py-3"
              >
                Tiêu đề
              </th>
              <th
                v-if="isColVisible('submitter')"
                class="px-5 py-3"
              >
                Người gửi
              </th>
              <th
                v-if="isColVisible('status')"
                class="px-5 py-3"
              >
                Trạng thái
              </th>
              <th
                v-if="isColVisible('created_at')"
                class="px-5 py-3"
              >
                Ngày gửi
              </th>
              <th
                v-if="isColVisible('processed_at')"
                class="px-5 py-3"
              >
                Ngày xử lý
              </th>
              <th
                v-if="isColVisible('attachments')"
                class="px-5 py-3"
              >
                Đính kèm
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <template
              v-for="(entry, idx) in tableEntries"
              :key="entry.type === 'group' ? `g-${entry.key}` : `r-${entry.row.id}`"
            >
              <tr
                v-if="entry.type === 'group'"
                class="border-y border-slate-200 bg-slate-100/70"
              >
                <td
                  :colspan="tableColspan || 1"
                  class="px-5 py-2.5"
                >
                  <div class="flex flex-wrap items-center gap-2">
                    <AppIcon
                      name="building"
                      :size="16"
                      class="shrink-0 text-brand"
                    />
                    <span class="text-sm font-semibold text-slate-800">{{ entry.label }}</span>
                    <span class="text-xs font-medium text-slate-500">{{ entry.count }} đề xuất</span>
                  </div>
                </td>
              </tr>
              <tr
                v-else
                class="hover:bg-slate-50/80"
                :class="{ 'border-t-0': idx > 0 && tableEntries[idx - 1]?.type === 'group' }"
              >
                <td
                  v-if="isColVisible('code')"
                  class="px-5 py-3 font-mono text-xs text-slate-600"
                >
                  <Link
                    :href="route('congnghe.proposals.show', entry.row.id)"
                    class="font-semibold text-brand hover:underline"
                  >
                    {{ entry.row.reference_code ?? '—' }}
                  </Link>
                </td>
                <td
                  v-if="isColVisible('title')"
                  class="max-w-[240px] px-5 py-3"
                >
                  <Link
                    :href="route('congnghe.proposals.show', entry.row.id)"
                    class="line-clamp-2 font-medium text-slate-900 hover:text-brand"
                  >
                    {{ entry.row.title }}
                  </Link>
                </td>
                <td
                  v-if="isColVisible('submitter')"
                  class="px-5 py-3 font-medium text-slate-800"
                >
                  {{ entry.row.submitter_name }}
                </td>
                <td
                  v-if="isColVisible('status')"
                  class="px-5 py-3"
                >
                  <select
                    v-if="entry.row.can?.update"
                    :value="entry.row.status?.value"
                    class="input h-9 min-w-[9.5rem] max-w-full px-2 text-xs"
                    :disabled="statusUpdating.has(entry.row.id)"
                    aria-label="Trạng thái đề xuất"
                    @change="updateStatus(entry.row, $event.target.value)"
                  >
                    <option
                      v-for="opt in options.statuses"
                      :key="opt.value"
                      :value="opt.value"
                    >
                      {{ opt.label }}
                    </option>
                  </select>
                  <span
                    v-else
                    class="text-sm text-slate-600"
                  >{{ entry.row.status?.label }}</span>
                </td>
                <td
                  v-if="isColVisible('created_at')"
                  class="px-5 py-3 text-slate-600 tabular-nums"
                >
                  {{ datetime(entry.row.created_at) }}
                </td>
                <td
                  v-if="isColVisible('processed_at')"
                  class="px-5 py-3 text-slate-600 tabular-nums"
                >
                  {{ processedAtLabel(entry.row) }}
                </td>
                <td
                  v-if="isColVisible('attachments')"
                  class="px-5 py-3 text-slate-600 tabular-nums"
                >
                  {{ entry.row.attachments_count ?? 0 }}
                </td>
              </tr>
            </template>
            <tr v-if="!proposals.data?.length">
              <td
                :colspan="tableColspan || 1"
                class="px-5 py-12 text-center text-slate-500"
              >
                Chưa có đề xuất nào.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <DatagridPaginationFooter
        variant="bar"
        :meta="proposals.meta"
        :per-page="perPage"
        :per-page-options="PER_PAGE_OPTIONS"
        @update:per-page="(v) => { perPage = v; }"
      />
    </div>
  </AppLayout>
</template>
