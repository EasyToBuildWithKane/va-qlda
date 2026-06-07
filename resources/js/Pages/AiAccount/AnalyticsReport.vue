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
import {
    isAnchoredDropdownTarget,
    useAnchoredDropdownStyle,
} from '@/shared/composables/useAnchoredDropdownStyle';
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
const { panelStyle: colPanelStyle } = useAnchoredDropdownStyle(colDdRef, showColDd);
const { panelStyle: exportPanelStyle } = useAnchoredDropdownStyle(exportDdRef, showExportDd, { width: 176 });
const groupBy = ref('');
const debounceTimer = ref(null);

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

const displayRows = computed(() => {
    if (!groupBy.value) return rows.value.map((r) => ({ type: 'row', row: r }));
    const groups = {};
    rows.value.forEach((row) => {
        const key = row[groupBy.value] ?? '—';
        groups[key] = groups[key] ?? { key, rows: [], sum: 0 };
        groups[key].rows.push(row);
        groups[key].sum += Number(row.cost_monthly) || 0;
    });
    return Object.values(groups).flatMap((g) => [
        { type: 'group', label: g.key, count: g.rows.length, sum: g.sum },
        ...g.rows.map((row) => ({ type: 'row', row })),
    ]);
});

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

function onDocMouseDown(e) {
    if (isAnchoredDropdownTarget(e.target)) {
        return;
    }
    [filterPanelDdRef, colDdRef, exportDdRef].forEach((r) => {
        if (r.value && !r.value.contains(e.target)) {
            showFilterPanelDd.value = false;
            showColDd.value = false;
            showExportDd.value = false;
        }
    });
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
      <div class="border-b border-slate-100 px-5 py-3">
        <div class="flex flex-wrap items-center gap-2">
          <div class="flex min-w-0 flex-1 items-center gap-2">
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
              :anchor="filterPanelDdRef"
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
            <Teleport to="body">
              <div
                v-if="showColDd"
                data-va-anchored-dropdown
                :style="colPanelStyle"
                class="max-h-[min(20rem,70vh)] overflow-y-auto rounded-xl border border-slate-200 bg-white py-2 shadow-elevation-2"
                @mousedown.stop
              >
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
            </Teleport>
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
            <Teleport to="body">
              <div
                v-if="showExportDd"
                data-va-anchored-dropdown
                :style="exportPanelStyle"
                class="rounded-xl border border-slate-200 bg-white py-1 shadow-elevation-2"
                @mousedown.stop
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
            </Teleport>
          </div>
          <select
            v-model="groupBy"
            class="h-9 rounded-lg border border-slate-200 px-2 text-xs text-slate-700"
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
      </div>

      <div
        v-if="hasFilterRow"
        class="flex flex-wrap gap-2 border-b border-slate-100 px-5 py-2.5"
      >
        <template v-if="visibleFilters.date_created">
          <input
            v-model="createdFrom"
            type="date"
            class="h-8 rounded border border-slate-200 px-2 text-xs"
            aria-label="Từ ngày PĐX"
          >
          <input
            v-model="createdTo"
            type="date"
            class="h-8 rounded border border-slate-200 px-2 text-xs"
            aria-label="Đến ngày PĐX"
          >
        </template>
        <template v-if="visibleFilters.purchase_date">
          <input
            v-model="purchaseFrom"
            type="date"
            class="h-8 rounded border border-slate-200 px-2 text-xs"
          >
          <input
            v-model="purchaseTo"
            type="date"
            class="h-8 rounded border border-slate-200 px-2 text-xs"
          >
        </template>
        <template v-if="visibleFilters.expiry">
          <input
            v-model="expiryFrom"
            type="date"
            class="h-8 rounded border border-slate-200 px-2 text-xs"
          >
          <input
            v-model="expiryTo"
            type="date"
            class="h-8 rounded border border-slate-200 px-2 text-xs"
          >
        </template>
        <select
          v-if="visibleFilters.department"
          v-model="department"
          class="h-8 rounded border border-slate-200 px-2 text-xs"
        >
          <option value="all">
            Tất cả phòng ban
          </option>
          <option
            v-for="d in filterOptions.departments"
            :key="d"
            :value="d"
          >
            {{ d }}
          </option>
        </select>
        <select
          v-if="visibleFilters.group_function"
          v-model="groupFunction"
          class="h-8 rounded border border-slate-200 px-2 text-xs"
        >
          <option value="all">
            Tất cả nhóm
          </option>
          <option
            v-for="o in options.group_function"
            :key="o.value"
            :value="o.value"
          >
            {{ o.label }}
          </option>
        </select>
        <select
          v-if="visibleFilters.tool"
          v-model="tool"
          class="h-8 rounded border border-slate-200 px-2 text-xs"
        >
          <option value="all">
            Tất cả AI
          </option>
          <option
            v-for="t in filterOptions.tools"
            :key="t"
            :value="t"
          >
            {{ t }}
          </option>
        </select>
        <select
          v-if="visibleFilters.vendor"
          v-model="vendor"
          class="h-8 rounded border border-slate-200 px-2 text-xs"
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
        <select
          v-if="visibleFilters.status"
          v-model="status"
          class="h-8 rounded border border-slate-200 px-2 text-xs"
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
        <select
          v-if="visibleFilters.lifecycle"
          v-model="lifecycleStatus"
          class="h-8 rounded border border-slate-200 px-2 text-xs"
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
        <select
          v-if="visibleFilters.proposer"
          v-model="proposer"
          class="h-8 max-w-[12rem] rounded border border-slate-200 px-2 text-xs"
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
        <template v-if="visibleFilters.cost_range">
          <input
            v-model="costMin"
            type="number"
            placeholder="Chi phí min"
            class="h-8 w-28 rounded border border-slate-200 px-2 text-xs"
          >
          <input
            v-model="costMax"
            type="number"
            placeholder="Chi phí max"
            class="h-8 w-28 rounded border border-slate-200 px-2 text-xs"
          >
        </template>
      </div>

      <div class="w-full min-w-0 overflow-x-auto">
        <table class="w-full border-collapse text-left text-xs">
          <thead class="bg-slate-50">
            <tr>
              <th
                v-for="col in activeColumns"
                :key="col.key"
                class="whitespace-nowrap border-b border-slate-200 px-3 py-2.5 text-slate-600"
                :class="col.width ? '' : 'min-w-[7rem]'"
                :style="col.width ? { minWidth: col.width } : undefined"
              >
                {{ col.label }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td
                :colspan="activeColumns.length"
                class="px-3 py-8 text-center text-slate-400"
              >
                Đang tải…
              </td>
            </tr>
            <template
              v-for="(entry, idx) in displayRows"
              :key="idx"
            >
              <tr
                v-if="entry.type === 'group'"
                class="bg-brand/5 font-semibold text-brand"
              >
                <td
                  :colspan="activeColumns.length"
                  class="px-3 py-2"
                >
                  {{ entry.label }} · {{ entry.count }} bản ghi · {{ formatVnd(entry.sum) }}/tháng
                </td>
              </tr>
              <tr
                v-else
                class="border-b border-slate-100 hover:bg-slate-50/80"
              >
                <td
                  v-for="col in activeColumns"
                  :key="col.key"
                  class="whitespace-nowrap px-3 py-2 text-slate-700"
                  :class="col.key === 'notes' ? 'max-w-md whitespace-normal' : ''"
                  :title="String(entry.row[col.key] ?? '')"
                >
                  {{ cellValue(entry.row, col) }}
                </td>
              </tr>
            </template>
            <tr v-if="!loading && !rows.length">
              <td
                :colspan="activeColumns.length"
                class="px-3 py-10 text-center text-slate-400"
              >
                Không có bản ghi phù hợp bộ lọc.
              </td>
            </tr>
          </tbody>
        </table>
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
