<script setup>
import {
    computed, onBeforeUnmount, onMounted, ref, watch,
} from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterDatePicker from '@/shared/ui/FilterDatePicker.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import { useAiAnalyticsReport } from '@/modules/aiAccount/composables/useAiAnalyticsReport';
import { exportAiAnalyticsWorkbook } from '@/modules/aiAccount/composables/useAiAnalyticsExport';
import { useAiExecutiveDashboard } from '@/modules/aiAccount/composables/useAiExecutiveDashboard';
import {
    ANALYTICS_REPORT_COLUMNS,
    DEFAULT_VISIBLE_ANALYTICS_COLUMNS,
} from '@/modules/aiAccount/config/analyticsReportColumns';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useToast } from '@/shared/composables/useToast';
import { formatVnd } from '@/modules/aiAccount/utils/formatVnd';

const FILTER_CONTROLS_DEF = [
    { key: 'date_created', label: 'Khoảng thời gian PĐX', default: false },
    { key: 'purchase_date', label: 'Ngày mua', default: false },
    { key: 'expiry', label: 'Ngày hết hạn', default: false },
    { key: 'department', label: 'Phòng ban', default: false },
    { key: 'group_function', label: 'Nhóm chức năng', default: false },
    { key: 'tool', label: 'Loại AI', default: false },
    { key: 'vendor', label: 'Nhà cung cấp', default: false },
    { key: 'status', label: 'Trạng thái TK', default: false },
    { key: 'lifecycle', label: 'Vòng đời', default: false },
    { key: 'proposer', label: 'Người đề xuất', default: false },
    { key: 'cost_range', label: 'Khoảng chi phí', default: false },
];

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const COL_STORAGE_KEY = 'va-workspace.ai-analytics.columns';
const COL_ORDER_KEY = 'va-workspace.ai-analytics.column-order';
const VISIBLE_FILTERS_KEY = 'va-workspace.ai-analytics.visible-filters.v2';

const props = defineProps({
    options: { type: Object, required: true },
    exporter: { type: Object, default: () => ({}) },
});

const toast = useToast();

const report = useAiAnalyticsReport();
const dashboardLoader = useAiExecutiveDashboard();

const {
    loading,
    rows,
    stats,
    filterOptions,
    search,
    department,
    groupFunction,
    tool,
    vendor,
    status,
    lifecycleStatus,
    proposalStatus,
    proposer,
    purchaseFrom,
    purchaseTo,
    expiryFrom,
    expiryTo,
    createdFrom,
    createdTo,
    costMin,
    costMax,
    loadFilterOptions,
    loadReport,
    applyFromQuery,
} = report;

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
const exporting = ref(false);
const filterPanelDdRef = ref(null);
const colDdRef = ref(null);
const exportDdRef = ref(null);
const groupBy = ref('');
const debounceTimer = ref(null);
const collapsedGroupKeys = ref(new Set());
const expandedRowIds = ref(new Set());

const INLINE_COL_LIMIT = 6;

function readVisibleCols() {
    try {
        const raw = localStorage.getItem(COL_STORAGE_KEY);
        if (raw) return JSON.parse(raw);
    } catch { /* ignore */ }
    return Object.fromEntries(
        ANALYTICS_REPORT_COLUMNS.map((c) => [c.key, DEFAULT_VISIBLE_ANALYTICS_COLUMNS.includes(c.key)]),
    );
}

const visibleCols = ref(readVisibleCols());

function readColumnOrder() {
    try {
        const raw = localStorage.getItem(COL_ORDER_KEY);
        if (raw) return JSON.parse(raw);
    } catch { /* ignore */ }
    return ANALYTICS_REPORT_COLUMNS.map((c) => c.key);
}

const columnOrder = ref(readColumnOrder());
const dragColKey = ref(null);

const orderedColumns = computed(() => {
    const map = Object.fromEntries(ANALYTICS_REPORT_COLUMNS.map((c) => [c.key, c]));
    return columnOrder.value.map((k) => map[k]).filter(Boolean);
});

const activeColumns = computed(() => orderedColumns.value.filter((c) => visibleCols.value[c.key]));

const groupableColumns = computed(() => ANALYTICS_REPORT_COLUMNS.filter((c) => c.groupable));

const tableColumns = computed(() => {
    const cols = activeColumns.value;
    if (cols.length <= INLINE_COL_LIMIT) {
        return { inline: cols, overflow: [], showExpand: false };
    }
    return {
        inline: cols.slice(0, INLINE_COL_LIMIT),
        overflow: cols.slice(INLINE_COL_LIMIT),
        showExpand: true,
    };
});

const displayRows = computed(() => {
    if (!groupBy.value) {
        return rows.value.map((r) => ({ type: 'row', row: r }));
    }
    const groups = {};
    rows.value.forEach((row) => {
        const key = row[groupBy.value] ?? '—';
        const groupKey = String(key);
        groups[groupKey] = groups[groupKey] ?? { key, groupKey, rows: [], sum: 0 };
        groups[groupKey].rows.push(row);
        groups[groupKey].sum += Number(row.cost_monthly) || 0;
    });
    return Object.values(groups).flatMap((g) => {
        const header = {
            type: 'group',
            label: g.key,
            groupKey: g.groupKey,
            count: g.rows.length,
            sum: g.sum,
        };
        if (collapsedGroupKeys.value.has(g.groupKey)) {
            return [header];
        }
        return [
            header,
            ...g.rows.map((row) => ({ type: 'row', row, groupKey: g.groupKey })),
        ];
    });
});

function toggleGroup(groupKey) {
    const next = new Set(collapsedGroupKeys.value);
    if (next.has(groupKey)) {
        next.delete(groupKey);
    } else {
        next.add(groupKey);
    }
    collapsedGroupKeys.value = next;
}

function isGroupCollapsed(groupKey) {
    return collapsedGroupKeys.value.has(groupKey);
}

function toggleRowExpand(rowId) {
    const next = new Set(expandedRowIds.value);
    if (next.has(rowId)) {
        next.delete(rowId);
    } else {
        next.add(rowId);
    }
    expandedRowIds.value = next;
}

function isRowExpanded(rowId) {
    return expandedRowIds.value.has(rowId);
}

function persistColumns() {
    localStorage.setItem(COL_STORAGE_KEY, JSON.stringify(visibleCols.value));
    localStorage.setItem(COL_ORDER_KEY, JSON.stringify(columnOrder.value));
}

function onColDragStart(key) {
    dragColKey.value = key;
}

function onColDrop(targetKey) {
    if (!dragColKey.value || dragColKey.value === targetKey) return;
    const order = [...columnOrder.value];
    const from = order.indexOf(dragColKey.value);
    const to = order.indexOf(targetKey);
    if (from < 0 || to < 0) return;
    order.splice(from, 1);
    order.splice(to, 0, dragColKey.value);
    columnOrder.value = order;
    dragColKey.value = null;
    persistColumns();
}

function scheduleLoad() {
    clearTimeout(debounceTimer.value);
    debounceTimer.value = setTimeout(loadReport, 350);
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
        showColDd.value = false;
        showFilterPanelDd.value = false;
        if (!dashboardLoader.data.value && !dashboardLoader.loading.value) {
            void dashboardLoader.load();
        }
    }
}

function toggleVisibleCol(key, checked) {
    visibleCols.value = { ...visibleCols.value, [key]: checked };
    persistColumns();
}

watch([
    search, department, groupFunction, tool, vendor, status, lifecycleStatus,
    proposalStatus, proposer, purchaseFrom, purchaseTo, expiryFrom, expiryTo,
    createdFrom, createdTo, costMin, costMax,
], scheduleLoad);

watch(groupBy, (key) => {
    expandedRowIds.value = new Set();
    if (!key) {
        collapsedGroupKeys.value = new Set();
        return;
    }
    const keys = new Set();
    rows.value.forEach((row) => {
        keys.add(String(row[key] ?? '—'));
    });
    collapsedGroupKeys.value = keys;
});

function onDocMouseDown(e) {
    if (e.target.closest?.('[data-filter-visibility-panel]')) return;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
    if (colDdRef.value && !colDdRef.value.contains(e.target)) {
        showColDd.value = false;
    }
    if (exportDdRef.value && !exportDdRef.value.contains(e.target)) {
        showExportDd.value = false;
    }
}

onMounted(async () => {
    document.addEventListener('mousedown', onDocMouseDown);
    applyFromQuery(new URLSearchParams(window.location.search));
    await loadFilterOptions();
    await loadReport();
    void dashboardLoader.load();
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocMouseDown);
    clearTimeout(debounceTimer.value);
});

function exportExcel() {
    showExportDd.value = false;
    if (!rows.value.length) {
        toast.error('Không có dữ liệu để xuất.');
        return;
    }
    if (activeColumns.value.length === 0) {
        toast.error('Chọn ít nhất một cột để xuất.');
        return;
    }
    exporting.value = true;
    try {
        const keys = activeColumns.value.map((c) => c.key);
        const filename = exportAiAnalyticsWorkbook({
            rows: rows.value,
            stats: stats.value,
            dashboard: dashboardLoader.data.value,
            visibleColumnKeys: keys,
            exporterName: props.exporter?.name,
            filterNote: search.value ? `Tìm: ${search.value}` : '',
        });
        if (!filename) {
            toast.error('Không xuất được file Excel.');
            return;
        }
        toast.success(`Đã tải ${filename}`);
    } catch (e) {
        console.error(e);
        toast.error('Xuất Excel thất bại. Thử lại hoặc liên hệ quản trị.');
    } finally {
        exporting.value = false;
    }
}

function cellValue(row, col) {
    const v = row[col.key];
    if (col.key === 'cost_monthly' || col.key === 'cost_yearly' || col.key === 'actual_cost') {
        return v != null ? formatVnd(v) : '—';
    }
    return v ?? '—';
}

const headColCount = computed(() => {
    let n = tableColumns.value.inline.length;
    if (groupBy.value) n += 1;
    if (tableColumns.value.showExpand) n += 1;
    return Math.max(n, 1);
});

function rowStableId(row) {
    return row?.id ?? `${row?.proposal_code ?? ''}-${row?.user_name ?? ''}`;
}

const activeFilterCount = computed(() => {
    let n = 0;
    if (department.value !== 'all') n += 1;
    if (groupFunction.value !== 'all') n += 1;
    if (tool.value !== 'all') n += 1;
    if (vendor.value !== 'all') n += 1;
    if (status.value !== 'all') n += 1;
    if (lifecycleStatus.value !== 'all') n += 1;
    if (proposalStatus.value !== 'all') n += 1;
    if (proposer.value !== 'all') n += 1;
    if (purchaseFrom.value || purchaseTo.value) n += 1;
    if (expiryFrom.value || expiryTo.value) n += 1;
    if (createdFrom.value || createdTo.value) n += 1;
    if (costMin.value !== '' || costMax.value !== '') n += 1;
    return n;
});

function clearAllFilters() {
    search.value = '';
    department.value = 'all';
    groupFunction.value = 'all';
    tool.value = 'all';
    vendor.value = 'all';
    status.value = 'all';
    lifecycleStatus.value = 'all';
    proposalStatus.value = 'all';
    proposer.value = 'all';
    purchaseFrom.value = '';
    purchaseTo.value = '';
    expiryFrom.value = '';
    expiryTo.value = '';
    createdFrom.value = '';
    createdTo.value = '';
    costMin.value = '';
    costMax.value = '';
    void loadReport();
}
</script>

<template>
  <Head title="Báo cáo phân tích AI" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Báo cáo phân tích chuyên sâu"
        subtitle="Bảng dữ liệu tài khoản AI — lọc, nhóm, xuất Excel báo cáo quản trị"
        icon="performance"
        icon-color="brand"
      />
    </template>

    <div class="card overflow-visible shadow-sm">
      <div class="relative z-20 overflow-visible border-b border-slate-100 px-5 py-4 dark:border-slate-700">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              v-model="search"
              input-id="ai-analytics-search"
              placeholder="Mã hồ sơ, sản phẩm, người dùng…"
              stretch
              inline-actions
              hide-label
              input-height="h-10"
            />
          </div>

          <div class="flex shrink-0 items-center gap-2">
            <div
              ref="filterPanelDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="filter"
                :active="showFilterPanelDd"
                :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
                @click="openFilterPanel"
              >
                Lọc
              </DatagridToolbarActionButton>
              <FilterVisibilityDropdown
                v-model="visibleFilters"
                :show="showFilterPanelDd"
                :anchor-ref="filterPanelDdRef"
                :controls="FILTER_CONTROLS"
                @persist="persistVisibleFilters"
              />
            </div>

            <div
              ref="colDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="columns"
                :active="showColDd"
                title="Cột hiển thị"
                @click="openCol"
              >
                Cột
              </DatagridToolbarActionButton>
              <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 scale-95 -translate-y-1"
                leave-active-class="transition duration-100 ease-in"
                leave-to-class="opacity-0 scale-95 -translate-y-1"
              >
                <div
                  v-if="showColDd"
                  class="absolute right-0 top-full z-50 mt-1.5 max-h-80 w-56 overflow-y-auto rounded-xl border border-slate-200 bg-white py-2 shadow-elevation-2"
                >
                  <div class="border-b border-slate-100 px-4 py-2">
                    <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Cột hiển thị</span>
                  </div>
                  <p class="px-3 pb-1 pt-2 text-[10px] uppercase text-slate-400">
                    Kéo thả thứ tự
                  </p>
                  <div
                    v-for="col in orderedColumns"
                    :key="col.key"
                    draggable="true"
                    class="flex cursor-grab items-center gap-2 px-3 py-1 text-xs hover:bg-slate-50"
                    @dragstart="onColDragStart(col.key)"
                    @dragover.prevent
                    @drop="onColDrop(col.key)"
                  >
                    <input
                      type="checkbox"
                      class="rounded border-slate-300 text-brand focus:ring-brand/30"
                      :checked="visibleCols[col.key]"
                      @change="toggleVisibleCol(col.key, $event.target.checked)"
                    >
                    {{ col.label }}
                  </div>
                </div>
              </Transition>
            </div>

            <div
              ref="exportDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="export"
                :active="showExportDd"
                :disabled="exporting || loading"
                title="Xuất Excel báo cáo phân tích"
                @click="openExportMenu"
              >
                {{ exporting ? 'Đang xuất…' : 'Xuất' }}
              </DatagridToolbarActionButton>
              <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 scale-95 -translate-y-1"
                leave-active-class="transition duration-100 ease-in"
                leave-to-class="opacity-0 scale-95 -translate-y-1"
              >
                <div
                  v-if="showExportDd"
                  class="absolute right-0 top-full z-50 mt-1.5 w-44 rounded-xl border border-slate-200 bg-white py-1 shadow-elevation-2"
                >
                  <button
                    type="button"
                    class="block w-full px-3 py-2 text-left text-xs hover:bg-slate-50 disabled:opacity-50"
                    :disabled="exporting || loading"
                    @click.stop="exportExcel"
                  >
                    Excel báo cáo (.xlsx)
                  </button>
                </div>
              </Transition>
            </div>
          </div>

          <div class="ml-auto flex w-full min-w-0 shrink-0 basis-full sm:w-auto sm:max-w-xs sm:basis-auto lg:min-w-[11rem]">
            <select
              v-model="groupBy"
              :class="FILTER_CONTROL_CLASS"
              aria-label="Nhóm dữ liệu"
            >
              <option value="">
                Nhóm dữ liệu
              </option>
              <option
                v-for="c in groupableColumns"
                :key="c.key"
                :value="c.key"
              >
                {{ c.label }}
              </option>
            </select>
          </div>
        </div>

        <p
          v-if="!hasFilterRow && (activeFilterCount > 0 || search.trim())"
          class="mt-2 text-xs text-slate-500"
        >
          <span v-if="activeFilterCount > 0">{{ activeFilterCount }} bộ lọc đang áp dụng</span>
          <span v-if="search.trim()"><span v-if="activeFilterCount > 0"> · </span>«{{ search.trim() }}»</span>
        </p>

        <Transition name="fade-slide">
          <div
            v-if="hasFilterRow"
            class="grid grid-cols-1 gap-3 border-t border-slate-100 px-0 pt-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6 dark:border-slate-700"
          >
            <div
              v-if="visibleFilters.date_created"
              class="min-w-0 w-full sm:col-span-2 xl:col-span-2"
            >
              <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:gap-3">
                <FilterDatePicker
                  v-model="createdFrom"
                  placeholder="Từ ngày PĐX"
                  :max-date="createdTo || null"
                />
                <FilterDatePicker
                  v-model="createdTo"
                  placeholder="Đến ngày PĐX"
                  :min-date="createdFrom || null"
                />
              </div>
            </div>

            <div
              v-if="visibleFilters.purchase_date"
              class="min-w-0 w-full sm:col-span-2 xl:col-span-2"
            >
              <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:gap-3">
                <FilterDatePicker
                  v-model="purchaseFrom"
                  placeholder="Từ ngày mua"
                  :max-date="purchaseTo || null"
                />
                <FilterDatePicker
                  v-model="purchaseTo"
                  placeholder="Đến ngày mua"
                  :min-date="purchaseFrom || null"
                />
              </div>
            </div>

            <div
              v-if="visibleFilters.expiry"
              class="min-w-0 w-full sm:col-span-2 xl:col-span-2"
            >
              <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:gap-3">
                <FilterDatePicker
                  v-model="expiryFrom"
                  placeholder="Từ ngày hết hạn"
                  :max-date="expiryTo || null"
                />
                <FilterDatePicker
                  v-model="expiryTo"
                  placeholder="Đến ngày hết hạn"
                  :min-date="expiryFrom || null"
                />
              </div>
            </div>

            <DatagridFilterField v-if="visibleFilters.department">
              <select
                v-model="department"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Phòng ban"
              >
                <option value="all">
                  Phòng ban
                </option>
                <option
                  v-for="d in filterOptions.departments"
                  :key="d"
                  :value="d"
                >
                  {{ d }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.group_function">
              <select
                v-model="groupFunction"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Nhóm chức năng"
              >
                <option value="all">
                  Nhóm chức năng
                </option>
                <option
                  v-for="o in options.group_function"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.tool">
              <select
                v-model="tool"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Loại AI"
              >
                <option value="all">
                  Loại AI
                </option>
                <option
                  v-for="t in filterOptions.tools"
                  :key="t"
                  :value="t"
                >
                  {{ t }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.vendor">
              <select
                v-model="vendor"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Nhà cung cấp"
              >
                <option value="all">
                  Nhà cung cấp
                </option>
                <option
                  v-for="v in filterOptions.vendors"
                  :key="v"
                  :value="v"
                >
                  {{ v }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.status">
              <select
                v-model="status"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Trạng thái TK"
              >
                <option value="all">
                  Trạng thái TK
                </option>
                <option
                  v-for="o in options.status"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.lifecycle">
              <select
                v-model="lifecycleStatus"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Vòng đời"
              >
                <option value="all">
                  Vòng đời
                </option>
                <option
                  v-for="o in options.lifecycle_status"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.proposer">
              <select
                v-model="proposer"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Người đề xuất"
              >
                <option value="all">
                  Người đề xuất
                </option>
                <option
                  v-for="p in filterOptions.proposers"
                  :key="p"
                  :value="p"
                >
                  {{ p }}
                </option>
              </select>
            </DatagridFilterField>

            <div
              v-if="visibleFilters.cost_range"
              class="min-w-0 w-full sm:col-span-2 xl:col-span-2"
            >
              <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:gap-3">
                <input
                  v-model="costMin"
                  type="number"
                  placeholder="Chi phí từ (VNĐ)"
                  :class="FILTER_CONTROL_CLASS"
                  aria-label="Chi phí tối thiểu"
                >
                <input
                  v-model="costMax"
                  type="number"
                  placeholder="Chi phí đến (VNĐ)"
                  :class="FILTER_CONTROL_CLASS"
                  aria-label="Chi phí tối đa"
                >
              </div>
            </div>

            <div
              v-if="activeFilterCount > 0 || search.trim()"
              class="col-span-full flex justify-end pt-0.5"
            >
              <button
                type="button"
                class="inline-flex h-10 items-center px-2 text-xs font-medium text-brand hover:underline"
                @click="clearAllFilters"
              >
                Đặt lại bộ lọc
              </button>
            </div>
          </div>
        </Transition>
      </div>

      <div class="w-full min-w-0 overflow-x-auto">
        <table class="w-full table-fixed border-collapse text-left text-xs">
          <thead class="bg-slate-50">
            <tr>
              <th
                v-if="groupBy"
                class="w-9 border-b border-slate-200 px-1 py-2"
                aria-label="Thu gọn nhóm"
              />
              <th
                v-if="tableColumns.showExpand"
                class="w-9 border-b border-slate-200 px-1 py-2"
                aria-label="Chi tiết thêm"
              />
              <th
                v-for="col in tableColumns.inline"
                :key="col.key"
                class="border-b border-slate-200 px-2 py-2 font-medium text-slate-600"
                :class="col.width ? 'truncate' : 'min-w-[5.5rem]'"
                :style="col.width ? { width: col.width } : undefined"
                :title="col.label"
              >
                <span class="block truncate">{{ col.label }}</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td
                :colspan="headColCount"
                class="px-3 py-8 text-center text-slate-400"
              >
                Đang tải…
              </td>
            </tr>
            <template
              v-for="entry in displayRows"
              :key="entry.type === 'group' ? `g-${entry.groupKey}` : `r-${rowStableId(entry.row)}`"
            >
              <tr
                v-if="entry.type === 'group'"
                class="border-b border-brand/10 bg-brand/[0.04] text-brand"
              >
                <td class="px-1 py-1.5 align-middle">
                  <button
                    type="button"
                    class="inline-flex h-7 w-7 items-center justify-center rounded-md text-brand hover:bg-brand/10"
                    :aria-expanded="!isGroupCollapsed(entry.groupKey)"
                    :aria-label="isGroupCollapsed(entry.groupKey) ? 'Mở nhóm' : 'Thu gọn nhóm'"
                    @click="toggleGroup(entry.groupKey)"
                  >
                    <AppIcon
                      :name="isGroupCollapsed(entry.groupKey) ? 'chevron-right' : 'chevron-down'"
                      :size="16"
                    />
                  </button>
                </td>
                <td
                  v-if="tableColumns.showExpand"
                  class="px-1 py-1.5"
                />
                <td
                  :colspan="tableColumns.inline.length"
                  class="truncate px-2 py-1.5 font-medium"
                >
                  <span class="text-slate-800">{{ entry.label }}</span>
                  <span class="ml-2 font-normal text-slate-500">
                    {{ entry.count }} TK · {{ formatVnd(entry.sum) }}/tháng
                  </span>
                </td>
              </tr>
              <template v-else>
                <tr
                  class="border-b border-slate-100 hover:bg-slate-50/80"
                  :class="entry.groupKey ? 'bg-white' : ''"
                >
                  <td
                    v-if="groupBy"
                    class="w-9 px-1 py-1 align-middle"
                  >
                    <span
                      v-if="entry.groupKey"
                      class="ml-3 inline-block h-1.5 w-1.5 rounded-full bg-slate-200"
                      aria-hidden="true"
                    />
                  </td>
                  <td
                    v-if="tableColumns.showExpand"
                    class="px-1 py-1 align-middle"
                  >
                    <button
                      type="button"
                      class="inline-flex h-7 w-7 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                      :aria-expanded="isRowExpanded(rowStableId(entry.row))"
                      aria-label="Xem thêm cột"
                      @click="toggleRowExpand(rowStableId(entry.row))"
                    >
                      <AppIcon
                        :name="isRowExpanded(rowStableId(entry.row)) ? 'chevron-down' : 'chevron-right'"
                        :size="16"
                      />
                    </button>
                  </td>
                  <td
                    v-for="col in tableColumns.inline"
                    :key="col.key"
                    class="truncate px-2 py-1 text-slate-700"
                    :title="String(entry.row[col.key] ?? '')"
                  >
                    {{ cellValue(entry.row, col) }}
                  </td>
                </tr>
                <tr
                  v-if="tableColumns.showExpand && isRowExpanded(rowStableId(entry.row))"
                  class="border-b border-slate-100 bg-slate-50/90"
                >
                  <td :colspan="headColCount">
                    <dl
                      class="grid grid-cols-2 gap-x-4 gap-y-2 px-3 py-2 sm:grid-cols-3 lg:grid-cols-4"
                    >
                      <div
                        v-for="col in tableColumns.overflow"
                        :key="col.key"
                        class="min-w-0"
                      >
                        <dt class="text-[10px] font-medium text-slate-500">
                          {{ col.label }}
                        </dt>
                        <dd class="truncate text-xs text-slate-800">
                          {{ cellValue(entry.row, col) }}
                        </dd>
                      </div>
                    </dl>
                  </td>
                </tr>
              </template>
            </template>
            <tr v-if="!loading && !rows.length">
              <td
                :colspan="headColCount"
                class="px-3 py-10 text-center text-slate-400"
              >
                Không có bản ghi phù hợp bộ lọc.
              </td>
            </tr>
          </tbody>
        </table>
        <p
          v-if="tableColumns.showExpand && activeColumns.length > INLINE_COL_LIMIT"
          class="border-t border-slate-100 px-3 py-2 text-[10px] text-slate-400"
        >
          Hiển thị {{ tableColumns.inline.length }}/{{ activeColumns.length }} cột trên bảng — bấm mũi tên từng dòng để xem thêm.
        </p>
      </div>

      <div
        v-if="stats"
        class="grid gap-3 border-t border-slate-200 bg-slate-50/80 p-4 sm:grid-cols-2 lg:grid-cols-3"
      >
        <div>
          <p class="text-[10px] font-semibold uppercase text-slate-400">
            Tổng TK
          </p>
          <p class="text-lg font-bold text-slate-900">
            {{ stats.account_count }}
          </p>
        </div>
        <div>
          <p class="text-[10px] font-semibold uppercase text-slate-400">
            Tổng chi phí / tháng
          </p>
          <p class="text-lg font-bold text-brand">
            {{ formatVnd(stats.cost_total_monthly) }}
          </p>
        </div>
        <div>
          <p class="text-[10px] font-semibold uppercase text-slate-400">
            Chi phí trung bình
          </p>
          <p class="text-lg font-bold text-slate-900">
            {{ formatVnd(stats.cost_average_monthly) }}
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
