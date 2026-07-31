<script setup>
import {
    computed, reactive, ref, watch, onMounted, onBeforeUnmount,
} from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Badge from '@/shared/ui/Badge.vue';
import EvaluationSummaryBar from '@/modules/evaluation/components/EvaluationSummaryBar.vue';
import EvaluationCriterionFormModal from '@/modules/evaluation/components/EvaluationCriterionFormModal.vue';
import EvaluationImportModal from '@/modules/evaluation/components/EvaluationImportModal.vue';
import EvaluationExportModal from '@/modules/evaluation/components/EvaluationExportModal.vue';
import EvaluationCriterionRowActions from '@/modules/evaluation/components/EvaluationCriterionRowActions.vue';
import EvaluationCategoryTabs from '@/modules/evaluation/components/EvaluationCategoryTabs.vue';
import EvaluationScoreLevelCell from '@/modules/evaluation/components/EvaluationScoreLevelCell.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import { EVALUATION_TABLE_COLUMNS } from '@/modules/evaluation/config/columns.js';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';
import { datetime } from '@/composables/useFormat';

const props = defineProps({
    criteria: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    departments: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    scopeOptions: { type: Array, default: () => [] },
    nextCode: { type: String, default: 'TCVA001' },
    defaultScoreLevels: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    viewer: {
        type: Object,
        default: () => ({
            can_manage_all: true,
            can_create_general: true,
            own_department_code: null,
            forced_department_code: null,
        }),
    },
});

const canManageAllDepts = computed(() => props.viewer?.can_manage_all !== false);
const canCreateGeneral = computed(() => props.viewer?.can_create_general !== false);

const confirmDelete = useConfirmDelete();
const filterPanelDdRef = ref(null);
const colDdRef = ref(null);
const tableScrollRef = ref(null);
const showFormModal = ref(false);
const showImportModal = ref(false);
const showExportModal = ref(false);
const editingCriterion = ref(null);
const tableDragging = ref(false);

let tablePointerId = null;
let tableStartX = 0;
let tableStartScrollLeft = 0;
let tableMoved = false;
let tableSuppressClick = false;
const TABLE_DRAG_THRESHOLD = 8;

function onTablePointerDown(e) {
    const el = tableScrollRef.value;
    if (!el || e.button !== 0) return;
    if (e.pointerType !== 'mouse') return;
    // Tab loại có scroller riêng — không bắt drag bảng tại đây
    if (e.target?.closest?.('.eval-cat-tabs')) return;

    tablePointerId = e.pointerId;
    tableStartX = e.clientX;
    tableStartScrollLeft = el.scrollLeft;
    tableMoved = false;
    tableSuppressClick = false;
    tableDragging.value = false;
}

function onTablePointerMove(e) {
    const el = tableScrollRef.value;
    if (!el || tablePointerId !== e.pointerId) return;

    const dx = e.clientX - tableStartX;
    if (!tableMoved && Math.abs(dx) < TABLE_DRAG_THRESHOLD) return;

    if (!tableMoved) {
        tableMoved = true;
        tableSuppressClick = true;
        tableDragging.value = true;
        try {
            el.setPointerCapture(e.pointerId);
        } catch {
            /* ignore */
        }
    }

    el.scrollLeft = tableStartScrollLeft - dx;
    e.preventDefault();
}

function endTablePointer(e) {
    const el = tableScrollRef.value;
    if (tablePointerId == null || (e?.pointerId != null && tablePointerId !== e.pointerId)) return;

    if (el?.hasPointerCapture?.(tablePointerId)) {
        try {
            el.releasePointerCapture(tablePointerId);
        } catch {
            /* ignore */
        }
    }

    const wasDragging = tableSuppressClick;
    tablePointerId = null;
    tableDragging.value = false;
    tableMoved = false;

    if (wasDragging) {
        setTimeout(() => {
            tableSuppressClick = false;
        }, 0);
    } else {
        tableSuppressClick = false;
    }
}

function onTableClickCapture(e) {
    if (!tableSuppressClick) return;
    e.preventDefault();
    e.stopPropagation();
}

const FILTER_CONTROLS = [
    { key: 'scope', label: 'Phạm vi', default: false },
    { key: 'department_code', label: 'Phòng ban', default: false },
    { key: 'category', label: 'Loại tiêu chí', default: false },
    { key: 'status', label: 'Trạng thái', default: false },
];

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const filters = reactive({
    q: props.filters.q || '',
    scope: props.filters.scope || '',
    department_code: props.filters.department_code || '',
    category: props.filters.category || '',
    status: props.filters.status || '',
});

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
} = useVisibleFilterControls(FILTER_CONTROLS, 'va-workspace.evaluation.visible-filters.v5');

const {
    visibleCols,
    showColDd,
    visibleColumnCount,
    persistVisibleColumns,
    openColPanel,
    isColVisible,
    TABLE_COLUMNS,
} = useVisibleColumns(EVALUATION_TABLE_COLUMNS, 'va-workspace.evaluation.columns.v10');

const GROUP_GENERAL = '__general__';
const GROUP_UNCATEGORIZED = '__uncategorized__';
const CATEGORY_EMPTY_LABEL = 'Chưa phân loại';
const COLLAPSE_STORAGE_KEY = 'va-workspace.evaluation.collapsed-groups.v4';

function loadCollapsedGroups() {
    try {
        const raw = localStorage.getItem(COLLAPSE_STORAGE_KEY);
        if (raw) return new Set(JSON.parse(raw));
    } catch {
        /* ignore */
    }
    return new Set();
}

const collapsedGroups = ref(loadCollapsedGroups());
const activeCategoryByGroup = reactive({});

const rows = computed(() => props.criteria?.data || []);

function categoryKeyOf(row) {
    const category = (row.category || '').trim();
    return category || GROUP_UNCATEGORIZED;
}

function buildCategoryTabs(items) {
    const map = new Map();
    for (const row of items) {
        const key = categoryKeyOf(row);
        if (!map.has(key)) {
            map.set(key, {
                key,
                label: key === GROUP_UNCATEGORIZED ? CATEGORY_EMPTY_LABEL : key,
                count: 0,
            });
        }
        map.get(key).count += 1;
    }
    return [...map.values()].sort((a, b) => {
        if (a.key === GROUP_UNCATEGORIZED) return 1;
        if (b.key === GROUP_UNCATEGORIZED) return -1;
        return a.label.localeCompare(b.label, 'vi');
    });
}

const groupedCriteria = computed(() => {
    const map = new Map();
    for (const row of rows.value) {
        const isGeneral = row.scope === 'general';
        const key = isGeneral ? GROUP_GENERAL : ((row.department_code || '').trim() || '__unknown__');
        if (!map.has(key)) {
            map.set(key, {
                key,
                code: isGeneral ? null : (row.department_code || null),
                label: isGeneral ? 'Tiêu chí chung' : (row.department_name || EMPTY_LABELS.team),
                items: [],
            });
        }
        const group = map.get(key);
        if (!isGeneral) {
            const name = (row.department_name || '').trim();
            if (name && group.label === EMPTY_LABELS.team) group.label = name;
        }
        group.items.push(row);
    }
    return [...map.values()]
        .sort((a, b) => {
            if (a.key === GROUP_GENERAL) return -1;
            if (b.key === GROUP_GENERAL) return 1;
            return a.label.localeCompare(b.label, 'vi');
        })
        .map((group) => ({
            ...group,
            categoryTabs: buildCategoryTabs(group.items),
        }));
});

/** Số cột mức điểm tối đa trên trang (theo dữ liệu đang load). */
const maxScoreLevelCount = computed(() => {
    let max = 0;
    for (const row of rows.value) {
        const n = Array.isArray(row?.score_levels) ? row.score_levels.length : 0;
        if (n > max) max = n;
    }
    if (max > 0) return max;
    const defaults = Array.isArray(props.defaultScoreLevels) ? props.defaultScoreLevels.length : 0;
    return defaults > 0 ? defaults : 0;
});

const scoreLevelColumnIndexes = computed(() => (
    Array.from({ length: maxScoreLevelCount.value }, (_, i) => i)
));

/** Header cột mức: Mức 1, Mức 2, … (chi tiết nhãn/điểm nằm trong ô). */
const scoreLevelHeaders = computed(() => scoreLevelColumnIndexes.value.map((index) => ({
    index,
    label: `Mức ${index + 1}`,
    title: `Cột mức điểm ${index + 1}`,
})));

const tableColspan = computed(() => (
    visibleColumnCount.value + 3 + scoreLevelColumnIndexes.value.length
));

function persistCollapsedGroups() {
    localStorage.setItem(COLLAPSE_STORAGE_KEY, JSON.stringify([...collapsedGroups.value]));
}

function isGroupExpanded(key) {
    return !collapsedGroups.value.has(key);
}

function toggleGroup(key) {
    const next = new Set(collapsedGroups.value);
    if (next.has(key)) next.delete(key);
    else next.add(key);
    collapsedGroups.value = next;
    persistCollapsedGroups();
}

function expandAllGroups() {
    collapsedGroups.value = new Set();
    persistCollapsedGroups();
}

function collapseAllGroups() {
    collapsedGroups.value = new Set(groupedCriteria.value.map((g) => g.key));
    persistCollapsedGroups();
}

const allGroupsExpanded = computed(() => (
    groupedCriteria.value.length > 0
    && groupedCriteria.value.every((g) => isGroupExpanded(g.key))
));

function toggleAllGroups() {
    if (allGroupsExpanded.value) collapseAllGroups();
    else expandAllGroups();
}

function activeCategoryTab(group) {
    const current = activeCategoryByGroup[group.key];
    if (current && group.categoryTabs.some((tab) => tab.key === current)) {
        return current;
    }
    return group.categoryTabs[0]?.key ?? GROUP_UNCATEGORIZED;
}

function setCategoryTab(groupKey, tabKey) {
    activeCategoryByGroup[groupKey] = tabKey;
}

function visibleGroupItems(group) {
    const tabKey = activeCategoryTab(group);
    return group.items.filter((row) => categoryKeyOf(row) === tabKey);
}

function scoreLevelAt(row, index) {
    const levels = Array.isArray(row?.score_levels) ? row.score_levels : [];
    return levels[index] || null;
}

watch(groupedCriteria, (groups) => {
    for (const group of groups) {
        const current = activeCategoryByGroup[group.key];
        if (!current || !group.categoryTabs.some((tab) => tab.key === current)) {
            activeCategoryByGroup[group.key] = group.categoryTabs[0]?.key ?? GROUP_UNCATEGORIZED;
        }
    }
}, { immediate: true });

let searchTimer = null;
watch(() => filters.q, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 350);
});

function applyFilters(extra = {}) {
    router.get(route('workspace.evaluation.index'), {
        q: filters.q || undefined,
        scope: filters.scope || undefined,
        department_code: filters.department_code || undefined,
        category: filters.category || undefined,
        status: filters.status || undefined,
        ...extra,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function onQuickFilter(payload) {
    filters.status = payload.status || '';
    filters.scope = payload.scope || '';
    applyFilters();
}

function openCreate() {
    editingCriterion.value = null;
    showFormModal.value = true;
}

function openEdit(row) {
    editingCriterion.value = row;
    showFormModal.value = true;
}

function closeFormModal() {
    showFormModal.value = false;
    editingCriterion.value = null;
}

function onDelete(row) {
    confirmDelete(
        `Xoá tiêu chí «${row.criteria_name}»? Thao tác không thể hoàn tác trên giao diện.`,
        () => router.delete(route('workspace.evaluation.destroy', row.id), { preserveScroll: true }),
    );
}

function openFilterPanelSafe() {
    openFilterPanel(() => {
        showColDd.value = false;
    });
}

function openColPanelSafe() {
    openColPanel(() => {
        showFilterPanelDd.value = false;
    });
}

function openImportModal() {
    showImportModal.value = true;
}

function closeImportModal() {
    showImportModal.value = false;
}

function onImported() {
    showImportModal.value = false;
    router.reload({ only: ['criteria', 'summary', 'categories', 'nextCode'], preserveScroll: true });
}

function openExportModal() {
    showExportModal.value = true;
}

function closeExportModal() {
    showExportModal.value = false;
}

function onDocClick(e) {
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
    if (colDdRef.value && !colDdRef.value.contains(e.target)) {
        showColDd.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', onDocClick));
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocClick);
    clearTimeout(searchTimer);
    tablePointerId = null;
});
</script>

<template>
  <Head title="Cấu hình tiêu chí đánh giá" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Cấu hình tiêu chí đánh giá"
        subtitle="Tiêu chí chung và theo từng phòng ban — siêu quản trị"
        icon="award"
        :badge="summary.total ?? null"
      >
        <button
          v-if="can.manage"
          type="button"
          class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-sm"
          @click="openCreate"
        >
          <AppIcon
            name="add"
            :size="15"
          />
          Thêm tiêu chí
        </button>
      </PageHeader>
    </template>

    <EvaluationSummaryBar
      :summary="summary"
      :active-status="filters.status"
      :active-scope="filters.scope"
      @quick-filter="onQuickFilter"
    />

    <div class="card overflow-visible">
      <div class="relative z-20 border-b border-slate-100 px-5 py-4">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              v-model="filters.q"
              input-id="evaluation-search"
              hide-label
              stretch
              inline-actions
              input-height="h-10"
              placeholder="Tìm tên, mã, loại, mô tả…"
              aria-label="Tìm tiêu chí đánh giá"
            />
          </div>
          <div class="flex shrink-0 items-center gap-2">
            <div
              ref="filterPanelDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="filter"
                :active="enabledFilterControlCount > 0 || showFilterPanelDd"
                :title="enabledFilterControlCount ? `Lọc (${enabledFilterControlCount})` : 'Lọc'"
                @click="openFilterPanelSafe"
              >
                Lọc
                <span
                  v-if="enabledFilterControlCount"
                  class="ml-0.5 inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-brand px-1 text-[10px] font-semibold text-white"
                >{{ enabledFilterControlCount }}</span>
              </DatagridToolbarActionButton>
              <FilterVisibilityDropdown
                v-model="visibleFilters"
                :show="showFilterPanelDd"
                :anchor-ref="filterPanelDdRef"
                :controls="FILTER_CONTROLS"
                input-id-prefix="evaluation-filter-vis"
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
                :fixed-labels="['Tên tiêu chí']"
                input-id-prefix="evaluation-col-vis"
                @persist="persistVisibleColumns"
              />
            </div>

            <DatagridToolbarActionButton
              icon="export"
              title="Xuất dữ liệu tiêu chí đánh giá"
              @click="openExportModal"
            >
              Xuất
            </DatagridToolbarActionButton>

            <DatagridToolbarActionButton
              v-if="can.manage"
              icon="upload"
              title="Nhập tiêu chí từ Excel"
              @click="openImportModal"
            >
              Nhập
            </DatagridToolbarActionButton>
          </div>

          <div class="ml-auto flex shrink-0 items-center gap-2">
            <DatagridToolbarActionButton
              v-if="groupedCriteria.length"
              icon="chevron-down"
              :title="allGroupsExpanded ? 'Thu gọn tất cả nhóm' : 'Mở tất cả nhóm'"
              @click="toggleAllGroups"
            >
              <span class="hidden sm:inline">{{ allGroupsExpanded ? 'Thu nhóm' : 'Mở nhóm' }}</span>
              <span class="sm:hidden">{{ allGroupsExpanded ? 'Thu' : 'Mở' }}</span>
            </DatagridToolbarActionButton>
          </div>
        </div>

        <div
          v-if="hasFilterRow"
          class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
        >
          <DatagridFilterField v-if="visibleFilters.scope">
            <select
              v-model="filters.scope"
              :class="FILTER_CONTROL_CLASS"
              aria-label="Phạm vi"
              @change="applyFilters()"
            >
              <option value="">
                Phạm vi
              </option>
              <option
                v-for="opt in scopeOptions"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
          </DatagridFilterField>
          <DatagridFilterField v-if="canManageAllDepts && visibleFilters.department_code">
            <select
              v-model="filters.department_code"
              :class="FILTER_CONTROL_CLASS"
              aria-label="Phòng ban"
              @change="applyFilters()"
            >
              <option value="">
                Phòng ban
              </option>
              <option
                v-for="d in departments"
                :key="d.code"
                :value="d.code"
              >
                {{ d.name }}
              </option>
            </select>
          </DatagridFilterField>
          <DatagridFilterField v-if="visibleFilters.category">
            <select
              v-model="filters.category"
              :class="FILTER_CONTROL_CLASS"
              aria-label="Loại tiêu chí"
              @change="applyFilters()"
            >
              <option value="">
                Loại tiêu chí
              </option>
              <option
                v-for="cat in categories"
                :key="cat"
                :value="cat"
              >
                {{ cat }}
              </option>
            </select>
          </DatagridFilterField>
          <DatagridFilterField v-if="visibleFilters.status">
            <select
              v-model="filters.status"
              :class="FILTER_CONTROL_CLASS"
              aria-label="Trạng thái"
              @change="applyFilters()"
            >
              <option value="">
                Trạng thái
              </option>
              <option value="active">
                Hoạt động
              </option>
              <option value="inactive">
                Ngưng hoạt động
              </option>
            </select>
          </DatagridFilterField>
        </div>
      </div>

      <div
        ref="tableScrollRef"
        class="evaluation-table-wrap"
        :class="{ 'evaluation-table-wrap--dragging': tableDragging }"
        @pointerdown="onTablePointerDown"
        @pointermove="onTablePointerMove"
        @pointerup="endTablePointer"
        @pointercancel="endTablePointer"
        @lostpointercapture="endTablePointer"
        @click.capture="onTableClickCapture"
      >
        <table class="evaluation-table text-left text-sm">
          <colgroup>
            <col class="evaluation-table__col-spacer">
            <col class="evaluation-table__col-name">
            <col
              v-for="col in scoreLevelHeaders"
              :key="`col-score-${col.index}`"
              class="evaluation-table__col"
            >
            <col
              v-if="isColVisible('allow_half_score')"
              class="evaluation-table__col"
            >
            <col
              v-if="isColVisible('description')"
              class="evaluation-table__col"
            >
            <col
              v-if="isColVisible('creator')"
              class="evaluation-table__col"
            >
            <col
              v-if="isColVisible('created_at')"
              class="evaluation-table__col"
            >
            <col
              v-if="isColVisible('updated_at')"
              class="evaluation-table__col"
            >
            <col
              v-if="isColVisible('status')"
              class="evaluation-table__col"
            >
            <col class="evaluation-table__col-actions">
          </colgroup>
          <tbody>
            <template v-if="groupedCriteria.length">
              <template
                v-for="group in groupedCriteria"
                :key="group.key"
              >
                <tr class="border-y border-slate-200 bg-slate-100/80">
                  <td
                    :colspan="tableColspan"
                    class="max-w-0 px-3 py-2.5 sm:px-4"
                  >
                    <button
                      type="button"
                      class="flex w-full min-w-0 items-center gap-2 text-left"
                      @click="toggleGroup(group.key)"
                    >
                      <AppIcon
                        :name="isGroupExpanded(group.key) ? 'chevron-down' : 'chevron-right'"
                        :size="14"
                        class="shrink-0 text-slate-400"
                      />
                      <span class="min-w-0 truncate text-sm font-semibold text-slate-800">
                        {{ group.label }}
                      </span>
                      <span
                        v-if="group.code"
                        class="hidden shrink-0 font-mono text-[11px] text-slate-400 sm:inline"
                      >({{ group.code }})</span>
                      <span class="ml-auto shrink-0 rounded-full bg-white px-2 py-0.5 text-[11px] tabular-nums text-slate-500 ring-1 ring-slate-200/80 sm:ml-1">
                        {{ group.items.length }}
                      </span>
                    </button>
                  </td>
                </tr>
                <template v-if="isGroupExpanded(group.key)">
                  <tr
                    v-if="group.categoryTabs.length"
                    class="bg-white"
                  >
                    <td
                      :colspan="tableColspan"
                      class="max-w-0 px-2 pt-1 sm:px-3 sm:pl-8"
                    >
                      <div class="min-w-0 max-w-full">
                        <EvaluationCategoryTabs
                          :tabs="group.categoryTabs"
                          :model-value="activeCategoryTab(group)"
                          :aria-label="`Loại tiêu chí — ${group.label}`"
                          @update:model-value="setCategoryTab(group.key, $event)"
                        />
                      </div>
                    </td>
                  </tr>
                  <tr class="bg-slate-50/90 text-[11px] uppercase tracking-wide text-slate-500">
                    <th
                      class="evaluation-table__cell-spacer px-1 py-2.5"
                      aria-hidden="true"
                      scope="col"
                    />
                    <th
                      class="evaluation-table__cell-name px-2.5 py-2.5 text-left font-medium sm:px-3"
                      scope="col"
                    >
                      Tên tiêu chí
                    </th>
                    <th
                      v-for="col in scoreLevelHeaders"
                      :key="`th-score-${col.index}`"
                      class="evaluation-table__cell px-2 py-2.5 text-center font-medium"
                      scope="col"
                      :title="col.title"
                    >
                      {{ col.label }}
                    </th>
                    <th
                      v-if="isColVisible('allow_half_score')"
                      class="evaluation-table__cell px-2.5 py-2.5 text-left font-medium sm:px-3"
                      scope="col"
                    >
                      Chấm 0.5
                    </th>
                    <th
                      v-if="isColVisible('description')"
                      class="evaluation-table__cell px-2.5 py-2.5 text-left font-medium sm:px-3"
                      scope="col"
                    >
                      Mô tả
                    </th>
                    <th
                      v-if="isColVisible('creator')"
                      class="evaluation-table__cell px-2.5 py-2.5 text-left font-medium sm:px-3"
                      scope="col"
                    >
                      Người tạo
                    </th>
                    <th
                      v-if="isColVisible('created_at')"
                      class="evaluation-table__cell px-2.5 py-2.5 text-left font-medium sm:px-3"
                      scope="col"
                    >
                      Ngày tạo
                    </th>
                    <th
                      v-if="isColVisible('updated_at')"
                      class="evaluation-table__cell px-2.5 py-2.5 text-left font-medium sm:px-3"
                      scope="col"
                    >
                      Cập nhật
                    </th>
                    <th
                      v-if="isColVisible('status')"
                      class="evaluation-table__cell px-2.5 py-2.5 text-left font-medium sm:px-3"
                      scope="col"
                    >
                      Trạng thái
                    </th>
                    <th
                      class="evaluation-table__cell-actions px-2 py-2.5 text-center font-medium sm:px-3"
                      scope="col"
                    >
                      Thao tác
                    </th>
                  </tr>
                  <tr
                    v-for="row in visibleGroupItems(group)"
                    :key="row.id"
                    class="border-b border-slate-100 hover:bg-slate-50/60"
                  >
                    <td class="px-1 py-3" />
                    <td class="evaluation-table__cell-name px-2.5 py-3 align-top sm:px-3">
                      <Link
                        :href="route('workspace.evaluation.show', row.id)"
                        class="block whitespace-normal break-words font-medium leading-snug text-slate-800 hover:text-brand"
                      >
                        {{ row.criteria_name }}
                      </Link>
                      <span class="mt-0.5 block break-all font-mono text-[11px] text-slate-400">
                        {{ row.criteria_code }}
                      </span>
                    </td>
                    <td
                      v-for="col in scoreLevelHeaders"
                      :key="`${row.id}-score-${col.index}`"
                      class="evaluation-table__cell px-2 py-3 align-top"
                    >
                      <EvaluationScoreLevelCell :level="scoreLevelAt(row, col.index)" />
                    </td>
                    <td
                      v-if="isColVisible('allow_half_score')"
                      class="evaluation-table__cell px-2.5 py-3 text-slate-600 sm:px-3"
                    >
                      {{ row.allow_half_score ? 'Có' : 'Không' }}
                    </td>
                    <td
                      v-if="isColVisible('description')"
                      class="evaluation-table__cell px-2.5 py-3 align-top text-slate-600 sm:px-3"
                      :title="row.description || undefined"
                    >
                      <span class="line-clamp-3 break-words">
                        {{ displayOrEmpty(row.description, EMPTY_LABELS.notUpdated) }}
                      </span>
                    </td>
                    <td
                      v-if="isColVisible('creator')"
                      class="evaluation-table__cell px-2.5 py-3 align-top text-slate-600 sm:px-3"
                      :title="row.creator?.display_name || undefined"
                    >
                      <span class="line-clamp-2 break-words">
                        {{ displayOrEmpty(row.creator?.display_name, EMPTY_LABELS.notUpdated) }}
                      </span>
                    </td>
                    <td
                      v-if="isColVisible('created_at')"
                      class="evaluation-table__cell px-2.5 py-3 tabular-nums text-slate-600 sm:px-3"
                    >
                      {{ row.created_at ? datetime(row.created_at) : EMPTY_LABELS.notUpdated }}
                    </td>
                    <td
                      v-if="isColVisible('updated_at')"
                      class="evaluation-table__cell px-2.5 py-3 tabular-nums text-slate-600 sm:px-3"
                    >
                      {{ row.updated_at ? datetime(row.updated_at) : EMPTY_LABELS.notUpdated }}
                    </td>
                    <td
                      v-if="isColVisible('status')"
                      class="evaluation-table__cell px-2.5 py-3 sm:px-3"
                    >
                      <Badge
                        :color="row.is_active ? 'emerald' : 'slate'"
                        :label="row.is_active ? 'Hoạt động' : 'Ngưng'"
                      />
                    </td>
                    <td class="px-2 py-3 text-right sm:px-3">
                      <EvaluationCriterionRowActions
                        :criterion="row"
                        :can-manage="!!can.manage"
                        @edit="openEdit"
                        @delete="onDelete"
                      />
                    </td>
                  </tr>
                </template>
              </template>
            </template>
            <tr v-else>
              <td
                :colspan="tableColspan"
                class="px-5 py-12 text-center text-sm text-slate-500"
              >
                Chưa có tiêu chí đánh giá.
                <button
                  v-if="can.manage"
                  type="button"
                  class="ml-1 font-medium text-brand hover:underline"
                  @click="openCreate"
                >
                  Thêm tiêu chí
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        v-if="criteria?.meta?.total != null"
        class="flex items-center justify-between gap-3 border-t border-slate-100 px-5 py-3 text-sm text-slate-500"
      >
        <span>
          Hiển thị toàn bộ
          <span class="font-medium tabular-nums text-slate-700">{{ criteria.meta.total }}</span>
          tiêu chí
        </span>
      </div>
    </div>

    <EvaluationCriterionFormModal
      :show="showFormModal"
      :mode="editingCriterion ? 'edit' : 'create'"
      :criterion="editingCriterion"
      :departments="departments"
      :categories="categories"
      :next-code="nextCode"
      :default-score-levels="defaultScoreLevels"
      :can-create-general="canCreateGeneral"
      :default-department-code="viewer.forced_department_code || viewer.own_department_code || ''"
      @close="closeFormModal"
    />

    <EvaluationImportModal
      :show="showImportModal"
      :departments="departments"
      :categories="categories"
      :can-create-general="canCreateGeneral"
      :default-score-levels="defaultScoreLevels"
      @close="closeImportModal"
      @imported="onImported"
    />

    <EvaluationExportModal
      :show="showExportModal"
      :rows="rows"
      :filters="filters"
      :summary="summary"
      :visible-cols="visibleCols"
      @close="closeExportModal"
    />
  </AppLayout>
</template>

<style scoped>
.evaluation-table-wrap {
  max-width: 100%;
  overflow-x: auto;
  overscroll-behavior-x: contain;
  -webkit-overflow-scrolling: touch;
  cursor: grab;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.evaluation-table-wrap::-webkit-scrollbar {
  display: none;
  width: 0;
  height: 0;
}

.evaluation-table-wrap--dragging {
  cursor: grabbing;
  user-select: none;
}

.evaluation-table-wrap--dragging :deep(a),
.evaluation-table-wrap--dragging :deep(button) {
  pointer-events: none;
}

.evaluation-table {
  table-layout: fixed;
  width: max-content;
  min-width: 100%;
  border-collapse: collapse;
}

.evaluation-table__col {
  width: 150px;
  max-width: 150px;
}

.evaluation-table__col-name {
  width: 280px;
  max-width: 280px;
}

.evaluation-table__col-spacer {
  width: 2.25rem;
  max-width: 2.25rem;
}

.evaluation-table__col-actions {
  width: 4.5rem;
  max-width: 4.5rem;
}

.evaluation-table__cell {
  width: 150px;
  max-width: 150px;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.evaluation-table__cell-name {
  width: 280px;
  max-width: 280px;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.evaluation-table__cell-spacer {
  width: 2.25rem;
  max-width: 2.25rem;
}

.evaluation-table__cell-actions {
  width: 4.5rem;
  max-width: 4.5rem;
}
</style>
