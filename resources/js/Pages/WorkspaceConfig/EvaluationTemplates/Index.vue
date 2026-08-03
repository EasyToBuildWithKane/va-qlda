<script setup>
import {
    computed, reactive, ref, watch, onMounted, onBeforeUnmount,
} from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import EvaluationTemplateSummaryBar from '@/modules/evaluation-template/components/EvaluationTemplateSummaryBar.vue';
import EvaluationTemplateFormModal from '@/modules/evaluation-template/components/EvaluationTemplateFormModal.vue';
import EvaluationTemplateDataModal from '@/modules/evaluation-template/components/EvaluationTemplateDataModal.vue';
import EvaluationTemplateRowActions from '@/modules/evaluation-template/components/EvaluationTemplateRowActions.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import { EVALUATION_TEMPLATE_TABLE_COLUMNS } from '@/modules/evaluation-template/config/columns.js';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';
import { datetime } from '@/composables/useFormat';

const props = defineProps({
    templates: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    positions: { type: Array, default: () => [] },
    jobTitles: { type: Array, default: () => [] },
    jobRanks: { type: Array, default: () => [] },
    fieldTypeOptions: { type: Array, default: () => [] },
    criteriaOptions: { type: Array, default: () => [] },
    nextCode: { type: String, default: 'MDG001' },
    exportLogs: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const confirmDelete = useConfirmDelete();
const filterPanelDdRef = ref(null);
const colDdRef = ref(null);
const showFormModal = ref(false);
const showDataModal = ref(false);
const dataModalTab = ref('import');
const editingTemplate = ref(null);
const localExportLogs = ref([...(props.exportLogs || [])]);

watch(() => props.exportLogs, (logs) => {
    localExportLogs.value = [...(logs || [])];
});

const FILTER_CONTROLS = [
    { key: 'position_code', label: 'Vị trí', default: false },
    { key: 'status', label: 'Trạng thái', default: false },
];

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
} = useVisibleFilterControls(
    FILTER_CONTROLS,
    'va-workspace.evaluation-templates.visible-filters.v1',
);

const {
    visibleCols,
    showColDd,
    persistVisibleColumns,
    openColPanel,
    TABLE_COLUMNS,
} = useVisibleColumns(
    EVALUATION_TEMPLATE_TABLE_COLUMNS,
    'va-workspace.evaluation-templates.columns.v1',
);

function openFilterPanelSafe() {
    openFilterPanel(() => { showColDd.value = false; });
}

function openColPanelSafe() {
    openColPanel(() => { showFilterPanelDd.value = false; });
}

function closeFilterPanels() {
    showFilterPanelDd.value = false;
    showColDd.value = false;
}

function openDataModal(tabName = 'import') {
    dataModalTab.value = tabName;
    closeFilterPanels();
    showDataModal.value = true;
}

const local = reactive({
    q: props.filters.q || '',
    position_code: props.filters.position_code || '',
    status: props.filters.status || '',
});

watch(() => props.filters, (f) => {
    local.q = f.q || '';
    local.position_code = f.position_code || '';
    local.status = f.status || '';
}, { deep: true });

let searchTimer = null;

function applyFilters(overrides = {}) {
    const params = {
        q: local.q || undefined,
        position_code: local.position_code || undefined,
        status: local.status || undefined,
        ...overrides,
    };
    Object.keys(params).forEach((k) => {
        if (params[k] === '' || params[k] == null) delete params[k];
    });
    router.get(route('workspace.evaluation-templates.index'), params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function onSearchInput(value) {
    local.q = value;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 350);
}

function onQuickFilter(payload) {
    local.status = payload.status ?? '';
    applyFilters({ status: local.status || undefined });
}

function openCreate() {
    router.visit(route('workspace.evaluation-templates.create'));
}

function openEdit(row) {
    editingTemplate.value = row;
    showFormModal.value = true;
}

function onDelete(row) {
    confirmDelete(
        `Xóa mẫu «${row.name}»? Thao tác không thể hoàn tác.`,
        () => {
            router.delete(route('workspace.evaluation-templates.destroy', row.id), {
                preserveScroll: true,
            });
        },
        { title: 'Xóa mẫu đánh giá', confirmText: 'Xóa', tone: 'danger' },
    );
}

const rows = computed(() => props.templates?.data ?? []);

function onDocClick(e) {
    const t = e.target;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(t)
        && !t?.closest?.('[data-filter-visibility-panel]')) {
        showFilterPanelDd.value = false;
    }
    if (colDdRef.value && !colDdRef.value.contains(t)
        && !t?.closest?.('[data-column-visibility-panel]')) {
        showColDd.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', onDocClick));
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocClick);
});

</script>

<template>
  <Head title="Mẫu đánh giá" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Danh sách mẫu đánh giá"
        subtitle="Gói tiêu chí theo vị trí — nhập/xuất Excel, nhân bản mẫu"
        icon="clipboard-list"
        :badge="summary.total ?? null"
      >
        <button
          v-if="can.manage"
          type="button"
          class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-sm"
          @click="openCreate"
        >
          <AppIcon
            name="plus"
            :size="15"
          />
          Thêm mẫu
        </button>
      </PageHeader>
    </template>

    <EvaluationTemplateSummaryBar
      :summary="summary"
      :active-status="filters.status || ''"
      @quick-filter="onQuickFilter"
    />

    <div class="card overflow-hidden">
      <div class="border-b border-slate-100 px-5 py-4">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <div class="min-w-0 w-full basis-full lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              hide-label
              stretch
              inline-actions
              input-height="h-10"
              :model-value="local.q"
              placeholder="Tìm tên mẫu, mã, vị trí…"
              aria-label="Tìm mẫu đánh giá"
              @update:model-value="onSearchInput"
            />
          </div>
          <div class="flex shrink-0 items-center gap-2">
            <div
              ref="filterPanelDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="filter"
                :active="showFilterPanelDd || hasFilterRow"
                :title="enabledFilterControlCount ? `Lọc (${enabledFilterControlCount})` : 'Lọc'"
                @click="openFilterPanelSafe"
              >
                Lọc
              </DatagridToolbarActionButton>
              <FilterVisibilityDropdown
                v-model="visibleFilters"
                :show="showFilterPanelDd"
                :anchor-ref="filterPanelDdRef"
                :controls="FILTER_CONTROLS"
                input-id-prefix="eval-tpl-filter-vis"
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
                :fixed-labels="['Tên mẫu đánh giá']"
                input-id-prefix="eval-tpl-col-vis"
                @persist="persistVisibleColumns"
              />
            </div>
            <DatagridToolbarActionButton
              icon="upload"
              title="Nhập · Xuất · Lịch sử export mẫu đánh giá"
              @click="openDataModal(can.manage ? 'import' : 'export')"
            >
              Dữ liệu
            </DatagridToolbarActionButton>
          </div>
        </div>

        <div
          v-if="hasFilterRow"
          class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
        >
          <DatagridFilterField v-if="visibleFilters.position_code">
            <select
              v-model="local.position_code"
              class="input h-10 w-full text-sm"
              @change="applyFilters()"
            >
              <option value="">
                Vị trí
              </option>
              <option
                v-for="p in positions"
                :key="p.code"
                :value="p.code"
              >
                {{ p.name }}
              </option>
            </select>
          </DatagridFilterField>
          <DatagridFilterField v-if="visibleFilters.status">
            <select
              v-model="local.status"
              class="input h-10 w-full text-sm"
              @change="applyFilters()"
            >
              <option value="">
                Trạng thái
              </option>
              <option value="active">
                Đang hoạt động
              </option>
              <option value="inactive">
                Ngưng hoạt động
              </option>
            </select>
          </DatagridFilterField>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-5 py-3 font-semibold">
                Tên mẫu đánh giá
              </th>
              <th
                v-if="visibleCols.criteria_count"
                class="px-3 py-3 font-semibold"
              >
                Số tiêu chí
              </th>
              <th
                v-if="visibleCols.criteria"
                class="min-w-[14rem] px-3 py-3 font-semibold"
              >
                Tiêu chí đánh giá
              </th>
              <th
                v-if="visibleCols.position"
                class="px-3 py-3 font-semibold"
              >
                Vị trí đánh giá
              </th>
              <th
                v-if="visibleCols.creator"
                class="px-3 py-3 font-semibold"
              >
                Người tạo
              </th>
              <th
                v-if="visibleCols.created_at"
                class="px-3 py-3 font-semibold"
              >
                Ngày tạo
              </th>
              <th
                v-if="visibleCols.updated_at"
                class="px-3 py-3 font-semibold"
              >
                Ngày cập nhật
              </th>
              <th
                v-if="visibleCols.status"
                class="px-3 py-3 font-semibold"
              >
                Trạng thái
              </th>
              <th
                v-if="visibleCols.description"
                class="px-3 py-3 font-semibold"
              >
                Mô tả
              </th>
              <th class="w-12 px-3 py-3" />
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in rows"
              :key="row.id"
              class="border-t border-slate-100 hover:bg-slate-50/60"
            >
              <td class="px-5 py-3">
                <Link
                  :href="route('workspace.evaluation-templates.show', row.id)"
                  class="font-medium text-slate-900 hover:text-brand"
                >
                  {{ row.name }}
                </Link>
                <p class="mt-0.5 font-mono text-[11px] text-slate-400">
                  {{ row.template_code }}
                </p>
              </td>
              <td
                v-if="visibleCols.criteria_count"
                class="px-3 py-3 tabular-nums text-slate-700"
              >
                {{ row.criteria_count ?? 0 }}
              </td>
              <td
                v-if="visibleCols.criteria"
                class="px-3 py-3"
              >
                <div
                  v-if="(row.criteria_labels || []).length"
                  class="flex flex-wrap gap-1"
                >
                  <span
                    v-for="(label, i) in row.criteria_labels.slice(0, 4)"
                    :key="i"
                    class="inline-flex max-w-[10rem] truncate rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-600"
                    :title="label"
                  >
                    {{ label }}
                  </span>
                  <span
                    v-if="row.criteria_labels.length > 4"
                    class="rounded-md bg-slate-50 px-1.5 py-0.5 text-[11px] text-slate-400"
                  >
                    +{{ row.criteria_labels.length - 4 }}
                  </span>
                </div>
                <span
                  v-else
                  class="text-xs text-slate-400"
                >Chưa gắn tiêu chí</span>
              </td>
              <td
                v-if="visibleCols.position"
                class="px-3 py-3 text-slate-700"
              >
                {{ displayOrEmpty(row.position_name, EMPTY_LABELS.notUpdated) }}
              </td>
              <td
                v-if="visibleCols.creator"
                class="px-3 py-3 text-slate-700"
              >
                {{ displayOrEmpty(row.creator?.display_name, EMPTY_LABELS.notUpdated) }}
              </td>
              <td
                v-if="visibleCols.created_at"
                class="px-3 py-3 whitespace-nowrap text-slate-600"
              >
                {{ row.created_at ? datetime(row.created_at) : EMPTY_LABELS.notUpdated }}
              </td>
              <td
                v-if="visibleCols.updated_at"
                class="px-3 py-3 whitespace-nowrap text-slate-600"
              >
                {{ row.updated_at ? datetime(row.updated_at) : EMPTY_LABELS.notUpdated }}
              </td>
              <td
                v-if="visibleCols.status"
                class="px-3 py-3"
              >
                <span
                  class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium"
                  :class="row.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                >
                  {{ row.is_active ? 'Hoạt động' : 'Ngưng' }}
                </span>
              </td>
              <td
                v-if="visibleCols.description"
                class="max-w-[14rem] truncate px-3 py-3 text-slate-500"
              >
                {{ displayOrEmpty(row.description, EMPTY_LABELS.notUpdated) }}
              </td>
              <td class="px-3 py-3 text-right">
                <EvaluationTemplateRowActions
                  :template="row"
                  :can-manage="!!can.manage"
                  @edit="openEdit"
                  @delete="onDelete"
                />
              </td>
            </tr>
            <tr v-if="!rows.length">
              <td
                colspan="10"
                class="px-5 py-16 text-center text-sm text-slate-400"
              >
                Chưa có mẫu đánh giá.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <EvaluationTemplateFormModal
      :show="showFormModal"
      :template="editingTemplate"
      :criteria-options="criteriaOptions"
      :job-titles="jobTitles"
      :job-ranks="jobRanks"
      :field-type-options="fieldTypeOptions"
      @close="showFormModal = false"
    />
    <EvaluationTemplateDataModal
      :show="showDataModal"
      :initial-tab="dataModalTab"
      :can-manage="!!can.manage"
      :positions="positions"
      :criteria-options="criteriaOptions"
      :rows="rows"
      :filters="filters"
      :summary="summary"
      :visible-cols="visibleCols"
      :export-logs="localExportLogs"
      @close="showDataModal = false"
      @logs-updated="(logs) => { localExportLogs = logs }"
    />
  </AppLayout>
</template>
