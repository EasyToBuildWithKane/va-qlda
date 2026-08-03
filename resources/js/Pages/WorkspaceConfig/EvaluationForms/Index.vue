<script setup>
import {
    computed, reactive, ref, watch, onMounted, onBeforeUnmount,
} from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import EvaluationFormSummaryBar from '@/modules/evaluation-form/components/EvaluationFormSummaryBar.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import { EVALUATION_FORM_TABLE_COLUMNS } from '@/modules/evaluation-form/config/columns.js';
import { exportEvaluationFormsWorkbook } from '@/modules/evaluation-form/composables/useEvaluationFormExport.js';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';
import { date as formatDate, datetime } from '@/composables/useFormat';

const props = defineProps({
    forms: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    statusOptions: { type: Array, default: () => [] },
    typeOptions: { type: Array, default: () => [] },
    templateOptions: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const confirmDelete = useConfirmDelete();
const filterPanelDdRef = ref(null);
const colDdRef = ref(null);
const exportDdRef = ref(null);
const showExportDd = ref(false);

const FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái', default: false },
    { key: 'type_id', label: 'Loại đánh giá', default: false },
    { key: 'template_id', label: 'Mẫu đánh giá', default: false },
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
    'va-workspace.evaluation-forms.visible-filters.v1',
);

const {
    visibleCols,
    showColDd,
    persistVisibleColumns,
    openColPanel,
    TABLE_COLUMNS,
} = useVisibleColumns(
    EVALUATION_FORM_TABLE_COLUMNS,
    'va-workspace.evaluation-forms.columns.v1',
);

function openFilterPanelSafe() {
    showExportDd.value = false;
    openFilterPanel(() => { showColDd.value = false; });
}

function openColPanelSafe() {
    showExportDd.value = false;
    openColPanel(() => { showFilterPanelDd.value = false; });
}

const local = reactive({
    q: props.filters.q || '',
    status: props.filters.status || '',
    type_id: props.filters.type_id || '',
    template_id: props.filters.template_id || '',
});

watch(() => props.filters, (f) => {
    local.q = f.q || '';
    local.status = f.status || '';
    local.type_id = f.type_id || '';
    local.template_id = f.template_id || '';
}, { deep: true });

let searchTimer = null;

function applyFilters(overrides = {}) {
    const params = {
        q: local.q || undefined,
        status: local.status || undefined,
        type_id: local.type_id || undefined,
        template_id: local.template_id || undefined,
        ...overrides,
    };
    Object.keys(params).forEach((k) => {
        if (params[k] === '' || params[k] == null) delete params[k];
    });
    router.get(route('workspace.evaluation-forms.index'), params, {
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
    router.visit(route('workspace.evaluation-forms.create'));
}

function onDelete(row) {
    confirmDelete(
        `Xóa phiếu «${row.name}»? Thao tác không thể hoàn tác.`,
        () => {
            router.delete(route('workspace.evaluation-forms.destroy', row.id), {
                preserveScroll: true,
            });
        },
        { title: 'Xóa phiếu đánh giá', confirmText: 'Xóa', tone: 'danger' },
    );
}

function openScoringPeriod(row) {
    router.post(route('workspace.evaluation-forms.open', row.id), {}, {
        preserveScroll: true,
    });
}

function closeScoringPeriod(row) {
    router.post(route('workspace.evaluation-forms.close', row.id), {}, {
        preserveScroll: true,
    });
}

function reopenScoringPeriod(row) {
    router.post(route('workspace.evaluation-forms.reopen', row.id), {}, {
        preserveScroll: true,
    });
}

function exportExcel() {
    showExportDd.value = false;
    exportEvaluationFormsWorkbook(rows.value);
}

const rows = computed(() => props.forms?.data ?? []);

const statusTone = {
    draft: 'bg-slate-100 text-slate-600',
    active: 'bg-emerald-50 text-emerald-700',
    closed: 'bg-amber-50 text-amber-700',
};

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
    if (exportDdRef.value && !exportDdRef.value.contains(t)) {
        showExportDd.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', onDocClick));
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocClick);
});
</script>

<template>
  <Head title="Phiếu đánh giá" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Danh sách phiếu đánh giá"
        subtitle="Cấu hình phiếu theo mẫu, kỳ và danh sách nhân sự"
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
          Tạo phiếu
        </button>
      </PageHeader>
    </template>

    <EvaluationFormSummaryBar
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
              placeholder="Tìm tên phiếu, mã…"
              aria-label="Tìm phiếu đánh giá"
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
                input-id-prefix="eval-form-filter-vis"
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
                title="Cột"
                @click="openColPanelSafe"
              >
                Cột
              </DatagridToolbarActionButton>
              <ColumnVisibilityDropdown
                v-model="visibleCols"
                :show="showColDd"
                :anchor-ref="colDdRef"
                :columns="TABLE_COLUMNS"
                input-id-prefix="eval-form-col-vis"
                @persist="persistVisibleColumns"
              />
            </div>
            <div
              ref="exportDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="download"
                :active="showExportDd"
                title="Xuất"
                @click="showExportDd = !showExportDd; showFilterPanelDd = false; showColDd = false"
              >
                Xuất
              </DatagridToolbarActionButton>
              <div
                v-if="showExportDd"
                class="absolute right-0 z-20 mt-1 w-48 rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
              >
                <button
                  type="button"
                  class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
                  @click="exportExcel"
                >
                  Xuất Excel (.xlsx)
                </button>
              </div>
            </div>
          </div>
        </div>

        <div
          v-if="hasFilterRow"
          class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
        >
          <DatagridFilterField
            v-if="visibleFilters.status"
          >
            <select
              v-model="local.status"
              class="input h-10 w-full text-sm"
              @change="applyFilters()"
            >
              <option value="">
                Trạng thái
              </option>
              <option
                v-for="opt in statusOptions"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
          </DatagridFilterField>
          <DatagridFilterField
            v-if="visibleFilters.type_id"
          >
            <select
              v-model="local.type_id"
              class="input h-10 w-full text-sm"
              @change="applyFilters()"
            >
              <option value="">
                Loại đánh giá
              </option>
              <option
                v-for="opt in typeOptions"
                :key="opt.id"
                :value="String(opt.id)"
              >
                {{ opt.name }}
              </option>
            </select>
          </DatagridFilterField>
          <DatagridFilterField
            v-if="visibleFilters.template_id"
          >
            <select
              v-model="local.template_id"
              class="input h-10 w-full text-sm"
              @change="applyFilters()"
            >
              <option value="">
                Mẫu đánh giá
              </option>
              <option
                v-for="opt in templateOptions"
                :key="opt.id"
                :value="String(opt.id)"
              >
                {{ opt.name }}
              </option>
            </select>
          </DatagridFilterField>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50/80 text-left text-xs uppercase tracking-wide text-slate-500">
              <th
                v-if="visibleCols.name"
                class="px-4 py-3"
              >
                Tên phiếu đánh giá
              </th>
              <th
                v-if="visibleCols.form_code"
                class="px-4 py-3"
              >
                Mã phiếu
              </th>
              <th
                v-if="visibleCols.status"
                class="px-4 py-3"
              >
                Trạng thái
              </th>
              <th
                v-if="visibleCols.criteria_count"
                class="px-4 py-3"
              >
                Số tiêu chí
              </th>
              <th
                v-if="visibleCols.assignees_count"
                class="px-4 py-3"
              >
                Số nhân sự
              </th>
              <th
                v-if="visibleCols.period"
                class="px-4 py-3"
              >
                Kỳ đánh giá
              </th>
              <th
                v-if="visibleCols.deadline"
                class="px-4 py-3"
              >
                Hạn đánh giá
              </th>
              <th
                v-if="visibleCols.type"
                class="px-4 py-3"
              >
                Loại
              </th>
              <th
                v-if="visibleCols.template"
                class="px-4 py-3"
              >
                Mẫu
              </th>
              <th
                v-if="visibleCols.created_at"
                class="px-4 py-3"
              >
                Ngày tạo
              </th>
              <th
                v-if="visibleCols.creator"
                class="px-4 py-3"
              >
                Người tạo
              </th>
              <th class="px-4 py-3 w-24" />
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in rows"
              :key="row.id"
              class="border-b border-slate-50 hover:bg-slate-50/60"
            >
              <td
                v-if="visibleCols.name"
                class="px-4 py-3"
              >
                <Link
                  :href="route('workspace.evaluation-forms.edit', row.id)"
                  class="font-medium text-slate-800 hover:text-brand"
                >
                  {{ row.name }}
                </Link>
              </td>
              <td
                v-if="visibleCols.form_code"
                class="px-4 py-3 tabular-nums text-slate-600"
              >
                {{ row.form_code }}
              </td>
              <td
                v-if="visibleCols.status"
                class="px-4 py-3"
              >
                <span
                  class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium"
                  :class="statusTone[row.status] || 'bg-slate-100 text-slate-600'"
                >
                  {{ row.status_label || row.status }}
                </span>
              </td>
              <td
                v-if="visibleCols.criteria_count"
                class="px-4 py-3 tabular-nums text-slate-600"
              >
                {{ row.criteria_count ?? 0 }}
              </td>
              <td
                v-if="visibleCols.assignees_count"
                class="px-4 py-3 tabular-nums text-slate-600"
              >
                {{ row.assignees_count ?? 0 }}
              </td>
              <td
                v-if="visibleCols.period"
                class="px-4 py-3 text-slate-600"
              >
                {{ displayOrEmpty(row.period_label, EMPTY_LABELS.notUpdated) }}
              </td>
              <td
                v-if="visibleCols.deadline"
                class="px-4 py-3 text-slate-600"
              >
                {{ row.deadline ? formatDate(row.deadline) : displayOrEmpty(null, EMPTY_LABELS.notUpdated) }}
              </td>
              <td
                v-if="visibleCols.type"
                class="px-4 py-3 text-slate-600"
              >
                {{ displayOrEmpty(row.type_name, EMPTY_LABELS.notUpdated) }}
              </td>
              <td
                v-if="visibleCols.template"
                class="px-4 py-3 text-slate-600"
              >
                {{ displayOrEmpty(row.template_name, EMPTY_LABELS.notUpdated) }}
              </td>
              <td
                v-if="visibleCols.created_at"
                class="px-4 py-3 text-slate-600"
              >
                {{ row.created_at ? formatDate(row.created_at) : displayOrEmpty(null, EMPTY_LABELS.notUpdated) }}
              </td>
              <td
                v-if="visibleCols.creator"
                class="px-4 py-3 text-slate-600"
              >
                <div
                  v-if="row.creator_name"
                  class="flex flex-col"
                >
                  <span>{{ row.creator_name }}</span>
                  <span
                    v-if="row.created_at"
                    class="text-[11px] text-slate-400"
                  >
                    {{ datetime(row.created_at) }}
                  </span>
                </div>
                <span v-else>{{ displayOrEmpty(null, EMPTY_LABELS.notUpdated) }}</span>
              </td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap items-center gap-1">
                  <Link
                    v-if="row.status === 'active' || row.status === 'closed'"
                    :href="route('workspace.evaluation-forms.scoring.index', row.id)"
                    class="btn-ghost h-8 px-2 text-xs text-brand"
                    title="Chấm điểm"
                  >
                    Chấm
                  </Link>
                  <button
                    v-if="can.manage && row.status === 'draft'"
                    type="button"
                    class="btn-ghost h-8 px-2 text-xs text-emerald-600"
                    title="Mở chấm"
                    @click="openScoringPeriod(row)"
                  >
                    Mở chấm
                  </button>
                  <button
                    v-if="can.manage && row.status === 'active'"
                    type="button"
                    class="btn-ghost h-8 px-2 text-xs text-amber-600"
                    title="Khóa kỳ"
                    @click="closeScoringPeriod(row)"
                  >
                    Khóa
                  </button>
                  <button
                    v-if="can.manage && row.status === 'closed'"
                    type="button"
                    class="btn-ghost h-8 px-2 text-xs"
                    title="Mở lại"
                    @click="reopenScoringPeriod(row)"
                  >
                    Mở lại
                  </button>
                  <Link
                    v-if="row.status === 'draft' || can.manage"
                    :href="route('workspace.evaluation-forms.edit', row.id)"
                    class="btn-ghost h-8 px-2 text-xs"
                    title="Sửa"
                  >
                    <AppIcon
                      name="edit"
                      :size="14"
                    />
                  </Link>
                  <button
                    v-if="can.manage"
                    type="button"
                    class="btn-ghost h-8 px-2 text-xs text-rose-500"
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
            <tr v-if="rows.length === 0">
              <td
                colspan="12"
                class="px-4 py-16 text-center text-sm text-slate-400"
              >
                Chưa có phiếu đánh giá.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
