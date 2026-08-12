<script setup>
import { reactive, ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
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

const PER_PAGE_OPTIONS = [10, 15, 20, 50];

const TC_FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái', default: false },
    { key: 'priority', label: 'Mức độ ưu tiên', default: false },
    { key: 'last_result', label: 'Kết quả cuối', default: false },
    { key: 'project', label: 'Dự án', default: false },
    { key: 'owner', label: 'Người phụ trách', default: false },
];

const TC_TABLE_COLUMNS = [
    { key: 'code', label: 'Mã' },
    { key: 'title', label: 'Tiêu đề' },
    { key: 'project', label: 'Dự án', default: false },
    { key: 'suite', label: 'Bộ test', default: false },
    { key: 'priority', label: 'Ưu tiên' },
    { key: 'status', label: 'Trạng thái' },
    { key: 'last_result', label: 'Kết quả' },
    { key: 'owner', label: 'Phụ trách' },
    { key: 'updated_at', label: 'Cập nhật', default: false },
];

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const props = defineProps({
    testCases: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const dialog = useDialog();
const toast = useToast();

const modal = ref(false);
const editing = ref(null);
const executeModal = ref(false);
const executingTestCase = ref(null);
const dataModal = ref(false);
const filterPanelDdRef = ref(null);
const colDdRef = ref(null);
const dataMenuRef = ref(null);
const dataMenu = ref(false);
const perPage = ref(Number(props.filters.per_page) || props.testCases.meta?.per_page || 15);
const activeQuickFilter = ref('');

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
    FILTER_CONTROLS,
} = useVisibleFilterControls(TC_FILTER_CONTROLS, 'va-workspace.testcases.visible-filters.v1');

const {
    visibleCols,
    showColDd,
    persistVisibleColumns,
    openColPanel,
    isColVisible,
    TABLE_COLUMNS,
} = useVisibleColumns(TC_TABLE_COLUMNS, 'va-workspace.testcases.columns');

const filterForm = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    priority: props.filters.priority ?? '',
    last_result: props.filters.last_result ?? '',
    project_id: props.filters.project_id ?? '',
    owner_id: props.filters.owner_id ?? '',
});

const appliedFilterCount = computed(() =>
    [filterForm.status, filterForm.priority, filterForm.last_result, filterForm.project_id, filterForm.owner_id]
        .filter((v) => v !== '' && v != null).length,
);

const tableColspan = computed(() => TABLE_COLUMNS.filter((c) => isColVisible(c.key)).length + 1);

let searchDebounce = null;
watch(() => filterForm.q, () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => applyFilters({ page: 1 }), 350);
});

function applyFilters(extra = {}) {
    router.get(
        route('test-cases.index'),
        { ...filterForm, per_page: perPage.value, ...extra },
        { preserveScroll: true, preserveState: true },
    );
}

function clearFilters() {
    filterForm.q = '';
    filterForm.status = '';
    filterForm.priority = '';
    filterForm.last_result = '';
    filterForm.project_id = '';
    filterForm.owner_id = '';
    activeQuickFilter.value = '';
    applyFilters({ page: 1 });
}

function applyQuickFilter(payload) {
    filterForm.status = payload.status ?? '';
    filterForm.last_result = payload.last_result ?? '';
    activeQuickFilter.value = payload.status || payload.last_result || '';
    applyFilters({ page: 1 });
}

function onPerPageChange() {
    applyFilters({ page: 1 });
}

// Modals
function openCreate() {
    editing.value = null;
    modal.value = true;
}

function openEdit(tc) {
    editing.value = tc;
    modal.value = true;
}

function openExecuteModal(tc) {
    executingTestCase.value = tc;
    executeModal.value = true;
}

function onSaved() {
    modal.value = false;
    executeModal.value = false;
    router.reload({ only: ['testCases', 'summary'], preserveScroll: true });
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
                    router.reload({ only: ['testCases', 'summary'] });
                },
                onError: () => toast.error('Không thể xóa test case này.'),
            });
        },
    });
}

function toggleDataMenu() {
    dataMenu.value = !dataMenu.value;
    if (dataMenu.value) { showFilterPanelDd.value = false; showColDd.value = false; }
}

function openDataModal() {
    dataMenu.value = false;
    dataModal.value = true;
}

function openFilterPanelSafe() {
    openFilterPanel(() => { showColDd.value = false; dataMenu.value = false; });
}
function openColPanelSafe() {
    openColPanel(() => { showFilterPanelDd.value = false; dataMenu.value = false; });
}

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

onMounted(() => document.addEventListener('mousedown', onClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside));

// Styling helpers
const STATUS_COLOR = { draft: 'slate', ready: 'emerald', deprecated: 'amber' };
const PRIORITY_COLOR = { low: 'slate', medium: 'sky', high: 'amber', critical: 'rose' };
const RESULT_COLOR = { pass: 'emerald', fail: 'rose', blocked: 'violet', skipped: 'slate' };

const statusOptions = computed(() => props.options.status ?? []);
const priorityOptions = computed(() => props.options.priority ?? []);
const runResultOptions = computed(() => props.options.runResult ?? []);
const projectOptions = computed(() => props.options.projects ?? []);
const employeeOptions = computed(() => props.options.employees ?? []);

const testCaseList = computed(() => props.testCases?.data ?? []);
const paginationMeta = computed(() => props.testCases?.meta ?? {});
</script>

<template>
  <Head title="QA / Test case" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="QA / Test case"
        subtitle="Quản lý bộ kiểm thử toàn hệ thống"
        icon="check-circle"
        icon-color="brand"
        :badge="summary.not_run > 0 ? summary.not_run : null"
      >
        <button
          v-if="can.create"
          type="button"
          class="btn-primary h-9 gap-1.5 px-3 text-sm"
          @click="openCreate"
        >
          <AppIcon
            name="plus"
            :size="15"
          />
          Thêm test case
        </button>
      </PageHeader>
    </template>

    <div class="px-4 py-5 sm:px-5 lg:px-6">
      <!-- KPI strip -->
      <TestCaseSummaryBar
        :summary="summary"
        :filters="{ status: filterForm.status, last_result: filterForm.last_result }"
        @quick-filter="applyQuickFilter"
      />

      <!-- Card -->
      <div class="card overflow-hidden">
        <!-- Toolbar -->
        <div class="border-b border-slate-100 px-5 py-4">
          <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
            <!-- Search -->
            <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
              <DatagridToolbarSearch
                v-model="filterForm.q"
                input-id="testcase-global-search"
                placeholder="Mã, tiêu đề, điều kiện…"
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
                  :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
                  @click="openFilterPanelSafe"
                >
                  Lọc
                </DatagridToolbarActionButton>
                <FilterVisibilityDropdown
                  v-model="visibleFilters"
                  :show="showFilterPanelDd"
                  :anchor-ref="filterPanelDdRef"
                  :controls="FILTER_CONTROLS"
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

              <!-- Dữ liệu -->
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
                >
                  <button
                    type="button"
                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-slate-700 hover:bg-slate-50"
                    @click="openDataModal"
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

              <button
                v-if="can.create"
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
                @change="applyFilters({ page: 1 })"
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
                @change="applyFilters({ page: 1 })"
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
              v-if="visibleFilters.last_result"
              key="last_result"
            >
              <select
                v-model="filterForm.last_result"
                :class="FILTER_CONTROL_CLASS"
                @change="applyFilters({ page: 1 })"
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
              v-if="visibleFilters.project"
              key="project"
            >
              <select
                v-model="filterForm.project_id"
                :class="FILTER_CONTROL_CLASS"
                @change="applyFilters({ page: 1 })"
              >
                <option value="">
                  Dự án
                </option>
                <option
                  v-for="p in projectOptions"
                  :key="p.value ?? p.id"
                  :value="p.value ?? p.id"
                >
                  {{ p.label ?? p.name }}
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
                @change="applyFilters({ page: 1 })"
              >
                <option value="">
                  Người phụ trách
                </option>
                <option
                  v-for="emp in employeeOptions"
                  :key="emp.id ?? emp.value"
                  :value="emp.id ?? emp.value"
                >
                  {{ emp.name ?? emp.label }}
                </option>
              </select>
            </DatagridFilterField>
          </div>

          <!-- Applied filter badge -->
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
        <div class="overflow-x-auto">
          <table class="w-full min-w-[680px] text-sm">
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
                  v-if="isColVisible('project')"
                  class="px-4 py-3"
                >
                  Dự án
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
              <template v-if="testCaseList.length">
                <tr
                  v-for="tc in testCaseList"
                  :key="tc.id"
                  class="group border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50/60"
                >
                  <td
                    v-if="isColVisible('code')"
                    class="px-4 py-3"
                  >
                    <span class="font-mono text-[11px] text-slate-500">{{ tc.code ?? '—' }}</span>
                  </td>
                  <td
                    v-if="isColVisible('title')"
                    class="max-w-[260px] px-4 py-3"
                  >
                    <p class="truncate font-medium text-slate-800">
                      {{ tc.title }}
                    </p>
                  </td>
                  <td
                    v-if="isColVisible('project')"
                    class="px-4 py-3 text-xs text-slate-500"
                  >
                    {{ displayOrEmpty(tc.project?.code) }}
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
                      :tone="PRIORITY_COLOR[tc.priority.value] ?? 'slate'"
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
                      :tone="STATUS_COLOR[tc.status.value] ?? 'slate'"
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
                      :tone="RESULT_COLOR[tc.last_result.value] ?? 'slate'"
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
                    <div class="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                      <button
                        v-if="tc.can?.execute"
                        type="button"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-violet-50 hover:text-violet-600"
                        title="Thực thi"
                        @click="openExecuteModal(tc)"
                      >
                        <AppIcon
                          name="play"
                          :size="13"
                        />
                      </button>
                      <button
                        v-if="tc.can?.update"
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
                        v-if="tc.can?.delete"
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
              </template>

              <!-- Empty state -->
              <tr v-else>
                <td
                  :colspan="tableColspan"
                  class="px-4 py-20"
                >
                  <div class="flex flex-col items-center gap-3 text-center">
                    <span class="grid h-14 w-14 place-items-center rounded-2xl bg-violet-50 text-violet-400">
                      <AppIcon
                        name="check-circle"
                        :size="28"
                      />
                    </span>
                    <div>
                      <p class="font-display text-sm font-semibold text-slate-700">
                        {{ appliedFilterCount > 0 ? 'Không tìm thấy kết quả phù hợp' : 'Chưa có test case nào' }}
                      </p>
                      <p class="mt-1 text-xs text-slate-400">
                        {{ appliedFilterCount > 0 ? 'Thử thay đổi bộ lọc.' : 'Bấm «Thêm test case» để bắt đầu xây dựng bộ kiểm thử.' }}
                      </p>
                    </div>
                    <button
                      v-if="!appliedFilterCount && can.create"
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

        <!-- Pagination -->
        <DatagridPaginationFooter
          v-if="paginationMeta.total > 0"
          :meta="paginationMeta"
          :per-page="perPage"
          :per-page-options="PER_PAGE_OPTIONS"
          @update:per-page="(v) => { perPage = v; onPerPageChange(); }"
          @page="(p) => applyFilters({ page: p })"
        />
      </div>
    </div>

    <!-- Modals -->
    <TestCaseFormModal
      :show="modal"
      :test-case="editing"
      :test-suites="[]"
      :employees="employeeOptions"
      :priority-options="priorityOptions"
      :status-options="statusOptions"
      @close="modal = false"
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
      :project-id="0"
      project-code="ALL"
      :test-cases="testCaseList"
      :employees="employeeOptions"
      :can-manage="can.create"
      @close="dataModal = false"
      @imported="onSaved"
    />
  </AppLayout>
</template>
