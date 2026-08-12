<script setup>
import { reactive, ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { useFixedDropdownAnchor } from '@/shared/composables/useFixedDropdownAnchor';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';
import { date } from '@/composables/useFormat';
import { displayOrEmpty } from '@/shared/utils/emptyDisplay';
import TestCaseSummaryBar from '@/modules/testcase/components/TestCaseSummaryBar.vue';
import TestCaseFormModal from '@/modules/testcase/components/TestCaseFormModal.vue';
import TestCaseExecuteModal from '@/modules/testcase/components/TestCaseExecuteModal.vue';
import TestCaseDataModal from '@/modules/testcase/components/TestCaseDataModal.vue';

const FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái', default: false },
    { key: 'priority', label: 'Mức độ ưu tiên', default: false },
    { key: 'suite', label: 'Bộ test', default: false },
    { key: 'last_result', label: 'Kết quả cuối', default: false },
    { key: 'owner', label: 'Người phụ trách', default: false },
];
const VISIBLE_FILTERS_KEY = 'va-workspace.project-testcase.visible-filters.v1';
const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const TABLE_COLUMNS_DEF = [
    { key: 'code', label: 'Mã' },
    { key: 'title', label: 'Tiêu đề' },
    { key: 'suite', label: 'Bộ test', default: false },
    { key: 'priority', label: 'Ưu tiên' },
    { key: 'status', label: 'Trạng thái' },
    { key: 'last_result', label: 'Kết quả' },
    { key: 'owner', label: 'Phụ trách', default: false },
    { key: 'updated_at', label: 'Cập nhật', default: false },
];

const props = defineProps({
    projectId: { type: Number, required: true },
    projectCode: { type: String, default: 'DA' },
    projectName: { type: String, default: '' },
    testCases: { type: Array, default: () => [] },
    testSuites: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    employees: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    canContribute: { type: Boolean, default: false },
    statusOptions: { type: Array, default: () => [] },
    priorityOptions: { type: Array, default: () => [] },
    runResultOptions: { type: Array, default: () => [] },
    layout: { type: String, default: 'panel' },
});

const emit = defineEmits(['saved']);

const dialog = useDialog();
const toast = useToast();

// ── Toolbar state ──────────────────────────────────────────────────────────
const filterPanelDdRef = ref(null);
const colDdRef = ref(null);
const dataMenuRef = ref(null);
const dataMenu = ref(false);

const { panelStyle: dataMenuStyle } = useFixedDropdownAnchor(
    () => dataMenuRef.value,
    dataMenu,
    { width: 240, zIndex: 200 },
);

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS: filterControls,
} = useVisibleFilterControls(FILTER_CONTROLS, VISIBLE_FILTERS_KEY);

const {
    visibleCols,
    showColDd,
    persistVisibleColumns,
    openColPanel,
    isColVisible,
    TABLE_COLUMNS,
} = useVisibleColumns(TABLE_COLUMNS_DEF, 'va-workspace.project-testcase.columns');

// ── Filters ────────────────────────────────────────────────────────────────
const filterForm = reactive({
    q: '',
    status: '',
    priority: '',
    suite_id: '',
    last_result: '',
    owner_id: '',
});

const activeQuickFilter = ref('');

function applyQuickFilter(payload) {
    filterForm.status = payload.status ?? '';
    filterForm.last_result = payload.last_result ?? '';
    activeQuickFilter.value = payload.status || payload.last_result || '';
}

function clearFilters() {
    filterForm.q = '';
    filterForm.status = '';
    filterForm.priority = '';
    filterForm.suite_id = '';
    filterForm.last_result = '';
    filterForm.owner_id = '';
    activeQuickFilter.value = '';
}

const filteredTestCases = computed(() => {
    let rows = props.testCases ?? [];
    const q = filterForm.q.trim().toLowerCase();
    if (q) {
        rows = rows.filter((tc) =>
            (tc.title ?? '').toLowerCase().includes(q)
            || (tc.code ?? '').toLowerCase().includes(q),
        );
    }
    if (filterForm.status) {
        rows = rows.filter((tc) => tc.status?.value === filterForm.status);
    }
    if (filterForm.priority) {
        rows = rows.filter((tc) => tc.priority?.value === filterForm.priority);
    }
    if (filterForm.suite_id) {
        const sid = Number(filterForm.suite_id);
        rows = rows.filter((tc) => tc.suite_id === sid || tc.suite?.id === sid);
    }
    if (filterForm.last_result) {
        if (filterForm.last_result === 'not_run') {
            rows = rows.filter((tc) => !tc.last_result?.value);
        } else {
            rows = rows.filter((tc) => tc.last_result?.value === filterForm.last_result);
        }
    }
    if (filterForm.owner_id) {
        const oid = Number(filterForm.owner_id);
        rows = rows.filter((tc) => tc.owner_id === oid || tc.owner?.id === oid);
    }
    return rows;
});

const appliedFilterCount = computed(() =>
    [filterForm.status, filterForm.priority, filterForm.suite_id, filterForm.last_result, filterForm.owner_id]
        .filter((v) => v !== '' && v != null).length,
);

// ── Modals ─────────────────────────────────────────────────────────────────
const formModal = ref(false);
const editing = ref(null);
const executeModal = ref(false);
const executingTestCase = ref(null);
const dataModal = ref(false);
const expandedRow = ref(null);

function openCreate() {
    editing.value = null;
    formModal.value = true;
}

function openEdit(tc) {
    editing.value = tc;
    formModal.value = true;
}

function openExecute(tc) {
    executingTestCase.value = tc;
    executeModal.value = true;
}

function toggleExpand(tc) {
    expandedRow.value = expandedRow.value?.id === tc.id ? null : tc;
}

function onSaved() {
    formModal.value = false;
    executeModal.value = false;
    emit('saved');
    router.reload({ only: ['testCases', 'testCaseSummary'], preserveScroll: true });
}

function openDataModal() {
    dataMenu.value = false;
    dataModal.value = true;
}

function onDelete(tc) {
    dialog.confirm({
        title: 'Xóa test case',
        message: `Bạn có chắc muốn xóa "${tc.title}"? Hành động này không thể hoàn tác.`,
        confirmLabel: 'Xóa',
        confirmVariant: 'danger',
        onConfirm: () => {
            router.delete(route('test-cases.destroy', { testCase: tc.id }), {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Đã xóa test case.');
                    emit('saved');
                },
                onError: () => toast.error('Không thể xóa test case này.'),
            });
        },
    });
}

// ── Toolbar click outside ──────────────────────────────────────────────────
function onClickOutside(e) {
    if (e.target.closest?.('[data-filter-visibility-panel]')) return;
    if (e.target.closest?.('[data-column-visibility-panel]')) return;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
    if (colDdRef.value && !colDdRef.value.contains(e.target)) {
        showColDd.value = false;
    }
    if (dataMenuRef.value && !dataMenuRef.value.contains(e.target)) {
        dataMenu.value = false;
    }
}

function openFilterPanelSafe() {
    openFilterPanel(() => { showColDd.value = false; dataMenu.value = false; });
}
function openColPanelSafe() {
    openColPanel(() => { showFilterPanelDd.value = false; dataMenu.value = false; });
}
function toggleDataMenu() {
    dataMenu.value = !dataMenu.value;
    if (dataMenu.value) { showFilterPanelDd.value = false; showColDd.value = false; }
}

onMounted(() => document.addEventListener('mousedown', onClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside));

// ── Styling helpers ────────────────────────────────────────────────────────
const STATUS_COLOR = { draft: 'slate', ready: 'emerald', deprecated: 'amber' };
const PRIORITY_COLOR = { low: 'slate', medium: 'sky', high: 'amber', critical: 'rose' };
const RESULT_COLOR = { pass: 'emerald', fail: 'rose', blocked: 'violet', skipped: 'slate' };

function statusBadge(tc) {
    return STATUS_COLOR[tc.status?.value] ?? 'slate';
}
function priorityBadge(tc) {
    return PRIORITY_COLOR[tc.priority?.value] ?? 'slate';
}
function resultBadge(tc) {
    return RESULT_COLOR[tc.last_result?.value] ?? 'slate';
}

const tableColspan = computed(() =>
    TABLE_COLUMNS.filter((c) => isColVisible(c.key)).length + 1,
);

const suiteOptions = computed(() => props.testSuites.map((s) => ({ value: s.id, label: s.name })));
</script>

<template>
  <div class="flex h-full min-h-0 w-full min-w-0 flex-col">
    <!-- KPI Strip -->
    <TestCaseSummaryBar
      :summary="summary"
      :filters="{ status: filterForm.status, last_result: filterForm.last_result }"
      class="mb-5"
      @quick-filter="applyQuickFilter"
    />

    <!-- Main card -->
    <div class="card flex min-h-0 flex-1 flex-col overflow-hidden">
      <!-- Toolbar -->
      <div class="shrink-0 overflow-visible border-b border-slate-100 px-5 py-4">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <!-- Search -->
          <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              v-model="filterForm.q"
              input-id="testcase-search"
              placeholder="Mã, tiêu đề…"
              stretch
              inline-actions
              hide-label
              input-height="h-10"
            />
          </div>

          <div class="flex shrink-0 items-center gap-2">
            <!-- Lọc -->
            <div
              ref="filterPanelDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="filter"
                :active="showFilterPanelDd"
                :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${filterControls.length})`"
                @click="openFilterPanelSafe"
              >
                Lọc
              </DatagridToolbarActionButton>
              <FilterVisibilityDropdown
                v-model="visibleFilters"
                :show="showFilterPanelDd"
                :anchor-ref="filterPanelDdRef"
                :controls="filterControls"
                @persist="persistVisibleFilters"
              />
            </div>

            <!-- Cột -->
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
                @persist="persistVisibleColumns"
              />
            </div>

            <!-- Dữ liệu (Nhập / Xuất) -->
            <div
              ref="dataMenuRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="upload"
                :active="dataMenu"
                title="Nhập / Xuất dữ liệu"
                @click.stop="toggleDataMenu"
              >
                Dữ liệu
              </DatagridToolbarActionButton>

              <div
                v-if="dataMenu"
                :style="dataMenuStyle"
                class="fixed z-[200] w-60 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
                data-testcase-data-panel
              >
                <button
                  type="button"
                  class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-slate-700 hover:bg-slate-50"
                  @click="openDataModal('import')"
                >
                  <AppIcon
                    name="upload"
                    :size="14"
                    class="text-slate-400"
                  />
                  Nhập / Xuất / Đối soát…
                </button>
              </div>
            </div>

            <!-- Thêm -->
            <button
              v-if="canManage || canContribute"
              type="button"
              class="btn-primary h-10 gap-1.5 px-3 text-sm"
              @click="openCreate"
            >
              <AppIcon
                name="plus"
                :size="14"
              />
              Thêm test case
            </button>
          </div>
        </div>

        <!-- Filter row -->
        <div
          v-if="hasFilterRow"
          class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5"
        >
          <DatagridFilterField
            v-if="visibleFilters.status"
            key="status"
          >
            <select
              v-model="filterForm.status"
              :class="FILTER_CONTROL_CLASS"
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
            v-if="visibleFilters.priority"
            key="priority"
          >
            <select
              v-model="filterForm.priority"
              :class="FILTER_CONTROL_CLASS"
            >
              <option value="">
                Mức độ ưu tiên
              </option>
              <option
                v-for="opt in priorityOptions"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
          </DatagridFilterField>

          <DatagridFilterField
            v-if="visibleFilters.suite"
            key="suite"
          >
            <select
              v-model="filterForm.suite_id"
              :class="FILTER_CONTROL_CLASS"
            >
              <option value="">
                Bộ test
              </option>
              <option
                v-for="s in suiteOptions"
                :key="s.value"
                :value="s.value"
              >
                {{ s.label }}
              </option>
            </select>
          </DatagridFilterField>

          <DatagridFilterField
            v-if="visibleFilters.last_result"
            key="last_result"
          >
            <select
              v-model="filterForm.last_result"
              :class="FILTER_CONTROL_CLASS"
            >
              <option value="">
                Kết quả cuối
              </option>
              <option value="not_run">
                Chưa chạy
              </option>
              <option
                v-for="opt in runResultOptions"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
          </DatagridFilterField>

          <DatagridFilterField
            v-if="visibleFilters.owner"
            key="owner"
          >
            <select
              v-model="filterForm.owner_id"
              :class="FILTER_CONTROL_CLASS"
            >
              <option value="">
                Người phụ trách
              </option>
              <option
                v-for="emp in employees"
                :key="emp.id ?? emp.value"
                :value="emp.id ?? emp.value"
              >
                {{ emp.name ?? emp.label }}
              </option>
            </select>
          </DatagridFilterField>
        </div>

        <!-- Applied filters badge -->
        <div
          v-if="appliedFilterCount > 0"
          class="mt-2 flex items-center gap-2"
        >
          <span class="text-xs text-slate-500">{{ appliedFilterCount }} bộ lọc đang áp dụng</span>
          <button
            type="button"
            class="text-xs text-brand underline"
            @click="clearFilters"
          >
            Xóa bộ lọc
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="min-h-0 flex-1 overflow-auto">
        <table class="w-full min-w-[640px] text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50/80 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
              <th
                v-if="isColVisible('code')"
                class="px-4 py-3"
              >
                Mã
              </th>
              <th
                v-if="isColVisible('title')"
                class="px-4 py-3"
              >
                Tiêu đề
              </th>
              <th
                v-if="isColVisible('suite')"
                class="px-4 py-3"
              >
                Bộ test
              </th>
              <th
                v-if="isColVisible('priority')"
                class="px-4 py-3"
              >
                Ưu tiên
              </th>
              <th
                v-if="isColVisible('status')"
                class="px-4 py-3"
              >
                Trạng thái
              </th>
              <th
                v-if="isColVisible('last_result')"
                class="px-4 py-3"
              >
                Kết quả
              </th>
              <th
                v-if="isColVisible('owner')"
                class="px-4 py-3"
              >
                Phụ trách
              </th>
              <th
                v-if="isColVisible('updated_at')"
                class="px-4 py-3"
              >
                Cập nhật
              </th>
              <th class="px-4 py-3 text-right">
                Thao tác
              </th>
            </tr>
          </thead>

          <tbody>
            <template v-if="filteredTestCases.length">
              <template
                v-for="tc in filteredTestCases"
                :key="tc.id"
              >
                <tr
                  class="group cursor-pointer border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50/60"
                  @click="toggleExpand(tc)"
                >
                  <td
                    v-if="isColVisible('code')"
                    class="px-4 py-3"
                  >
                    <span class="font-mono text-[11px] text-slate-500">{{ tc.code ?? '—' }}</span>
                  </td>
                  <td
                    v-if="isColVisible('title')"
                    class="max-w-[280px] px-4 py-3"
                  >
                    <p class="truncate font-medium text-slate-800">
                      {{ tc.title }}
                    </p>
                    <p
                      v-if="tc.suite?.name"
                      class="truncate text-[11px] text-slate-400"
                    >
                      {{ tc.suite.name }}
                    </p>
                  </td>
                  <td
                    v-if="isColVisible('suite')"
                    class="px-4 py-3 text-xs text-slate-500"
                  >
                    {{ displayOrEmpty(tc.suite?.name) }}
                  </td>
                  <td
                    v-if="isColVisible('priority')"
                    class="px-4 py-3"
                  >
                    <Badge
                      v-if="tc.priority"
                      :tone="priorityBadge(tc)"
                      size="sm"
                    >
                      {{ tc.priority.label }}
                    </Badge>
                    <span
                      v-else
                      class="text-xs text-slate-300"
                    >—</span>
                  </td>
                  <td
                    v-if="isColVisible('status')"
                    class="px-4 py-3"
                  >
                    <Badge
                      v-if="tc.status"
                      :tone="statusBadge(tc)"
                      size="sm"
                    >
                      {{ tc.status.label }}
                    </Badge>
                    <span
                      v-else
                      class="text-xs text-slate-300"
                    >—</span>
                  </td>
                  <td
                    v-if="isColVisible('last_result')"
                    class="px-4 py-3"
                  >
                    <Badge
                      v-if="tc.last_result"
                      :tone="resultBadge(tc)"
                      size="sm"
                    >
                      {{ tc.last_result.label }}
                    </Badge>
                    <span
                      v-else
                      class="text-xs italic text-slate-400"
                    >Chưa chạy</span>
                  </td>
                  <td
                    v-if="isColVisible('owner')"
                    class="px-4 py-3"
                  >
                    <div
                      v-if="tc.owner"
                      class="flex items-center gap-1.5"
                    >
                      <Avatar
                        :name="tc.owner.name"
                        :size="20"
                      />
                      <span class="truncate text-xs text-slate-600">{{ tc.owner.name }}</span>
                    </div>
                    <span
                      v-else
                      class="text-xs italic text-slate-400"
                    >Chưa gán</span>
                  </td>
                  <td
                    v-if="isColVisible('updated_at')"
                    class="px-4 py-3 text-xs text-slate-400"
                  >
                    {{ date(tc.updated_at) }}
                  </td>
                  <td class="px-4 py-3">
                    <div
                      class="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100"
                      @click.stop
                    >
                      <button
                        type="button"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-violet-50 hover:text-violet-600"
                        title="Thực thi"
                        @click="openExecute(tc)"
                      >
                        <AppIcon
                          name="play"
                          :size="13"
                        />
                      </button>
                      <button
                        v-if="canManage || canContribute"
                        type="button"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-sky-50 hover:text-sky-600"
                        title="Sửa"
                        @click="openEdit(tc)"
                      >
                        <AppIcon
                          name="edit"
                          :size="13"
                        />
                      </button>
                      <button
                        v-if="canManage"
                        type="button"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                        title="Xóa"
                        @click="onDelete(tc)"
                      >
                        <AppIcon
                          name="trash"
                          :size="13"
                        />
                      </button>
                    </div>
                  </td>
                </tr>

                <!-- Expand row detail -->
                <tr
                  v-if="expandedRow?.id === tc.id"
                  :key="`${tc.id}-expand`"
                  class="border-b border-slate-100 bg-slate-50/40"
                >
                  <td
                    :colspan="tableColspan"
                    class="px-6 py-4"
                  >
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                      <div v-if="tc.preconditions">
                        <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                          Điều kiện tiên quyết
                        </p>
                        <p class="text-xs text-slate-700">
                          {{ tc.preconditions }}
                        </p>
                      </div>
                      <div v-if="tc.steps?.length">
                        <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                          Bước kiểm thử ({{ tc.steps.length }})
                        </p>
                        <ol class="space-y-0.5 pl-4">
                          <li
                            v-for="(step, si) in tc.steps"
                            :key="si"
                            class="list-decimal text-xs text-slate-700"
                          >
                            {{ step.step }}
                          </li>
                        </ol>
                      </div>
                      <div v-if="tc.expected_result">
                        <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                          Kết quả mong đợi
                        </p>
                        <p class="text-xs text-slate-700">
                          {{ tc.expected_result }}
                        </p>
                      </div>
                    </div>

                    <div class="mt-3 flex gap-2">
                      <button
                        type="button"
                        class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-violet-50 px-3 text-xs font-medium text-violet-700 hover:bg-violet-100"
                        @click="openExecute(tc)"
                      >
                        <AppIcon
                          name="play"
                          :size="13"
                        />
                        Thực thi
                      </button>
                      <button
                        v-if="canManage || canContribute"
                        type="button"
                        class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-sky-50 px-3 text-xs font-medium text-sky-700 hover:bg-sky-100"
                        @click="openEdit(tc)"
                      >
                        <AppIcon
                          name="edit"
                          :size="13"
                        />
                        Sửa
                      </button>
                    </div>
                  </td>
                </tr>
              </template>
            </template>

            <!-- Empty state -->
            <tr v-else>
              <td
                :colspan="tableColspan"
                class="px-4 py-16"
              >
                <div class="flex flex-col items-center gap-3 text-center">
                  <span class="grid h-12 w-12 place-items-center rounded-2xl bg-violet-50 text-violet-400">
                    <AppIcon
                      name="check-circle"
                      :size="24"
                    />
                  </span>
                  <div>
                    <p class="font-display text-sm font-semibold text-slate-700">
                      {{ appliedFilterCount > 0 ? 'Không tìm thấy kết quả phù hợp' : 'Chưa có test case nào' }}
                    </p>
                    <p class="mt-1 text-xs text-slate-400">
                      {{ appliedFilterCount > 0 ? 'Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm.' : 'Bấm «Thêm test case» để bắt đầu xây dựng bộ kiểm thử.' }}
                    </p>
                  </div>
                  <button
                    v-if="!appliedFilterCount && (canManage || canContribute)"
                    type="button"
                    class="btn-primary mt-1 h-9 gap-1.5 px-3 text-sm"
                    @click="openCreate"
                  >
                    <AppIcon
                      name="plus"
                      :size="14"
                    />
                    Thêm test case
                  </button>
                  <button
                    v-if="appliedFilterCount > 0"
                    type="button"
                    class="text-xs text-brand underline"
                    @click="clearFilters"
                  >
                    Xóa bộ lọc
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Footer count -->
      <div
        v-if="filteredTestCases.length"
        class="shrink-0 border-t border-slate-100 px-5 py-2.5 text-xs text-slate-400"
      >
        Hiển thị {{ filteredTestCases.length }} / {{ testCases.length }} test case
      </div>
    </div>

    <!-- Modals -->
    <TestCaseFormModal
      :show="formModal"
      :test-case="editing"
      :project-id="projectId"
      :project-code="projectCode"
      :project-name="projectName"
      :test-suites="testSuites"
      :employees="employees"
      :priority-options="priorityOptions"
      :status-options="statusOptions"
      @close="formModal = false"
      @saved="onSaved"
    />

    <TestCaseExecuteModal
      :show="executeModal"
      :test-case="executingTestCase"
      :run-result-options="runResultOptions"
      @close="executeModal = false"
      @saved="onSaved"
    />

    <TestCaseDataModal
      :show="dataModal"
      :project-id="projectId"
      :project-code="projectCode"
      :project-name="projectName"
      :test-cases="testCases"
      :test-suites="testSuites"
      :employees="employees"
      :can-manage="canManage"
      @close="dataModal = false"
      @imported="onSaved"
    />
  </div>
</template>
