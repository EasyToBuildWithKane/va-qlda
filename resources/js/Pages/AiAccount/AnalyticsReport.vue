<script setup>
import {
    computed, onBeforeUnmount, onMounted, ref, watch,
} from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
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
    { key: 'date_created', label: 'Khoảng thời gian PĐX', default: true },
    { key: 'purchase_date', label: 'Ngày mua', default: false },
    { key: 'expiry', label: 'Ngày hết hạn', default: true },
    { key: 'department', label: 'Phòng ban', default: true },
    { key: 'group_function', label: 'Nhóm chức năng', default: true },
    { key: 'tool', label: 'Loại AI', default: true },
    { key: 'vendor', label: 'Nhà cung cấp', default: false },
    { key: 'status', label: 'Trạng thái TK', default: true },
    { key: 'lifecycle', label: 'Vòng đời', default: false },
    { key: 'proposer', label: 'Người đề xuất', default: false },
    { key: 'cost_range', label: 'Khoảng chi phí', default: false },
];

const COL_STORAGE_KEY = 'va-qlda.ai-analytics.columns';
const COL_ORDER_KEY = 'va-qlda.ai-analytics.column-order';
const VISIBLE_FILTERS_KEY = 'va-qlda.ai-analytics.visible-filters';

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
</script>

<template>
  <Head title="Báo cáo phân tích AI" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Báo cáo phân tích chuyên sâu"
        subtitle="Bảng dữ liệu tài khoản AI — lọc, nhóm, xuất Excel báo cáo quản trị"
      />
    </template>

    <div class="card overflow-visible shadow-sm">
      <div class="relative z-20 overflow-visible border-b border-slate-100 bg-slate-50/40 px-5 py-3.5">
        <div class="flex flex-col gap-2.5 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
            <div class="flex min-w-0 flex-1 basis-full items-center gap-2 lg:min-w-[28rem] lg:basis-auto xl:min-w-[32rem]">
              <DatagridToolbarSearch
                v-model="search"
                input-id="ai-analytics-search"
                placeholder="Mã hồ sơ, sản phẩm, người dùng…"
              />
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
                /><span>Lọc</span>
                <span
                  v-if="enabledFilterControlCount"
                  class="text-brand"
                >({{ enabledFilterControlCount }})</span>
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
                /><span>Cột</span>
              </button>
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
              <button
                type="button"
                class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
                :class="showExportDd && 'border-brand/40 bg-brand/5 text-brand'"
                @click="openExportMenu"
              >
                <AppIcon
                  name="export"
                  :size="15"
                /><span>Xuất</span>
              </button>
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
                    {{ exporting ? 'Đang xuất…' : 'Excel báo cáo (.xlsx)' }}
                  </button>
                </div>
              </Transition>
            </div>
          </div>
          <select
            v-model="groupBy"
            class="h-9 shrink-0 rounded-lg border border-slate-200 bg-white px-2 text-xs text-slate-700"
            aria-label="Nhóm dữ liệu"
          >
            <option value="">
              Không nhóm
            </option>
            <option
              v-for="c in groupableColumns"
              :key="c.key"
              :value="c.key"
            >
              Nhóm: {{ c.label }}
            </option>
          </select>
        </div>

        <div
          v-if="hasFilterRow"
          class="mt-2.5 grid grid-cols-1 gap-2 border-t border-slate-100 pt-2.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
          <div
            v-if="visibleFilters.date_created"
            class="min-w-0 rounded-lg border border-slate-200/80 bg-white px-2.5 py-1.5"
          >
            <span class="text-[10px] font-medium text-slate-500">Ngày tạo PĐX</span>
            <div class="mt-1 flex items-center gap-1">
              <input
                v-model="createdFrom"
                type="date"
                class="input h-8 min-w-0 flex-1 text-xs"
                aria-label="Từ ngày tạo PĐX"
              >
              <span class="shrink-0 text-xs text-slate-300">→</span>
              <input
                v-model="createdTo"
                type="date"
                class="input h-8 min-w-0 flex-1 text-xs"
                aria-label="Đến ngày tạo PĐX"
              >
            </div>
          </div>
          <div
            v-if="visibleFilters.purchase_date"
            class="min-w-0 rounded-lg border border-slate-200/80 bg-white px-2.5 py-1.5"
          >
            <span class="text-[10px] font-medium text-slate-500">Ngày mua</span>
            <div class="mt-1 flex items-center gap-1">
              <input
                v-model="purchaseFrom"
                type="date"
                class="input h-8 min-w-0 flex-1 text-xs"
                aria-label="Từ ngày mua"
              >
              <span class="shrink-0 text-xs text-slate-300">→</span>
              <input
                v-model="purchaseTo"
                type="date"
                class="input h-8 min-w-0 flex-1 text-xs"
                aria-label="Đến ngày mua"
              >
            </div>
          </div>
          <div
            v-if="visibleFilters.expiry"
            class="min-w-0 rounded-lg border border-slate-200/80 bg-white px-2.5 py-1.5"
          >
            <span class="text-[10px] font-medium text-slate-500">Ngày hết hạn</span>
            <div class="mt-1 flex items-center gap-1">
              <input
                v-model="expiryFrom"
                type="date"
                class="input h-8 min-w-0 flex-1 text-xs"
                aria-label="Từ ngày hết hạn"
              >
              <span class="shrink-0 text-xs text-slate-300">→</span>
              <input
                v-model="expiryTo"
                type="date"
                class="input h-8 min-w-0 flex-1 text-xs"
                aria-label="Đến ngày hết hạn"
              >
            </div>
          </div>
          <div
            v-if="visibleFilters.department"
            class="min-w-0 rounded-lg border border-slate-200/80 bg-white px-2.5 py-1.5"
          >
            <label class="text-[10px] font-medium text-slate-500">Phòng ban</label>
            <select
              v-model="department"
              class="input mt-1 h-8 w-full text-xs"
            >
              <option value="all">
                Tất cả
              </option>
              <option
                v-for="d in filterOptions.departments"
                :key="d"
                :value="d"
              >
                {{ d }}
              </option>
            </select>
          </div>
          <div
            v-if="visibleFilters.group_function"
            class="min-w-0 rounded-lg border border-slate-200/80 bg-white px-2.5 py-1.5"
          >
            <label class="text-[10px] font-medium text-slate-500">Nhóm chức năng</label>
            <select
              v-model="groupFunction"
              class="input mt-1 h-8 w-full text-xs"
            >
              <option value="all">
                Tất cả
              </option>
              <option
                v-for="o in options.group_function"
                :key="o.value"
                :value="o.value"
              >
                {{ o.label }}
              </option>
            </select>
          </div>
          <div
            v-if="visibleFilters.tool"
            class="min-w-0 rounded-lg border border-slate-200/80 bg-white px-2.5 py-1.5"
          >
            <label class="text-[10px] font-medium text-slate-500">Loại AI</label>
            <select
              v-model="tool"
              class="input mt-1 h-8 w-full text-xs"
            >
              <option value="all">
                Tất cả
              </option>
              <option
                v-for="t in filterOptions.tools"
                :key="t"
                :value="t"
              >
                {{ t }}
              </option>
            </select>
          </div>
          <div
            v-if="visibleFilters.vendor"
            class="min-w-0 rounded-lg border border-slate-200/80 bg-white px-2.5 py-1.5"
          >
            <label class="text-[10px] font-medium text-slate-500">Nhà cung cấp</label>
            <select
              v-model="vendor"
              class="input mt-1 h-8 w-full text-xs"
            >
              <option value="all">
                Tất cả
              </option>
              <option
                v-for="v in filterOptions.vendors"
                :key="v"
                :value="v"
              >
                {{ v }}
              </option>
            </select>
          </div>
          <div
            v-if="visibleFilters.status"
            class="min-w-0 rounded-lg border border-slate-200/80 bg-white px-2.5 py-1.5"
          >
            <label class="text-[10px] font-medium text-slate-500">Trạng thái TK</label>
            <select
              v-model="status"
              class="input mt-1 h-8 w-full text-xs"
            >
              <option value="all">
                Tất cả
              </option>
              <option
                v-for="o in options.status"
                :key="o.value"
                :value="o.value"
              >
                {{ o.label }}
              </option>
            </select>
          </div>
          <div
            v-if="visibleFilters.lifecycle"
            class="min-w-0 rounded-lg border border-slate-200/80 bg-white px-2.5 py-1.5"
          >
            <label class="text-[10px] font-medium text-slate-500">Vòng đời</label>
            <select
              v-model="lifecycleStatus"
              class="input mt-1 h-8 w-full text-xs"
            >
              <option value="all">
                Tất cả
              </option>
              <option
                v-for="o in options.lifecycle_status"
                :key="o.value"
                :value="o.value"
              >
                {{ o.label }}
              </option>
            </select>
          </div>
          <div
            v-if="visibleFilters.proposer"
            class="min-w-0 rounded-lg border border-slate-200/80 bg-white px-2.5 py-1.5"
          >
            <label class="text-[10px] font-medium text-slate-500">Người đề xuất</label>
            <select
              v-model="proposer"
              class="input mt-1 h-8 w-full text-xs"
            >
              <option value="all">
                Tất cả
              </option>
              <option
                v-for="p in filterOptions.proposers"
                :key="p"
                :value="p"
              >
                {{ p }}
              </option>
            </select>
          </div>
          <div
            v-if="visibleFilters.cost_range"
            class="min-w-0 rounded-lg border border-slate-200/80 bg-white px-2.5 py-1.5"
          >
            <span class="text-[10px] font-medium text-slate-500">Chi phí / tháng (VNĐ)</span>
            <div class="mt-1 flex items-center gap-1">
              <input
                v-model="costMin"
                type="number"
                placeholder="Từ"
                class="input h-8 min-w-0 flex-1 text-xs"
                aria-label="Chi phí tối thiểu"
              >
              <span class="shrink-0 text-xs text-slate-300">→</span>
              <input
                v-model="costMax"
                type="number"
                placeholder="Đến"
                class="input h-8 min-w-0 flex-1 text-xs"
                aria-label="Chi phí tối đa"
              >
            </div>
          </div>
        </div>
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
