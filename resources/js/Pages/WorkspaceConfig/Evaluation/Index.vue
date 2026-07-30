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
import EvaluationCriterionRowActions from '@/modules/evaluation/components/EvaluationCriterionRowActions.vue';
import EvaluationCategoryTabs from '@/modules/evaluation/components/EvaluationCategoryTabs.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import { EVALUATION_TABLE_COLUMNS } from '@/modules/evaluation/config/columns.js';
import {
    exportEvaluationWorkbook,
    exportEvaluationCsv,
} from '@/modules/evaluation/composables/useEvaluationExport.js';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { useToast } from '@/shared/composables/useToast';
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

const toast = useToast();
const confirmDelete = useConfirmDelete();
const filterPanelDdRef = ref(null);
const colDdRef = ref(null);
const exportDdRef = ref(null);
const showExportDd = ref(false);
const showFormModal = ref(false);
const editingCriterion = ref(null);

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
} = useVisibleColumns(EVALUATION_TABLE_COLUMNS, 'va-workspace.evaluation.columns.v8');

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

const tableColspan = computed(() => visibleColumnCount.value + 3);

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
        showExportDd.value = false;
    });
}

function openColPanelSafe() {
    openColPanel(() => {
        showFilterPanelDd.value = false;
        showExportDd.value = false;
    });
}

function openExportMenu() {
    showExportDd.value = !showExportDd.value;
    showFilterPanelDd.value = false;
    showColDd.value = false;
}

function runExport(format) {
    showExportDd.value = false;
    if (!rows.value.length) {
        toast.warning('Không có dữ liệu để xuất trên trang hiện tại.');
        return;
    }
    try {
        if (format === 'csv') {
            exportEvaluationCsv(rows.value);
        } else {
            exportEvaluationWorkbook(rows.value, { ...filters }, props.summary);
        }
        toast.success('Đã xuất file.');
    } catch {
        toast.error('Không xuất được file. Thử lại.');
    }
}

function onDocClick(e) {
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

onMounted(() => document.addEventListener('mousedown', onDocClick));
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocClick);
    clearTimeout(searchTimer);
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

            <div
              ref="exportDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="export"
                :active="showExportDd"
                title="Xuất CSV hoặc Excel"
                @click="openExportMenu"
              >
                Xuất
              </DatagridToolbarActionButton>
              <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 scale-95 -translate-y-1"
                leave-active-class="transition duration-100 ease-in"
                leave-to-class="opacity-0 scale-95 -translate-y-1"
              >
                <div
                  v-if="showExportDd"
                  class="absolute right-0 top-full z-30 mt-1.5 w-56 origin-top-right rounded-xl border border-slate-200 bg-white py-1 shadow-elevation-2"
                >
                  <p class="px-3 py-2 text-[10px] font-bold uppercase tracking-wide text-slate-400">
                    Trang hiện tại
                  </p>
                  <button
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
                    @click="runExport('xlsx')"
                  >
                    <AppIcon
                      name="export"
                      :size="15"
                      class="shrink-0 text-slate-400"
                    />
                    Excel (.xlsx)
                  </button>
                  <button
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
                    @click="runExport('csv')"
                  >
                    <AppIcon
                      name="documents"
                      :size="15"
                      class="shrink-0 text-slate-400"
                    />
                    CSV (.csv)
                  </button>
                </div>
              </Transition>
            </div>
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

      <div class="evaluation-table-wrap max-w-full overflow-x-auto">
        <table class="evaluation-table w-full max-w-full border-collapse text-left text-sm">
          <colgroup>
            <col class="w-9">
            <col>
            <col
              v-if="isColVisible('allow_half_score')"
              class="w-[6.5rem]"
            >
            <col
              v-if="isColVisible('description')"
              class="w-[14rem]"
            >
            <col
              v-if="isColVisible('creator')"
              class="w-[9rem]"
            >
            <col
              v-if="isColVisible('created_at')"
              class="w-[9.5rem]"
            >
            <col
              v-if="isColVisible('updated_at')"
              class="w-[9.5rem]"
            >
            <col
              v-if="isColVisible('status')"
              class="w-[7.5rem]"
            >
            <col class="w-12">
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
                      class="w-9 px-1 py-2.5"
                      aria-hidden="true"
                      scope="col"
                    />
                    <th
                      class="min-w-0 px-3 py-2.5 text-left font-medium sm:px-5"
                      scope="col"
                    >
                      Tên tiêu chí
                    </th>
                    <th
                      v-if="isColVisible('allow_half_score')"
                      class="px-3 py-2.5 text-left font-medium sm:px-5"
                      scope="col"
                    >
                      Chấm 0.5
                    </th>
                    <th
                      v-if="isColVisible('description')"
                      class="px-3 py-2.5 text-left font-medium sm:px-5"
                      scope="col"
                    >
                      Mô tả
                    </th>
                    <th
                      v-if="isColVisible('creator')"
                      class="px-3 py-2.5 text-left font-medium sm:px-5"
                      scope="col"
                    >
                      Người tạo
                    </th>
                    <th
                      v-if="isColVisible('created_at')"
                      class="px-3 py-2.5 text-left font-medium sm:px-5"
                      scope="col"
                    >
                      Ngày tạo
                    </th>
                    <th
                      v-if="isColVisible('updated_at')"
                      class="px-3 py-2.5 text-left font-medium sm:px-5"
                      scope="col"
                    >
                      Cập nhật
                    </th>
                    <th
                      v-if="isColVisible('status')"
                      class="px-3 py-2.5 text-left font-medium sm:px-5"
                      scope="col"
                    >
                      Trạng thái
                    </th>
                    <th
                      class="w-12 px-2 py-2.5 sm:px-3"
                      scope="col"
                      aria-label="Thao tác"
                    />
                  </tr>
                  <tr
                    v-for="row in visibleGroupItems(group)"
                    :key="row.id"
                    class="border-b border-slate-100 hover:bg-slate-50/60"
                  >
                    <td class="px-1 py-3" />
                    <td class="min-w-0 px-3 py-3 sm:px-5">
                      <Link
                        :href="route('workspace.evaluation.show', row.id)"
                        class="block truncate font-medium text-slate-800 hover:text-brand"
                        :title="row.criteria_name"
                      >
                        {{ row.criteria_name }}
                      </Link>
                      <span class="mt-0.5 block truncate font-mono text-[11px] text-slate-400">
                        {{ row.criteria_code }}
                      </span>
                    </td>
                    <td
                      v-if="isColVisible('allow_half_score')"
                      class="px-3 py-3 text-slate-600 sm:px-5"
                    >
                      {{ row.allow_half_score ? 'Có' : 'Không' }}
                    </td>
                    <td
                      v-if="isColVisible('description')"
                      class="min-w-0 truncate px-3 py-3 text-slate-600 sm:px-5"
                      :title="row.description || undefined"
                    >
                      {{ displayOrEmpty(row.description, EMPTY_LABELS.notUpdated) }}
                    </td>
                    <td
                      v-if="isColVisible('creator')"
                      class="min-w-0 truncate px-3 py-3 text-slate-600 sm:px-5"
                      :title="row.creator?.display_name || undefined"
                    >
                      {{ displayOrEmpty(row.creator?.display_name, EMPTY_LABELS.notUpdated) }}
                    </td>
                    <td
                      v-if="isColVisible('created_at')"
                      class="truncate px-3 py-3 tabular-nums text-slate-600 sm:px-5"
                    >
                      {{ row.created_at ? datetime(row.created_at) : EMPTY_LABELS.notUpdated }}
                    </td>
                    <td
                      v-if="isColVisible('updated_at')"
                      class="truncate px-3 py-3 tabular-nums text-slate-600 sm:px-5"
                    >
                      {{ row.updated_at ? datetime(row.updated_at) : EMPTY_LABELS.notUpdated }}
                    </td>
                    <td
                      v-if="isColVisible('status')"
                      class="px-3 py-3 sm:px-5"
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
  </AppLayout>
</template>

<style scoped>
.evaluation-table {
  table-layout: fixed;
}

.evaluation-table-wrap {
  overscroll-behavior-x: contain;
}
</style>
