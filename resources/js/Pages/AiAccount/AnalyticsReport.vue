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
import AiAccountSectionNav from '@/modules/aiAccount/components/AiAccountSectionNav.vue';
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
    savedFilters,
    favoriteFilters,
    loadFilterOptions,
    loadReport,
    snapshotFilters,
    applySnapshot,
    toggleFavorite,
    shareFilterUrl,
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
const showSavedDd = ref(false);
const filterPanelDdRef = ref(null);
const colDdRef = ref(null);
const exportDdRef = ref(null);
const savedDdRef = ref(null);
const saveFilterName = ref('');
const groupBy = ref('');
const freezeFirstCol = ref(true);
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

watch([
    search, department, groupFunction, tool, vendor, status, lifecycleStatus,
    proposalStatus, proposer, purchaseFrom, purchaseTo, expiryFrom, expiryTo,
    createdFrom, createdTo, costMin, costMax,
], scheduleLoad);

function onDocMouseDown(e) {
    [filterPanelDdRef, colDdRef, exportDdRef, savedDdRef].forEach((r) => {
        if (r.value && !r.value.contains(e.target)) {
            showFilterPanelDd.value = false;
            showColDd.value = false;
            showExportDd.value = false;
            showSavedDd.value = false;
        }
    });
}

onMounted(async () => {
    document.addEventListener('mousedown', onDocMouseDown);
    applyFromQuery(new URLSearchParams(window.location.search));
    await loadFilterOptions();
    await loadReport();
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocMouseDown);
    clearTimeout(debounceTimer.value);
});

function saveCurrentFilter() {
    const name = saveFilterName.value.trim() || `Bộ lọc ${new Date().toLocaleString('vi-VN')}`;
    snapshotFilters(name);
    saveFilterName.value = '';
    toast.success('Đã lưu bộ lọc.');
}

async function copyShareLink() {
    const url = shareFilterUrl();
    try {
        await navigator.clipboard.writeText(url);
        toast.success('Đã copy link chia sẻ bộ lọc.');
    } catch {
        toast.error('Không copy được link.');
    }
}

async function exportExcel() {
    showExportDd.value = false;
    if (!rows.value.length) {
        toast.error('Không có dữ liệu để xuất.');
        return;
    }
    if (!dashboardLoader.data.value) {
        await dashboardLoader.load();
    }
    const keys = activeColumns.value.map((c) => c.key);
    exportAiAnalyticsWorkbook({
        rows: rows.value,
        stats: stats.value,
        dashboard: dashboardLoader.data.value,
        visibleColumnKeys: keys,
        exporterName: props.exporter?.name,
        filterNote: search.value ? `Tìm: ${search.value}` : '',
    });
    toast.success('Đã xuất Excel báo cáo AI.');
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
  <AppLayout>
    <Head title="Báo cáo phân tích AI" />
    <div class="mx-auto max-w-[1600px] space-y-4 px-4 py-5 sm:px-6 lg:px-8">
      <PageHeader
        title="Báo cáo phân tích chuyên sâu"
        subtitle="Bảng dữ liệu tài khoản AI — lọc, nhóm, xuất Excel báo cáo quản trị"
      />

      <AiAccountSectionNav active="analytics" />

      <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 p-3">
          <div class="flex min-w-0 flex-1 items-center gap-2">
            <DatagridToolbarSearch
              v-model="search"
              input-id="ai-analytics-search"
              placeholder="Mã hồ sơ, sản phẩm, người dùng…"
            />
          </div>
          <div
            ref="filterPanelDdRef"
            class="relative"
          >
            <button
              type="button"
              class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
              @click="toggleFilterPanel"
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
            class="relative"
          >
            <button
              type="button"
              class="inline-flex h-9 items-center gap-1 rounded-btn border border-slate-200 px-2.5 text-xs font-medium text-slate-700"
              @click="showColDd = !showColDd; showExportDd = false; showSavedDd = false"
            >
              <AppIcon
                name="columns"
                :size="15"
              /><span>Cột</span>
            </button>
            <div
              v-if="showColDd"
              class="absolute right-0 z-30 mt-1 max-h-80 w-56 overflow-auto rounded-lg border border-slate-200 bg-white py-2 shadow-lg"
            >
              <label class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-xs">
                <input
                  v-model="freezeFirstCol"
                  type="checkbox"
                  class="rounded border-slate-300 text-brand"
                >
                Ghim cột đầu
              </label>
              <p class="px-3 pb-1 pt-2 text-[10px] font-semibold uppercase text-slate-400">
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
                  v-model="visibleCols[col.key]"
                  type="checkbox"
                  class="rounded border-slate-300 text-brand"
                  @change="persistColumns"
                >
                {{ col.label }}
              </div>
            </div>
          </div>
          <div
            ref="savedDdRef"
            class="relative"
          >
            <button
              type="button"
              class="inline-flex h-9 items-center gap-1 rounded-btn border border-slate-200 px-2.5 text-xs font-medium text-slate-700"
              @click="showSavedDd = !showSavedDd; showColDd = false; showExportDd = false"
            >
              <AppIcon
                name="star"
                :size="15"
              /><span>Đã lưu</span>
            </button>
            <div
              v-if="showSavedDd"
              class="absolute right-0 z-30 mt-1 w-72 rounded-lg border border-slate-200 bg-white p-3 shadow-lg"
            >
              <div class="flex gap-2">
                <input
                  v-model="saveFilterName"
                  type="text"
                  placeholder="Tên bộ lọc"
                  class="h-8 flex-1 rounded border border-slate-200 px-2 text-xs"
                >
                <button
                  type="button"
                  class="btn-primary h-8 px-2 text-xs"
                  @click="saveCurrentFilter"
                >
                  Lưu
                </button>
              </div>
              <button
                type="button"
                class="mt-2 text-xs text-brand hover:underline"
                @click="copyShareLink"
              >
                Chia sẻ link bộ lọc hiện tại
              </button>
              <ul class="mt-2 max-h-48 space-y-1 overflow-auto text-xs">
                <li
                  v-for="item in savedFilters"
                  :key="item.id"
                  class="flex items-center justify-between gap-2 rounded px-2 py-1 hover:bg-slate-50"
                >
                  <button
                    type="button"
                    class="truncate text-left"
                    @click="applySnapshot(item.values); loadReport()"
                  >
                    {{ item.name }}
                  </button>
                  <button
                    type="button"
                    class="shrink-0"
                    :class="favoriteFilters.includes(item.id) ? 'text-amber-500' : 'text-slate-300'"
                    @click="toggleFavorite(item.id)"
                  >
                    ★
                  </button>
                </li>
              </ul>
            </div>
          </div>
          <div
            ref="exportDdRef"
            class="relative"
          >
            <button
              type="button"
              class="inline-flex h-9 items-center gap-1 rounded-btn border border-slate-200 px-2.5 text-xs font-medium text-slate-700"
              @click="showExportDd = !showExportDd; showColDd = false"
            >
              <AppIcon
                name="export"
                :size="15"
              /><span>Xuất</span>
            </button>
            <div
              v-if="showExportDd"
              class="absolute right-0 z-30 mt-1 w-44 rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
            >
              <button
                type="button"
                class="block w-full px-3 py-2 text-left text-xs hover:bg-slate-50"
                @click="exportExcel"
              >
                Excel báo cáo (.xlsx)
              </button>
            </div>
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

        <div
          v-if="hasFilterRow"
          class="flex flex-wrap gap-2 border-b border-slate-100 px-3 py-2.5"
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

        <div class="relative max-h-[32rem] overflow-auto">
          <table class="min-w-full border-collapse text-left text-xs">
            <thead class="sticky top-0 z-10 bg-slate-50 shadow-sm">
              <tr>
                <th
                  v-for="(col, ci) in activeColumns"
                  :key="col.key"
                  class="whitespace-nowrap border-b border-slate-200 px-3 py-2.5 font-semibold text-slate-600"
                  :class="freezeFirstCol && ci === 0 ? 'sticky left-0 z-20 bg-slate-50' : ''"
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
                    v-for="(col, ci) in activeColumns"
                    :key="col.key"
                    class="max-w-xs truncate px-3 py-2 text-slate-700"
                    :class="freezeFirstCol && ci === 0 ? 'sticky left-0 bg-white' : ''"
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
          class="grid gap-3 border-t border-slate-200 bg-slate-50/80 p-4 sm:grid-cols-2 lg:grid-cols-4"
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
          <div>
            <p class="text-[10px] font-semibold uppercase text-slate-400">
              Top phòng ban
            </p>
            <p class="text-sm text-slate-700">
              {{ stats.by_department?.[0]?.department ?? '—' }}
              <span
                v-if="stats.by_department?.[0]"
                class="text-brand"
              >
                ({{ formatVnd(stats.by_department[0].cost_monthly) }})
              </span>
            </p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
