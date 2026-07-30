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
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import FilterDatePicker from '@/shared/ui/FilterDatePicker.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { EVALUATION_TABLE_COLUMNS } from '@/modules/evaluation/config/columns.js';
import { exportEvaluationWorkbook } from '@/modules/evaluation/composables/useEvaluationExport.js';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { useToast } from '@/shared/composables/useToast';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';
import { date } from '@/composables/useFormat';

const props = defineProps({
    configs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    departments: { type: Array, default: () => [] },
    templateTypeOptions: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const toast = useToast();
const confirmDelete = useConfirmDelete();
const filterPanelDdRef = ref(null);
const colDdRef = ref(null);
const exportDdRef = ref(null);
const showExportDd = ref(false);
const perPage = ref(Number(props.filters.per_page) || 20);

const FILTER_CONTROLS = [
    { key: 'department_code', label: 'Phòng ban', default: false },
    { key: 'template_type', label: 'Loại mẫu', default: false },
    { key: 'status', label: 'Trạng thái', default: false },
    { key: 'date_range', label: 'Hiệu lực', default: false },
];

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const filters = reactive({
    q: props.filters.q || '',
    department_code: props.filters.department_code || '',
    template_type: props.filters.template_type || '',
    status: props.filters.status || '',
    effective_from: props.filters.effective_from || '',
    effective_to: props.filters.effective_to || '',
});

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
} = useVisibleFilterControls(FILTER_CONTROLS, 'va-workspace.evaluation.visible-filters.v3');

const {
    visibleCols,
    showColDd,
    visibleColumnCount,
    persistVisibleColumns,
    openColPanel,
    isColVisible,
    TABLE_COLUMNS,
} = useVisibleColumns(EVALUATION_TABLE_COLUMNS, 'va-workspace.evaluation.columns.v3');

const GROUP_UNKNOWN = '__unknown__';
const COLLAPSE_STORAGE_KEY = 'va-workspace.evaluation.collapsed-dept-groups';

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

const rows = computed(() => props.configs?.data || []);

/** Nhóm theo phòng ban (trang hiện tại) — pattern Blocker. */
const groupedConfigs = computed(() => {
    const map = new Map();
    for (const row of rows.value) {
        const code = (row.department_code || '').trim();
        const key = code || GROUP_UNKNOWN;
        if (!map.has(key)) {
            map.set(key, {
                key,
                code: code || null,
                label: EMPTY_LABELS.team,
                items: [],
            });
        }
        const group = map.get(key);
        const name = (row.department_name || '').trim();
        if (name && group.label === EMPTY_LABELS.team) {
            group.label = name;
        }
        group.items.push(row);
    }
    return [...map.values()].sort((a, b) => {
        if (a.key === GROUP_UNKNOWN) return 1;
        if (b.key === GROUP_UNKNOWN) return -1;
        return a.label.localeCompare(b.label, 'vi');
    });
});

/** Chevron nhóm + «Tên cấu hình» cố định + «Hành động». */
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
    collapsedGroups.value = new Set(groupedConfigs.value.map((g) => g.key));
    persistCollapsedGroups();
}

const allGroupsExpanded = computed(() =>
    groupedConfigs.value.length > 0
    && groupedConfigs.value.every((g) => isGroupExpanded(g.key)),
);

function toggleAllGroups() {
    if (allGroupsExpanded.value) collapseAllGroups();
    else expandAllGroups();
}

let searchTimer = null;
watch(() => filters.q, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters({ page: 1 }), 350);
});

watch(
    () => [filters.effective_from, filters.effective_to],
    () => applyFilters({ page: 1 }),
);

watch(perPage, () => applyFilters({ page: 1 }));

function applyFilters(extra = {}) {
    router.get(route('workspace.evaluation.index'), {
        q: filters.q || undefined,
        department_code: filters.department_code || undefined,
        template_type: filters.template_type || undefined,
        status: filters.status || undefined,
        effective_from: filters.effective_from || undefined,
        effective_to: filters.effective_to || undefined,
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
    filters.template_type = payload.template_type || '';
    applyFilters({ page: 1 });
}

function onDelete(row) {
    confirmDelete(
        `Xoá cấu hình «${row.config_name}»? Thao tác không thể hoàn tác trên giao diện.`,
        () => router.delete(route('workspace.evaluation.destroy', row.id), { preserveScroll: true }),
    );
}

function formatRange(from, to) {
    const a = from ? date(from) : null;
    const b = to ? date(to) : null;
    if (!a && !b) return EMPTY_LABELS.period;
    if (a && !b) return `${a} trở đi`;
    if (!a && b) return `đến ${b}`;
    return `${a} – ${b}`;
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
    if (showExportDd.value) {
        showFilterPanelDd.value = false;
        showColDd.value = false;
    }
}

function runExport(format) {
    showExportDd.value = false;
    if (!rows.value.length) {
        toast.warning('Không có dữ liệu để xuất trên trang này.');
        return;
    }
    try {
        if (format === 'csv') {
            exportCsv(rows.value);
        } else {
            exportEvaluationWorkbook(rows.value, { ...filters }, props.summary);
        }
        toast.success(`Đã xuất ${rows.value.length} cấu hình (trang hiện tại).`);
    } catch {
        toast.error('Xuất file thất bại. Thử lại sau.');
    }
}

function exportCsv(list) {
    const headers = [
        'STT', 'Mã phòng', 'Phòng ban', 'Tên cấu hình', 'Loại',
        'Từ ngày', 'Đến ngày', 'Số tiêu chí', 'Điểm gốc', 'Trạng thái', 'Người tạo',
    ];
    const lines = [headers.join(',')];
    list.forEach((row, i) => {
        const cells = [
            i + 1,
            row.department_code ?? '',
            row.department_name ?? '',
            row.config_name ?? '',
            row.template_type_label ?? '',
            row.effective_from ? date(row.effective_from) : '',
            row.effective_to ? date(row.effective_to) : '',
            row.criteria_count ?? 0,
            row.base_score ?? '',
            row.is_active ? 'Đang bật' : 'Đã tắt',
            row.creator?.display_name ?? '',
        ].map((v) => `"${String(v).replace(/"/g, '""')}"`);
        lines.push(cells.join(','));
    });
    const blob = new Blob([`\uFEFF${lines.join('\n')}`], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `VA_CauHinhDanhGia_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

function onDocClick(e) {
    const t = e.target;
    if (t.closest?.('[data-filter-visibility-panel]')) return;
    if (t.closest?.('[data-column-visibility-panel]')) return;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(t)) {
        showFilterPanelDd.value = false;
    }
    if (colDdRef.value && !colDdRef.value.contains(t)) {
        showColDd.value = false;
    }
    if (exportDdRef.value && !exportDdRef.value.contains(t)) {
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
  <Head title="Cấu hình đánh giá" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Cấu hình đánh giá"
        subtitle="Bộ quy tắc đánh giá theo phòng ban — siêu quản trị"
        icon="award"
        :badge="summary.total ?? null"
      >
        <Link
          v-if="can.manage"
          :href="route('workspace.evaluation.create')"
          class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-sm"
        >
          <AppIcon
            name="add"
            :size="15"
          />
          Thêm mới
        </Link>
      </PageHeader>
    </template>

    <EvaluationSummaryBar
      :summary="summary"
      :active-status="filters.status"
      :active-template-type="filters.template_type"
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
              placeholder="Tìm tên cấu hình, phòng ban, mô tả…"
              aria-label="Tìm cấu hình đánh giá"
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
                :fixed-labels="['Tên cấu hình']"
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
            v-if="groupedConfigs.length"
            class="ml-auto flex shrink-0 items-center gap-2"
          >
            <DatagridToolbarActionButton
              icon="chevron-down"
              :title="allGroupsExpanded ? 'Thu gọn tất cả nhóm phòng ban' : 'Mở tất cả nhóm phòng ban'"
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
          <DatagridFilterField v-if="visibleFilters.template_type">
            <select
              v-model="filters.template_type"
              :class="FILTER_CONTROL_CLASS"
              aria-label="Loại mẫu"
              @change="applyFilters({ page: 1 })"
            >
              <option value="">
                Loại mẫu
              </option>
              <option
                v-for="opt in templateTypeOptions"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
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
                Đang bật
              </option>
              <option value="inactive">
                Đã tắt
              </option>
              <option value="effective">
                Đang hiệu lực
              </option>
            </select>
          </DatagridFilterField>
          <div
            v-if="visibleFilters.date_range"
            class="min-w-0 w-full sm:col-span-2 xl:col-span-2"
          >
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:gap-3">
              <FilterDatePicker
                id="evaluation-filter-effective-from"
                v-model="filters.effective_from"
                name="effective_from"
                placeholder="Hiệu lực từ"
                :max-date="filters.effective_to || null"
              />
              <FilterDatePicker
                id="evaluation-filter-effective-to"
                v-model="filters.effective_to"
                name="effective_to"
                placeholder="Hiệu lực đến"
                :min-date="filters.effective_from || null"
              />
            </div>
          </div>
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
                Tên cấu hình
              </th>
              <th
                v-if="isColVisible('template_type')"
                class="px-5 py-3 font-medium"
              >
                Loại
              </th>
              <th
                v-if="isColVisible('effective')"
                class="px-5 py-3 font-medium"
              >
                Hiệu lực
              </th>
              <th
                v-if="isColVisible('effective_from')"
                class="px-5 py-3 font-medium"
              >
                Từ ngày
              </th>
              <th
                v-if="isColVisible('effective_to')"
                class="px-5 py-3 font-medium"
              >
                Đến ngày
              </th>
              <th
                v-if="isColVisible('criteria_count')"
                class="px-5 py-3 font-medium"
              >
                Tiêu chí
              </th>
              <th
                v-if="isColVisible('base_score')"
                class="px-5 py-3 font-medium"
              >
                Điểm gốc
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
              <th class="px-5 py-3 font-medium text-right">
                Hành động
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="rows.length === 0">
              <td
                :colspan="tableColspan"
                class="px-5 py-10 text-center text-slate-500"
              >
                Chưa có cấu hình đánh giá.
              </td>
            </tr>
            <template
              v-for="group in groupedConfigs"
              :key="group.key"
            >
              <tr
                class="cursor-pointer border-y border-slate-200 bg-slate-100/70 transition hover:bg-slate-100"
                @click="toggleGroup(group.key)"
              >
                <td class="px-1 py-2.5 text-center align-middle">
                  <AppIcon
                    name="chevron-down"
                    :size="15"
                    class="inline-block text-slate-500 transition-transform"
                    :class="isGroupExpanded(group.key) ? '' : '-rotate-90'"
                  />
                </td>
                <td
                  :colspan="tableColspan - 1"
                  class="px-5 py-2.5 align-middle"
                >
                  <div class="flex items-center gap-2">
                    <span class="min-w-0 flex-1 break-words text-sm font-semibold text-slate-800">
                      {{ group.label }}
                    </span>
                    <span
                      v-if="group.code"
                      class="shrink-0 font-mono text-[11px] text-slate-500"
                    >{{ group.code }}</span>
                    <span class="shrink-0 rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold tabular-nums text-slate-600 ring-1 ring-slate-200/90">
                      {{ group.items.length }}
                    </span>
                  </div>
                </td>
              </tr>
              <template v-if="isGroupExpanded(group.key)">
                <tr
                  v-for="row in group.items"
                  :key="row.id"
                  class="border-b border-slate-100 hover:bg-slate-50/80"
                >
                  <td class="px-1 py-3 align-middle">
                    <span
                      class="mx-auto block h-6 w-1 rounded-full bg-slate-200/80"
                      aria-hidden="true"
                    />
                  </td>
                  <td class="px-5 py-3">
                    <Link
                      :href="route('workspace.evaluation.show', row.id)"
                      class="font-medium text-brand hover:underline"
                    >
                      {{ row.config_name }}
                    </Link>
                  </td>
                  <td
                    v-if="isColVisible('template_type')"
                    class="px-5 py-3"
                  >
                    <Badge
                      :color="row.template_type === 'point_system' ? 'violet' : 'amber'"
                      :label="row.template_type_label"
                    />
                  </td>
                  <td
                    v-if="isColVisible('effective')"
                    class="px-5 py-3 tabular-nums text-slate-700"
                  >
                    {{ formatRange(row.effective_from, row.effective_to) }}
                  </td>
                  <td
                    v-if="isColVisible('effective_from')"
                    class="px-5 py-3 tabular-nums text-slate-700"
                  >
                    {{ row.effective_from ? date(row.effective_from) : EMPTY_LABELS.period }}
                  </td>
                  <td
                    v-if="isColVisible('effective_to')"
                    class="px-5 py-3 tabular-nums text-slate-700"
                  >
                    {{ row.effective_to ? date(row.effective_to) : 'Không giới hạn' }}
                  </td>
                  <td
                    v-if="isColVisible('criteria_count')"
                    class="px-5 py-3 tabular-nums"
                  >
                    {{ row.criteria_count ?? 0 }}
                  </td>
                  <td
                    v-if="isColVisible('base_score')"
                    class="px-5 py-3 tabular-nums text-slate-700"
                  >
                    {{ row.base_score != null ? row.base_score : EMPTY_LABELS.notUpdated }}
                  </td>
                  <td
                    v-if="isColVisible('description')"
                    class="max-w-[14rem] truncate px-5 py-3 text-slate-600"
                    :title="row.description || undefined"
                  >
                    {{ displayOrEmpty(row.description, EMPTY_LABELS.notUpdated) }}
                  </td>
                  <td
                    v-if="isColVisible('creator')"
                    class="px-5 py-3 text-slate-700"
                  >
                    {{ displayOrEmpty(row.creator?.display_name, EMPTY_LABELS.notUpdated) }}
                  </td>
                  <td
                    v-if="isColVisible('created_at')"
                    class="whitespace-nowrap px-5 py-3 text-xs text-slate-600"
                  >
                    {{ row.created_at ? date(row.created_at) : EMPTY_LABELS.notUpdated }}
                  </td>
                  <td
                    v-if="isColVisible('updated_at')"
                    class="whitespace-nowrap px-5 py-3 text-xs text-slate-600"
                  >
                    {{ row.updated_at ? date(row.updated_at) : EMPTY_LABELS.notUpdated }}
                  </td>
                  <td
                    v-if="isColVisible('status')"
                    class="px-5 py-3"
                  >
                    <Badge
                      :color="row.is_active ? 'emerald' : 'slate'"
                      :label="row.is_active ? 'Đang bật' : 'Đã tắt'"
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
                      <Link
                        v-if="can.manage"
                        :href="route('workspace.evaluation.edit', row.id)"
                        class="btn-ghost h-8 w-8 p-0"
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
          </tbody>
          <tfoot>
            <DatagridPaginationFooter
              v-model:per-page="perPage"
              :meta="configs.meta"
              :colspan="tableColspan"
              :per-page-options="[10, 20, 25, 50]"
            />
          </tfoot>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
