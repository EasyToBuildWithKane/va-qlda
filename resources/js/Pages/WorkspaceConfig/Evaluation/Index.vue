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
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
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
    nextCode: { type: String, default: '1' },
    defaultScoreLabels: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const toast = useToast();
const confirmDelete = useConfirmDelete();
const filterPanelDdRef = ref(null);
const colDdRef = ref(null);
const exportDdRef = ref(null);
const showExportDd = ref(false);
const showFormModal = ref(false);
const editingCriterion = ref(null);
const perPage = ref(Number(props.filters.per_page) || 20);

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
} = useVisibleFilterControls(FILTER_CONTROLS, 'va-workspace.evaluation.visible-filters.v4');

const {
    visibleCols,
    showColDd,
    visibleColumnCount,
    persistVisibleColumns,
    openColPanel,
    isColVisible,
    TABLE_COLUMNS,
} = useVisibleColumns(EVALUATION_TABLE_COLUMNS, 'va-workspace.evaluation.columns.v4');

const GROUP_GENERAL = '__general__';
const COLLAPSE_STORAGE_KEY = 'va-workspace.evaluation.collapsed-groups.v2';

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

const rows = computed(() => props.criteria?.data || []);

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
    return [...map.values()].sort((a, b) => {
        if (a.key === GROUP_GENERAL) return -1;
        if (b.key === GROUP_GENERAL) return 1;
        return a.label.localeCompare(b.label, 'vi');
    });
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

let searchTimer = null;
watch(() => filters.q, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters({ page: 1 }), 350);
});

watch(perPage, () => applyFilters({ page: 1 }));

function applyFilters(extra = {}) {
    router.get(route('workspace.evaluation.index'), {
        q: filters.q || undefined,
        scope: filters.scope || undefined,
        department_code: filters.department_code || undefined,
        category: filters.category || undefined,
        status: filters.status || undefined,
        per_page: perPage.value,
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
    applyFilters({ page: 1 });
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

          <div
            v-if="groupedCriteria.length"
            class="ml-auto flex shrink-0 items-center gap-2"
          >
            <DatagridToolbarActionButton
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
              @change="applyFilters({ page: 1 })"
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
          <DatagridFilterField v-if="visibleFilters.department_code">
            <select
              v-model="filters.department_code"
              :class="FILTER_CONTROL_CLASS"
              aria-label="Phòng ban"
              @change="applyFilters({ page: 1 })"
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
              @change="applyFilters({ page: 1 })"
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
              @change="applyFilters({ page: 1 })"
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

      <div class="overflow-x-auto">
        <table class="min-w-full border-collapse text-left text-sm">
          <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
            <tr>
              <th
                class="w-9 px-1 py-3"
                aria-hidden="true"
              />
              <th class="px-5 py-3 font-medium">
                Tên tiêu chí
              </th>
              <th
                v-if="isColVisible('criteria_code')"
                class="px-5 py-3 font-medium"
              >
                Mã
              </th>
              <th
                v-if="isColVisible('category')"
                class="px-5 py-3 font-medium"
              >
                Loại
              </th>
              <th
                v-if="isColVisible('allow_half_score')"
                class="px-5 py-3 font-medium"
              >
                Chấm 0.5
              </th>
              <th
                v-if="isColVisible('description')"
                class="px-5 py-3 font-medium"
              >
                Mô tả
              </th>
              <th
                v-if="isColVisible('creator')"
                class="px-5 py-3 font-medium"
              >
                Người tạo
              </th>
              <th
                v-if="isColVisible('created_at')"
                class="px-5 py-3 font-medium"
              >
                Ngày tạo
              </th>
              <th
                v-if="isColVisible('updated_at')"
                class="px-5 py-3 font-medium"
              >
                Cập nhật
              </th>
              <th
                v-if="isColVisible('status')"
                class="px-5 py-3 font-medium"
              >
                Trạng thái
              </th>
              <th class="px-5 py-3 text-right font-medium">
                Hành động
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <template v-if="groupedCriteria.length">
              <template
                v-for="group in groupedCriteria"
                :key="group.key"
              >
                <tr class="bg-slate-50/90">
                  <td
                    :colspan="tableColspan"
                    class="px-3 py-2.5"
                  >
                    <button
                      type="button"
                      class="flex w-full items-center gap-2 text-left"
                      @click="toggleGroup(group.key)"
                    >
                      <AppIcon
                        :name="isGroupExpanded(group.key) ? 'chevron-down' : 'chevron-right'"
                        :size="14"
                        class="shrink-0 text-slate-400"
                      />
                      <span class="text-sm font-semibold text-slate-800">
                        {{ group.label }}
                      </span>
                      <span
                        v-if="group.code"
                        class="font-mono text-[11px] text-slate-400"
                      >({{ group.code }})</span>
                      <span class="ml-1 rounded-full bg-white px-2 py-0.5 text-[11px] tabular-nums text-slate-500 ring-1 ring-slate-200">
                        {{ group.items.length }}
                      </span>
                    </button>
                  </td>
                </tr>
                <template v-if="isGroupExpanded(group.key)">
                  <tr
                    v-for="row in group.items"
                    :key="row.id"
                    class="hover:bg-slate-50/60"
                  >
                    <td class="px-1 py-3" />
                    <td class="px-5 py-3">
                      <Link
                        :href="route('workspace.evaluation.show', row.id)"
                        class="font-medium text-slate-800 hover:text-brand"
                      >
                        {{ row.display_name || row.criteria_name }}
                      </Link>
                    </td>
                    <td
                      v-if="isColVisible('criteria_code')"
                      class="px-5 py-3 font-mono text-slate-700"
                    >
                      {{ row.criteria_code }}
                    </td>
                    <td
                      v-if="isColVisible('category')"
                      class="px-5 py-3 text-slate-700"
                    >
                      {{ displayOrEmpty(row.category, EMPTY_LABELS.notUpdated) }}
                    </td>
                    <td
                      v-if="isColVisible('allow_half_score')"
                      class="px-5 py-3 text-slate-600"
                    >
                      {{ row.allow_half_score ? 'Có' : 'Không' }}
                    </td>
                    <td
                      v-if="isColVisible('description')"
                      class="max-w-xs truncate px-5 py-3 text-slate-600"
                      :title="row.description || undefined"
                    >
                      {{ displayOrEmpty(row.description, EMPTY_LABELS.notUpdated) }}
                    </td>
                    <td
                      v-if="isColVisible('creator')"
                      class="px-5 py-3 text-slate-600"
                    >
                      {{ displayOrEmpty(row.creator?.display_name, EMPTY_LABELS.notUpdated) }}
                    </td>
                    <td
                      v-if="isColVisible('created_at')"
                      class="px-5 py-3 tabular-nums text-slate-600"
                    >
                      {{ row.created_at ? datetime(row.created_at) : EMPTY_LABELS.notUpdated }}
                    </td>
                    <td
                      v-if="isColVisible('updated_at')"
                      class="px-5 py-3 tabular-nums text-slate-600"
                    >
                      {{ row.updated_at ? datetime(row.updated_at) : EMPTY_LABELS.notUpdated }}
                    </td>
                    <td
                      v-if="isColVisible('status')"
                      class="px-5 py-3"
                    >
                      <Badge
                        :color="row.is_active ? 'emerald' : 'slate'"
                        :label="row.is_active ? 'Hoạt động' : 'Ngưng'"
                      />
                    </td>
                    <td class="px-5 py-3">
                      <div class="flex items-center justify-end gap-1">
                        <Link
                          :href="route('workspace.evaluation.show', row.id)"
                          class="btn-ghost h-8 w-8 p-0"
                          title="Xem"
                        >
                          <AppIcon
                            name="eye"
                            :size="14"
                          />
                        </Link>
                        <button
                          v-if="can.manage"
                          type="button"
                          class="btn-ghost h-8 w-8 p-0"
                          title="Sửa"
                          @click="openEdit(row)"
                        >
                          <AppIcon
                            name="edit"
                            :size="14"
                          />
                        </button>
                        <button
                          v-if="can.manage"
                          type="button"
                          class="btn-ghost h-8 w-8 p-0 text-rose-600"
                          title="Xóa"
                          @click="onDelete(row)"
                        >
                          <AppIcon
                            name="trash"
                            :size="14"
                          />
                        </button>
                      </div>
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

      <DatagridPaginationFooter
        v-if="criteria?.meta"
        :meta="criteria.meta"
        :per-page="perPage"
        @update:per-page="(v) => { perPage = v; }"
        @page="(page) => applyFilters({ page })"
      />
    </div>

    <EvaluationCriterionFormModal
      :show="showFormModal"
      :mode="editingCriterion ? 'edit' : 'create'"
      :criterion="editingCriterion"
      :departments="departments"
      :categories="categories"
      :scope-options="scopeOptions"
      :next-code="nextCode"
      :default-score-labels="defaultScoreLabels"
      @close="closeFormModal"
    />
  </AppLayout>
</template>
