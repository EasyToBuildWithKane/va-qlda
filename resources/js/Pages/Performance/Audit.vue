<script setup>
import {
    computed, reactive, ref, watch, onMounted, onBeforeUnmount,
} from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import PerformanceAuditSummaryBar from '@/modules/performance/components/PerformanceAuditSummaryBar.vue';
import PerformanceAuditPeriodCell from '@/modules/performance/components/PerformanceAuditPeriodCell.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridSegmentedControl from '@/shared/ui/DatagridSegmentedControl.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import FilterDatePicker from '@/shared/ui/FilterDatePicker.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { PERFORMANCE_AUDIT_TABLE_COLUMNS } from '@/modules/performance/config/auditColumns.js';
import { usePerformanceExport } from '@/modules/performance/composables/usePerformanceExport.js';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { useFixedDropdownAnchor } from '@/shared/composables/useFixedDropdownAnchor';
import { useToast } from '@/shared/composables/useToast';
import { displayOrEmpty, EMPTY_LABELS, auditGradeLabel } from '@/shared/utils/emptyDisplay.js';

const props = defineProps({
    employees: { type: Object, required: true },
    summary: { type: Object, default: () => ({}) },
    filter: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const toast = useToast();
const { exportAuditList } = usePerformanceExport();

const PERIOD_TABS = [
    { key: 'week', label: 'Tuần', icon: 'weekly' },
    { key: 'month', label: 'Tháng', icon: 'daily' },
    { key: 'quarter', label: 'Quý', icon: 'calendar' },
];

const FILTER_CONTROLS = [
    { key: 'anchor_date', label: 'Mốc thời gian', default: false },
    { key: 'department', label: 'Phòng ban', default: false },
    { key: 'team', label: 'Đơn vị', default: false },
];

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const filterPanelDdRef = ref(null);
const colDdRef = ref(null);
const exportRef = ref(null);
const exportMenu = ref(false);
const exporting = ref(false);
const perPage = ref(Number(props.filters.per_page) || 20);

const form = reactive({
    q: props.filters.q || '',
    kpi: props.filters.kpi || '',
    period: props.filter.period ?? 'month',
    date: props.filter.date ?? '',
    department: props.filter.department ?? '',
    team: props.filter.team ?? '',
});

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS: filterControlDefs,
} = useVisibleFilterControls(FILTER_CONTROLS, 'va-qlda.performance-audit.visible-filters.v1');

const {
    visibleCols,
    showColDd,
    visibleColumnCount,
    persistVisibleColumns,
    openColPanel,
    isColVisible,
    TABLE_COLUMNS,
} = useVisibleColumns(PERFORMANCE_AUDIT_TABLE_COLUMNS, 'va-qlda.performance-audit.columns.v1');

const tableColspan = computed(() => 1 + visibleColumnCount.value);

const { panelStyle: exportPanelStyle } = useFixedDropdownAnchor(
    () => exportRef.value,
    exportMenu,
    { width: 220, zIndex: 120 },
);

function routeParams(extra = {}) {
    const params = {
        q: form.q || undefined,
        kpi: form.kpi || undefined,
        period: form.period,
        date: form.date || undefined,
        department: form.department || undefined,
        team: form.team || undefined,
        per_page: perPage.value,
        ...extra,
    };
    const cleaned = {};
    Object.entries(params).forEach(([k, v]) => {
        if (v !== undefined && v !== null && v !== '') cleaned[k] = v;
    });
    return cleaned;
}

let debounce;
function apply(extra = {}) {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(route('performance.audit'), routeParams(extra), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 350);
}

watch(() => form.q, () => apply({ page: 1 }));

function applyImmediate(extra = {}) {
    router.get(route('performance.audit'), routeParams(extra), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function onQuickFilter({ kpi }) {
    form.kpi = kpi ?? '';
    applyImmediate({ page: 1, kpi: form.kpi || undefined });
}

function setPeriod(period) {
    form.period = period;
    applyImmediate({ page: 1 });
}

function shiftAnchor(delta) {
    if (!form.date) return;
    const d = new Date(`${form.date}T12:00:00`);
    if (Number.isNaN(d.getTime())) return;
    if (form.period === 'week') {
        d.setDate(d.getDate() + delta * 7);
    } else if (form.period === 'quarter') {
        d.setMonth(d.getMonth() + delta * 3);
    } else {
        d.setMonth(d.getMonth() + delta);
    }
    form.date = d.toISOString().slice(0, 10);
    applyImmediate({ page: 1 });
}

function detailHref(employeeId) {
    return route('performance.audit.show', {
        employee: employeeId,
        period: form.period,
        date: form.date || undefined,
        department: form.department || undefined,
        team: form.team || undefined,
    });
}

function onToolbarClickOutside(e) {
    const t = e.target;
    if (t.closest?.('[data-filter-visibility-panel]')) return;
    if (t.closest?.('[data-column-visibility-panel]')) return;
    if (t.closest?.('[data-performance-audit-export-panel]')) return;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(t)) {
        showFilterPanelDd.value = false;
    }
    if (colDdRef.value && !colDdRef.value.contains(t)) {
        showColDd.value = false;
    }
    if (exportRef.value && !exportRef.value.contains(t)) {
        exportMenu.value = false;
    }
}

function openFilterPanelSafe() {
    openFilterPanel(() => {
        showColDd.value = false;
        exportMenu.value = false;
    });
}

function openColPanelSafe() {
    openColPanel(() => {
        showFilterPanelDd.value = false;
        exportMenu.value = false;
    });
}

function toggleExportMenu() {
    exportMenu.value = !exportMenu.value;
    if (exportMenu.value) {
        showFilterPanelDd.value = false;
        showColDd.value = false;
    }
}

function runExport() {
    exportMenu.value = false;
    const rows = props.employees.data ?? [];
    if (!rows.length) {
        toast.warning('Không có dữ liệu để xuất trên trang này.');
        return;
    }
    exporting.value = true;
    try {
        exportAuditList(rows, props.filter);
        toast.success(`Đã xuất ${rows.length} nhân sự (trang hiện tại).`);
    } catch {
        toast.error('Xuất file thất bại. Thử lại sau.');
    } finally {
        exporting.value = false;
    }
}

function gradeTone(grade) {
    if (!grade || grade === EMPTY_LABELS.gradeNoCommitment) return 'slate';
    if (grade === 'S' || grade === 'A') return 'emerald';
    if (grade === 'B') return 'sky';
    if (grade === 'C') return 'amber';
    if (grade === 'D') return 'rose';
    return 'slate';
}

onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));
</script>

<template>
  <Head title="Audit nhân sự" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Audit nhân sự"
        :subtitle="`Danh sách cam kết & kết quả · ${displayOrEmpty(filter.label, EMPTY_LABELS.period)}`"
        icon="leaderboard"
        icon-color="brand"
        :badge="summary.total ?? null"
      />
    </template>

    <PerformanceAuditSummaryBar
      mode="list"
      :summary="summary"
      :active-kpi="form.kpi"
      @quick-filter="onQuickFilter"
    />

    <div class="card overflow-visible">
      <div class="relative z-20 border-b border-slate-100 px-5 py-4">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              v-model="form.q"
              input-id="performance-audit-search"
              hide-label
              stretch
              inline-actions
              input-height="h-10"
              placeholder="Tìm tên, vai trò, đơn vị…"
              aria-label="Tìm nhân sự audit"
            />
          </div>
          <div class="flex shrink-0 flex-wrap items-center gap-2">
            <div
              ref="filterPanelDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="filter"
                :active="showFilterPanelDd"
                :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${filterControlDefs.length})`"
                @click="openFilterPanelSafe"
              >
                Lọc
              </DatagridToolbarActionButton>
              <FilterVisibilityDropdown
                v-model="visibleFilters"
                :show="showFilterPanelDd"
                :anchor-ref="filterPanelDdRef"
                :controls="filterControlDefs"
                input-id-prefix="performance-audit-filter-vis"
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
                @click="openColPanelSafe"
              >
                Cột
              </DatagridToolbarActionButton>
              <ColumnVisibilityDropdown
                v-model="visibleCols"
                :show="showColDd"
                :columns="TABLE_COLUMNS"
                :anchor-ref="colDdRef"
                :fixed-labels="['Nhân sự']"
                input-id-prefix="performance-audit-col-vis"
                @persist="persistVisibleColumns"
              />
            </div>

            <div
              ref="exportRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="export"
                :active="exportMenu"
                :disabled="exporting"
                title="Xuất trang hiện tại"
                @click="toggleExportMenu"
              >
                {{ exporting ? 'Đang xuất…' : 'Xuất' }}
              </DatagridToolbarActionButton>
            </div>
          </div>

          <div class="ml-auto flex shrink-0 items-center gap-2">
            <DatagridSegmentedControl
              :model-value="form.period"
              :items="PERIOD_TABS"
              aria-label="Chọn kỳ audit"
              icon-only-below-sm
              @update:model-value="setPeriod"
            />
          </div>
        </div>

        <Teleport to="body">
          <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0"
          >
            <div
              v-if="exportMenu"
              :style="exportPanelStyle"
              class="overflow-hidden rounded-card border border-slate-200 bg-white p-1 shadow-elevation-2"
              data-performance-audit-export-panel
            >
              <button
                type="button"
                class="flex w-full flex-col rounded-btn px-3 py-2 text-left hover:bg-slate-50"
                @click="runExport"
              >
                <span class="text-sm font-medium text-slate-700">Excel (.xlsx)</span>
                <span class="text-[10px] text-slate-400">Trang đang xem</span>
              </button>
            </div>
          </Transition>
        </Teleport>

        <Transition name="fade-slide">
          <div
            v-if="hasFilterRow"
            class="mt-3 grid grid-cols-1 gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
          >
            <DatagridFilterField
              v-if="visibleFilters.anchor_date"
              class="sm:col-span-2 xl:col-span-2"
            >
              <div class="flex items-center gap-2">
                <button
                  type="button"
                  class="btn-ghost inline-flex h-10 w-10 shrink-0 items-center justify-center p-0"
                  aria-label="Kỳ trước"
                  @click="shiftAnchor(-1)"
                >
                  <AppIcon
                    name="chevron-left"
                    :size="18"
                  />
                </button>
                <FilterDatePicker
                  v-model="form.date"
                  placeholder="Mốc thời gian"
                  class="min-w-0 flex-1"
                  @update:model-value="applyImmediate({ page: 1 })"
                />
                <button
                  type="button"
                  class="btn-ghost inline-flex h-10 w-10 shrink-0 items-center justify-center p-0"
                  aria-label="Kỳ sau"
                  @click="shiftAnchor(1)"
                >
                  <AppIcon
                    name="chevron-right"
                    :size="18"
                  />
                </button>
              </div>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.department">
              <select
                v-model="form.department"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Phòng ban"
                @change="applyImmediate({ page: 1 })"
              >
                <option value="">
                  Phòng ban
                </option>
                <option
                  v-for="d in options.departments"
                  :key="d.value"
                  :value="d.value"
                >
                  {{ d.label }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.team">
              <select
                v-model="form.team"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Đơn vị"
                @change="applyImmediate({ page: 1 })"
              >
                <option value="">
                  Đơn vị
                </option>
                <option
                  v-for="t in options.teams"
                  :key="t.value"
                  :value="t.value"
                >
                  {{ t.label }}
                </option>
              </select>
            </DatagridFilterField>
          </div>
        </Transition>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-5 py-3">
                Nhân sự
              </th>
              <th
                v-if="isColVisible('team')"
                class="px-5 py-3"
              >
                Đơn vị
              </th>
              <th
                v-if="isColVisible('period')"
                class="px-5 py-3"
              >
                Kỳ
              </th>
              <th
                v-if="isColVisible('committed')"
                class="px-5 py-3"
              >
                Cam kết
              </th>
              <th
                v-if="isColVisible('done')"
                class="px-5 py-3"
              >
                Hoàn thành
              </th>
              <th
                v-if="isColVisible('commitment_rate')"
                class="px-5 py-3"
              >
                Tỷ lệ
              </th>
              <th
                v-if="isColVisible('score')"
                class="px-5 py-3"
              >
                Điểm
              </th>
              <th
                v-if="isColVisible('grade')"
                class="px-5 py-3"
              >
                Xếp loại
              </th>
              <th
                v-if="isColVisible('rank')"
                class="px-5 py-3"
              >
                Hạng
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in employees.data"
              :key="row.id"
              class="border-t border-slate-100 hover:bg-slate-50/80"
            >
              <td class="px-5 py-3">
                <Link
                  :href="detailHref(row.id)"
                  class="flex min-w-0 items-center gap-3"
                >
                  <Avatar
                    :name="row.name"
                    :src="row.avatar"
                    :size="36"
                  />
                  <div class="min-w-0">
                    <span class="font-medium text-brand hover:underline">{{ row.name }}</span>
                    <p
                      v-if="row.role"
                      class="truncate text-xs text-slate-500"
                    >
                      {{ row.role }}
                    </p>
                  </div>
                </Link>
              </td>
              <td
                v-if="isColVisible('team')"
                class="px-5 py-3 text-slate-600"
              >
                <span :class="{ 'text-slate-400 italic text-xs': !row.unitName }">
                  {{ displayOrEmpty(row.unitName, EMPTY_LABELS.team) }}
                </span>
              </td>
              <td
                v-if="isColVisible('period')"
                class="px-5 py-3"
              >
                <PerformanceAuditPeriodCell
                  :row="row"
                  :filter-label="filter.label"
                />
              </td>
              <td
                v-if="isColVisible('committed')"
                class="px-5 py-3 tabular-nums"
              >
                {{ row.committed }}
              </td>
              <td
                v-if="isColVisible('done')"
                class="px-5 py-3 tabular-nums"
              >
                {{ row.done }}
              </td>
              <td
                v-if="isColVisible('commitment_rate')"
                class="px-5 py-3 tabular-nums"
              >
                {{ row.commitmentRate }}%
              </td>
              <td
                v-if="isColVisible('score')"
                class="px-5 py-3 tabular-nums font-medium"
              >
                {{ row.avgScore }}%
              </td>
              <td
                v-if="isColVisible('grade')"
                class="px-5 py-3"
              >
                <Badge
                  v-if="row.committed > 0 && row.grade"
                  :label="row.grade"
                  :color="gradeTone(row.grade)"
                />
                <span
                  v-else
                  class="text-xs italic text-slate-400"
                >{{ auditGradeLabel(row.grade, (row.committed ?? 0) > 0) }}</span>
              </td>
              <td
                v-if="isColVisible('rank')"
                class="px-5 py-3 tabular-nums text-slate-600"
              >
                #{{ row.rank }}
              </td>
            </tr>
            <tr v-if="!employees.data?.length">
              <td
                :colspan="Math.max(tableColspan, 1)"
                class="px-5 py-10 text-center text-slate-500"
              >
                Không có nhân sự phù hợp trong kỳ này.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <DatagridPaginationFooter
        v-model:per-page="perPage"
        :meta="employees.meta"
        @change="applyImmediate()"
      />
    </div>
  </AppLayout>
</template>
