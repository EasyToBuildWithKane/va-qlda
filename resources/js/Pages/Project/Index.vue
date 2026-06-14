<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import ProjectCard from '@/modules/project/components/ProjectCard.vue';
import ProjectDataGrid from '@/modules/project/components/ProjectDataGrid.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridSegmentedControl from '@/shared/ui/DatagridSegmentedControl.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { COLUMNS, DEFAULT_VISIBLE } from '@/modules/project/config/columns';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';
import { exportProjectList } from '@/composables/useProjectListExport';
import ProjectPortfolioSummaryBar from '@/modules/project/components/ProjectPortfolioSummaryBar.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';

const PER_PAGE_OPTIONS = [5, 10, 15, 20, 50, 100];
const DEFAULT_PER_PAGE = 100;

const FILTER_CONTROLS_DEF = [
    { key: 'type', label: 'Loại dự án', default: false },
    { key: 'status', label: 'Trạng thái', default: false },
    { key: 'scope', label: 'Phạm vi', default: false },
    { key: 'department_id', label: 'Phòng ban', default: false },
    { key: 'region', label: 'Khu vực', default: false },
    { key: 'manager_id', label: 'Chủ dự án', default: false },
    { key: 'mine', label: 'Dự án của tôi', default: false },
];

const VIEW_TABS = [
    { key: 'list', label: 'Bảng', icon: 'report-history', title: 'Bảng danh sách' },
    { key: 'kanban', label: 'Kanban', icon: 'board', title: 'Kanban' },
];

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(FILTER_CONTROLS_DEF, 'va-qlda.projects.visible-filters.v1');

const props = defineProps({
    projects: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statusOptions: { type: Array, default: () => [] },
    typeOptions: { type: Array, default: () => [] },
    scopeOptions: { type: Array, default: () => [] },
    regionOptions: { type: Array, default: () => [] },
    departmentOptions: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const dialog = useDialog();
const toast = useToast();
const perPage = ref(Number(props.filters.per_page) || props.projects.meta?.per_page || DEFAULT_PER_PAGE);

// ---- Persisted UI state ---------------------------------------------------
const VIEW_KEY = 'va-qlda.projects.view';
const GROUP_KEY = 'va-qlda.projects.groupby';
const GRIDGROUP_KEY = 'va-qlda.projects.gridgroup';
const KANBAN_COLLAPSE_KEY = 'va-qlda.projects.kanban.collapsed';
const COLS_KEY = 'va-qlda.projects.columns';
const SAVED_KEY = 'va-qlda.projects.savedfilters';

const view = ref(localStorage.getItem(VIEW_KEY) || 'list');
const groupBy = ref(localStorage.getItem(GROUP_KEY) || 'type');
const gridGroupByDept = ref(localStorage.getItem(GRIDGROUP_KEY) === '1');
watch(view, (v) => localStorage.setItem(VIEW_KEY, v));
watch(groupBy, (v) => localStorage.setItem(GROUP_KEY, v));
watch(gridGroupByDept, (v) => localStorage.setItem(GRIDGROUP_KEY, v ? '1' : '0'));

// Visible columns
const loadCols = () => {
    try {
        const saved = JSON.parse(localStorage.getItem(COLS_KEY));
        if (Array.isArray(saved) && saved.length) return saved.filter((k) => COLUMNS.some((c) => c.key === k));
    } catch { /* ignore */ }
    return [...DEFAULT_VISIBLE];
};
const visibleColumns = ref(loadCols());
watch(visibleColumns, (v) => localStorage.setItem(COLS_KEY, JSON.stringify(v)), { deep: true });
const toggleColumn = (key) => {
    const s = new Set(visibleColumns.value);
    s.has(key) ? s.delete(key) : s.add(key);
    visibleColumns.value = COLUMNS.filter((c) => s.has(c.key)).map((c) => c.key);
};

// ---- Server-side filters --------------------------------------------------
const serverFilters = reactive({
    status: props.filters.status ?? '',
    type: props.filters.type ?? '',
    scope: props.filters.scope ?? '',
    department_id: props.filters.department_id ?? '',
    mine: props.filters.mine ? '1' : '',
    q: props.filters.q ?? '',
});

function projectRouteParams(resetPage = false) {
    return {
        status: serverFilters.status || undefined,
        type: serverFilters.type || undefined,
        scope: serverFilters.scope || undefined,
        department_id: serverFilters.department_id || undefined,
        mine: serverFilters.mine || undefined,
        q: serverFilters.q || undefined,
        per_page: perPage.value,
        page: resetPage ? 1 : undefined,
    };
}

function reloadProjects(resetPage = false) {
    router.get('/projects', projectRouteParams(resetPage), {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
}

function onPerPageChange(n) {
    perPage.value = n;
    reloadProjects(true);
}

let timer = null;
watch(serverFilters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => reloadProjects(true), 350);
});

const pageRangeLabel = computed(() => {
    const m = props.projects.meta;
    if (!m?.total) return '0 dự án';
    const from = m.from ?? 0;
    const to = m.to ?? 0;
    return `${from}–${to} / ${m.total}`;
});

// ---- Client-side post-filters -------------------------------------------
const clientFilters = reactive({ manager_id: '', region: '' });

const displayedProjects = computed(() => {
    let list = props.projects.data;
    if (clientFilters.manager_id) list = list.filter((p) => p.manager?.id === Number(clientFilters.manager_id));
    if (clientFilters.region) list = list.filter((p) => (p.scope_regions || []).includes(clientFilters.region));
    return list;
});

const activeFilterCount = computed(() => {
    let n = 0;
    ['status', 'type', 'scope', 'department_id', 'mine'].forEach((k) => { if (serverFilters[k]) n++; });
    if (clientFilters.manager_id) n++;
    if (clientFilters.region) n++;
    return n;
});

const clearAllFilters = () => {
    Object.assign(serverFilters, { status: '', type: '', scope: '', department_id: '', mine: '', q: serverFilters.q });
    Object.assign(clientFilters, { manager_id: '', region: '' });
};

// ---- Saved filters --------------------------------------------------------
const savedFilters = ref(JSON.parse(localStorage.getItem(SAVED_KEY) || '[]'));
const newFilterName = ref('');
const persistSaved = () => localStorage.setItem(SAVED_KEY, JSON.stringify(savedFilters.value));
const saveCurrentFilter = () => {
    const name = newFilterName.value.trim();
    if (!name) return;
    savedFilters.value = [...savedFilters.value.filter((f) => f.name !== name), {
        name, server: { ...serverFilters, q: '' }, client: { ...clientFilters },
    }];
    persistSaved();
    newFilterName.value = '';
};
const applySaved = (f) => { Object.assign(serverFilters, f.server); Object.assign(clientFilters, f.client); };
const deleteSaved = async (name) => {
    if (!await dialog.confirm({
        title: 'Xoá bộ lọc đã lưu',
        message: `Xoá bộ lọc "${name}"?`,
        tone: 'danger',
        confirmText: 'Xoá',
    })) return;
    savedFilters.value = savedFilters.value.filter((f) => f.name !== name);
    persistSaved();
};

// ---- UI popovers -----------------------------------------------------------
const colsMenu = ref(false);
const exportMenu = ref(false);
const filterPanelDdRef = ref(null);
const colsRef = ref(null);
const exportBtnRef = ref(null);
const exportMenuRef = ref(null);
const exportScope = ref('filtered');

const closeToolbarPanels = () => {
    showFilterPanelDd.value = false;
    colsMenu.value = false;
    exportMenu.value = false;
};

const runExport = (format) => {
    exportMenu.value = false;
    const list = exportScope.value === 'all' ? props.projects.data : displayedProjects.value;
    if (!list.length) {
        toast.warning('Không có dự án để xuất');
        return;
    }
    exportProjectList({
        list,
        visibleKeys: visibleColumns.value,
        summary: props.summary,
        format,
    });
    toast.success(format === 'csv' ? 'Đã xuất file CSV' : 'Đã xuất file Excel');
};

const onDocClick = (e) => {
    const t = e.target;
    if (filterPanelDdRef.value?.contains(t)) return;
    if (t.closest?.('[data-filter-visibility-panel]')) return;
    if (colsRef.value?.contains(t)) return;
    if (exportBtnRef.value?.contains(t) || exportMenuRef.value?.contains(t)) return;
    closeToolbarPanels();
};

onMounted(() => document.addEventListener('mousedown', onDocClick));
onUnmounted(() => document.removeEventListener('mousedown', onDocClick));

// ---- Row actions ----------------------------------------------------------
const remove = async (project) => {
    if (await dialog.confirm({ title: 'Xoá dự án', message: `Xoá "${project.name}"? Hành động này không thể hoàn tác.`, tone: 'danger', confirmText: 'Xoá' })) {
        router.delete(`/projects/${project.id}`, { preserveScroll: true });
    }
};
const duplicate = (project) => router.post(`/projects/${project.id}/duplicate`, {}, { preserveScroll: true });

// ---- Kanban (horizontal swimlanes) -----------------------------------------
const dot = {
    slate: 'bg-slate-400', sky: 'bg-sky-500', amber: 'bg-amber-500',
    emerald: 'bg-emerald-500', rose: 'bg-rose-500', violet: 'bg-violet-500', cyan: 'bg-cyan-500', brand: 'bg-brand',
};

const columns = computed(() => {
    const data = props.projects.data;
    if (groupBy.value === 'type') {
        return props.typeOptions.map((t) => ({
            key: 't' + t.value, label: t.label, color: t.color, value: t.value,
            projects: data.filter((p) => p.type?.value === t.value),
        }));
    }
    const cols = props.departmentOptions.map((d) => ({
        key: 'd' + d.id, label: d.name, color: d.color, value: d.id,
        projects: data.filter((p) => p.department_id === d.id),
    }));
    cols.push({ key: 'none', label: 'Chưa phân phòng', color: 'slate', value: null, projects: data.filter((p) => !p.department_id) });
    return cols;
});

const dragId = ref(null);
const onDrop = (col) => {
    const p = props.projects.data.find((x) => x.id === dragId.value);
    dragId.value = null;
    if (!p || !p.can?.update) return;
    if (groupBy.value === 'type') {
        if (p.type?.value !== col.value) router.patch(`/projects/${p.id}/type`, { type: col.value }, { preserveScroll: true, preserveState: true });
    } else if ((p.department_id ?? null) !== col.value) {
        router.patch(`/projects/${p.id}/department`, { department_id: col.value }, { preserveScroll: true, preserveState: true });
    }
};

const loadCollapsedLanes = () => {
    try {
        const saved = JSON.parse(localStorage.getItem(KANBAN_COLLAPSE_KEY));
        if (Array.isArray(saved)) return new Set(saved);
    } catch { /* ignore */ }
    return new Set();
};
const collapsedLanes = ref(loadCollapsedLanes());
watch(collapsedLanes, (v) => localStorage.setItem(KANBAN_COLLAPSE_KEY, JSON.stringify([...v])), { deep: true });

const isLaneOpen = (key) => !collapsedLanes.value.has(key);
const toggleLane = (key) => {
    const s = new Set(collapsedLanes.value);
    if (s.has(key)) s.delete(key);
    else s.add(key);
    collapsedLanes.value = s;
};
const expandAllLanes = () => { collapsedLanes.value = new Set(); };
const collapseAllLanes = () => { collapsedLanes.value = new Set(columns.value.map((c) => c.key)); };

function onPortfolioQuickFilter({ status }) {
    serverFilters.status = status ?? '';
}

</script>

<template>
  <Head title="Dự án" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Danh mục dự án"
        subtitle="Tất cả dự án đang hoạt động trong hệ thống"
        icon="all-projects"
        icon-color="brand"
        :badge="projects.meta?.total ?? projects.data?.length"
      >
        <Link
          v-if="can.create"
          href="/projects/create"
          class="btn-primary inline-flex h-9 shrink-0 items-center gap-1.5 px-3 text-xs font-semibold"
        >
          <AppIcon
            name="add"
            :size="15"
          />
          Tạo dự án
        </Link>
      </PageHeader>
    </template>

    <ProjectPortfolioSummaryBar
      :summary="summary"
      :active-status="serverFilters.status"
      @quick-filter="onPortfolioQuickFilter"
    />

    <!-- ===== TOOLBAR ===== -->
    <div class="card mb-3 overflow-visible">
      <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-700">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              v-model="serverFilters.q"
              input-id="projects-search"
              placeholder="Tìm theo tên hoặc mã dự án…"
              stretch
              inline-actions
              hide-label
              input-height="h-10"
            />
          </div>

          <div class="flex shrink-0 items-center gap-2">
            <div
              ref="filterPanelDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="filter"
                :active="showFilterPanelDd"
                :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
                @click="
                  openFilterPanel(() => {
                    colsMenu = false;
                    exportMenu = false;
                  })
                "
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

            <div
              v-if="view === 'list'"
              ref="colsRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="columns"
                :active="colsMenu"
                title="Cột hiển thị"
                @click="
                  colsMenu = !colsMenu;
                  exportMenu = false;
                  showFilterPanelDd = false;
                "
              >
                Cột
              </DatagridToolbarActionButton>
              <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 scale-95 -translate-y-1"
                leave-active-class="transition duration-100 ease-in"
                leave-to-class="opacity-0 scale-95 -translate-y-1"
              >
                <div
                  v-if="colsMenu"
                  class="absolute right-0 z-30 mt-1.5 w-60 origin-top-right rounded-xl border border-slate-200 bg-white p-2 shadow-elevation-2 dark:border-slate-700 dark:bg-slate-900"
                >
                  <p class="px-2 py-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
                    Cột hiển thị
                  </p>
                  <label
                    v-for="c in COLUMNS"
                    :key="c.key"
                    class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"
                  >
                    <input
                      type="checkbox"
                      class="rounded border-slate-300 text-brand focus:ring-brand/30"
                      :checked="visibleColumns.includes(c.key)"
                      @change="toggleColumn(c.key)"
                    >
                    {{ c.label }}
                  </label>
                </div>
              </Transition>
            </div>

            <div
              v-if="view === 'list'"
              ref="exportBtnRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="export"
                :active="exportMenu"
                title="Xuất danh sách dự án"
                @click="
                  exportMenu = !exportMenu;
                  colsMenu = false;
                  showFilterPanelDd = false;
                "
              >
                Xuất
              </DatagridToolbarActionButton>
              <div
                v-if="exportMenu"
                ref="exportMenuRef"
                class="absolute right-0 z-30 mt-1.5 w-56 rounded-xl border border-slate-200 bg-white py-1 shadow-elevation-2 dark:border-slate-700 dark:bg-slate-900"
              >
                <p class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-400">
                  Phạm vi
                </p>
                <label class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                  <input
                    v-model="exportScope"
                    type="radio"
                    value="filtered"
                    class="text-brand"
                  >
                  <span>Đang hiển thị ({{ displayedProjects.length }})</span>
                </label>
                <label class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                  <input
                    v-model="exportScope"
                    type="radio"
                    value="all"
                    class="text-brand"
                  >
                  <span>Trang hiện tại ({{ projects.data.length }})</span>
                </label>
                <div class="my-1 border-t border-slate-100 dark:border-slate-700" />
                <button
                  type="button"
                  class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800"
                  @click="runExport('xlsx')"
                >
                  <AppIcon
                    name="download"
                    :size="15"
                    class="text-brand"
                  />
                  <span>
                    Excel (.xlsx)
                    <span class="block text-[10px] text-slate-400">Có KPI & định dạng VA</span>
                  </span>
                </button>
                <button
                  type="button"
                  class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800"
                  @click="runExport('csv')"
                >
                  <AppIcon
                    name="download"
                    :size="15"
                    class="text-slate-500"
                  />
                  <span>
                    CSV (.csv)
                    <span class="block text-[10px] text-slate-400">Mở bằng Excel</span>
                  </span>
                </button>
              </div>
            </div>
          </div>

          <div class="ml-auto flex shrink-0 items-center gap-2">
            <DatagridSegmentedControl
              v-model="view"
              :items="VIEW_TABS"
              aria-label="Chế độ hiển thị"
              icon-only-below-sm
            />
          </div>
        </div>

        <Transition name="fade-slide">
          <div
            v-if="hasFilterRow"
            class="grid grid-cols-1 gap-3 border-t border-slate-100 px-5 py-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6 dark:border-slate-700"
          >
            <DatagridFilterField v-if="visibleFilters.type">
              <select
                v-model="serverFilters.type"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Loại dự án"
              >
                <option value="">
                  Loại dự án
                </option>
                <option
                  v-for="o in typeOptions"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.status">
              <select
                v-model="serverFilters.status"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Trạng thái"
              >
                <option value="">
                  Trạng thái
                </option>
                <option
                  v-for="o in statusOptions"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.scope">
              <select
                v-model="serverFilters.scope"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Phạm vi"
              >
                <option value="">
                  Phạm vi
                </option>
                <option
                  v-for="o in scopeOptions"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.department_id">
              <select
                v-model="serverFilters.department_id"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Phòng ban"
              >
                <option value="">
                  Phòng ban
                </option>
                <option
                  v-for="d in departmentOptions"
                  :key="d.id"
                  :value="d.id"
                >
                  {{ d.name }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.region">
              <select
                v-model="clientFilters.region"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Khu vực"
              >
                <option value="">
                  Khu vực
                </option>
                <option
                  v-for="r in regionOptions"
                  :key="r.value"
                  :value="r.value"
                >
                  {{ r.label }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.manager_id">
              <select
                v-model="clientFilters.manager_id"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Chủ dự án"
              >
                <option value="">
                  Chủ dự án
                </option>
                <option
                  v-for="e in employees"
                  :key="e.id"
                  :value="e.id"
                >
                  {{ e.name }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField
              v-if="visibleFilters.mine"
              class="flex items-center"
            >
              <label class="flex h-10 w-full cursor-pointer items-center gap-2 rounded-btn border border-slate-200 px-3 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300">
                <input
                  v-model="serverFilters.mine"
                  true-value="1"
                  false-value=""
                  type="checkbox"
                  class="rounded border-slate-300 text-brand focus:ring-brand/30"
                >
                Dự án của tôi
              </label>
            </DatagridFilterField>

            <div
              v-if="activeFilterCount"
              class="col-span-full flex flex-wrap items-center justify-between gap-2"
            >
              <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                <input
                  v-model="newFilterName"
                  type="text"
                  placeholder="Tên bộ lọc để lưu…"
                  class="input h-10 max-w-xs flex-1 text-sm"
                  @keyup.enter="saveCurrentFilter"
                >
                <button
                  type="button"
                  class="btn-ghost inline-flex h-10 shrink-0 items-center gap-1.5 border border-slate-200 px-3 text-xs"
                  @click="saveCurrentFilter"
                >
                  <AppIcon
                    name="save"
                    :size="15"
                  />
                  Lưu bộ lọc
                </button>
              </div>
              <button
                type="button"
                class="text-xs font-medium text-brand"
                @click="clearAllFilters"
              >
                Đặt lại bộ lọc
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </div>

    <!-- Secondary toolbar (list) -->
    <div
      v-if="view === 'list'"
      class="mb-3 flex flex-wrap items-center gap-3 text-sm"
    >
      <label class="flex cursor-pointer items-center gap-2 text-slate-600">
        <input
          v-model="gridGroupByDept"
          type="checkbox"
          class="rounded"
        > Nhóm theo phòng ban
      </label>
      <span class="text-slate-300">|</span>
      <span class="text-slate-500">
        <template v-if="projects.meta?.total">
          {{ pageRangeLabel }} dự án
          <span
            v-if="displayedProjects.length !== projects.data.length"
            class="text-slate-400"
          > · hiển thị {{ displayedProjects.length }} sau lọc client</span>
        </template>
        <template v-else>{{ displayedProjects.length }} dự án</template>
      </span>
      <div
        v-if="savedFilters.length"
        class="ml-auto flex flex-wrap items-center gap-1.5"
      >
        <span class="text-xs text-slate-400">Đã lưu:</span>
        <span
          v-for="f in savedFilters"
          :key="f.name"
          class="inline-flex items-center gap-1 rounded-full bg-slate-100 py-0.5 pl-2.5 pr-1 text-xs text-slate-600"
        >
          <button
            class="hover:text-brand"
            @click="applySaved(f)"
          >{{ f.name }}</button>
          <button
            class="grid h-4 w-4 place-items-center rounded-full text-slate-400 hover:bg-slate-200 hover:text-rose-500"
            @click="deleteSaved(f.name)"
          ><AppIcon
            name="close"
            :size="11"
          /></button>
        </span>
      </div>
    </div>

    <!-- ===== LIST VIEW ===== -->
    <ProjectDataGrid
      v-if="view === 'list'"
      :projects="displayedProjects"
      :visible="visibleColumns"
      :group-by-department="gridGroupByDept"
      :department-options="departmentOptions"
      @remove="remove"
      @duplicate="duplicate"
    />

    <!-- ===== KANBAN VIEW (horizontal rows / swimlanes) ===== -->
    <div
      v-else
      class="space-y-4"
    >
      <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
        <div class="flex items-center gap-2">
          <span class="text-slate-500">Nhóm theo:</span>
          <div class="inline-flex rounded-btn border border-slate-200 bg-white p-0.5">
            <button
              type="button"
              class="rounded-[4px] px-3 py-1 text-sm font-medium transition"
              :class="groupBy === 'type' ? 'bg-brand text-white' : 'text-slate-500 hover:bg-slate-100'"
              @click="groupBy = 'type'"
            >
              Loại dự án
            </button>
            <button
              type="button"
              class="rounded-[4px] px-3 py-1 text-sm font-medium transition"
              :class="groupBy === 'department' ? 'bg-brand text-white' : 'text-slate-500 hover:bg-slate-100'"
              @click="groupBy = 'department'"
            >
              Phòng ban
            </button>
          </div>
        </div>
        <div class="flex items-center gap-1">
          <button
            type="button"
            class="rounded-btn border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50"
            @click="expandAllLanes"
          >
            Mở tất cả
          </button>
          <button
            type="button"
            class="rounded-btn border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50"
            @click="collapseAllLanes"
          >
            Thu gọn tất cả
          </button>
        </div>
      </div>

      <div
        v-for="col in columns"
        :key="col.key"
        class="rounded-card bg-slate-100/70 p-3"
        @dragover.prevent
        @drop="onDrop(col)"
      >
        <!-- Row header (click to collapse) -->
        <button
          type="button"
          class="flex w-full flex-wrap items-center gap-2 rounded-md text-left transition hover:bg-white/60"
          :class="isLaneOpen(col.key) ? 'mb-3 px-1 py-1' : 'px-1 py-1'"
          @click="toggleLane(col.key)"
        >
          <AppIcon
            :name="isLaneOpen(col.key) ? 'chevron-down' : 'chevron-right'"
            :size="16"
            class="shrink-0 text-slate-400"
          />
          <span
            class="h-2.5 w-2.5 shrink-0 rounded-full"
            :class="dot[col.color] || dot.slate"
          />
          <span class="text-sm font-semibold text-slate-700">{{ col.label }}</span>
          <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-500 shadow-sm">{{ col.projects.length }}</span>
          <div
            v-if="col.projects.length"
            class="ml-2 flex items-center gap-1"
          >
            <template
              v-for="(p, i) in col.projects.slice(0, 5)"
              :key="p.id"
            >
              <Avatar
                v-if="p.manager"
                :name="p.manager.name"
                :src="p.manager.avatar_path"
                :size="20"
                :title="p.manager.name"
                :style="{ marginLeft: i === 0 ? '0' : '-4px' }"
                class="rounded-full ring-1 ring-white"
              />
            </template>
            <span
              v-if="col.projects.length > 5"
              class="ml-1 text-xs text-slate-400"
            >+{{ col.projects.length - 5 }} khác</span>
          </div>
        </button>

        <!-- Horizontal card row -->
        <div
          v-show="isLaneOpen(col.key)"
          class="kanban-row flex gap-3 overflow-x-auto pb-1"
        >
          <ProjectCard
            v-for="p in col.projects"
            :key="p.id"
            class="w-72 shrink-0"
            :project="p"
            :draggable="!!p.can?.update"
            :show-type="groupBy === 'department'"
            :show-department="groupBy === 'type'"
            @dragstart="dragId = p.id"
            @remove="remove"
          />
          <div
            v-if="col.projects.length === 0"
            class="flex min-h-[8rem] w-full items-center justify-center rounded-card border border-dashed border-slate-300 text-xs text-slate-400"
          >
            Kéo dự án vào đây
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="projects.meta?.total"
      class="card mt-4 overflow-hidden"
    >
      <DatagridPaginationFooter
        variant="bar"
        :meta="projects.meta"
        :per-page="perPage"
        :per-page-options="PER_PAGE_OPTIONS"
        @update:per-page="onPerPageChange"
      />
    </div>
  </AppLayout>
</template>

<style scoped>
.kanban-row {
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}
.kanban-row::-webkit-scrollbar { height: 6px; }
.kanban-row::-webkit-scrollbar-track { background: transparent; }
.kanban-row::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
</style>
