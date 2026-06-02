<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import ProjectCard from '@/modules/project/components/ProjectCard.vue';
import ProjectDataGrid from '@/modules/project/components/ProjectDataGrid.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import Drawer from '@/Components/Ui/Drawer.vue';
import { COLUMNS, DEFAULT_VISIBLE } from '@/modules/project/config/columns';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';
import { exportProjectList } from '@/composables/useProjectListExport';

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

let timer = null;
watch(serverFilters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/projects', {
            status: serverFilters.status || undefined,
            type: serverFilters.type || undefined,
            scope: serverFilters.scope || undefined,
            department_id: serverFilters.department_id || undefined,
            mine: serverFilters.mine || undefined,
            q: serverFilters.q || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
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

const resetFilters = async () => {
    if (activeFilterCount.value === 0) return;
    if (!await dialog.confirm({
        title: 'Xoá bộ lọc',
        message: 'Xoá tất cả bộ lọc đang áp dụng?',
        confirmText: 'Xoá lọc',
    })) return;
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
const showColumns = ref(false);
const showFilters = ref(false);
const showExportMenu = ref(false);
const exportBtnRef = ref(null);
const exportMenuRef = ref(null);
const exportScope = ref('filtered');

const runExport = (format) => {
    showExportMenu.value = false;
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
    const inExportBtn = exportBtnRef.value?.contains(e.target);
    const inExportMenu = exportMenuRef.value?.contains(e.target);
    if (!inExportBtn && !inExportMenu) showExportMenu.value = false;
};

onMounted(() => document.addEventListener('mousedown', onDocClick));
onUnmounted(() => document.removeEventListener('mousedown', onDocClick));

const refresh = () => router.reload({ preserveScroll: true });

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

// ---- Dashboard cards ------------------------------------------------------
const cards = computed(() => [
    { label: 'Tổng dự án', value: props.summary.total ?? 0, icon: 'projects', tone: 'text-brand', bg: 'bg-brand-50' },
    { label: 'Đang thực hiện', value: props.summary.active ?? 0, icon: 'sprint', tone: 'text-sky-600', bg: 'bg-sky-50' },
    { label: 'Hoàn thành', value: props.summary.completed ?? 0, icon: 'done', tone: 'text-emerald-600', bg: 'bg-emerald-50' },
    { label: 'Trễ hạn', value: props.summary.overdue ?? 0, icon: 'flag', tone: 'text-rose-600', bg: 'bg-rose-50' },
]);
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
      />
    </template>

    <!-- Dashboard summary cards -->
    <div class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
      <div
        v-for="c in cards"
        :key="c.label"
        class="card flex items-center gap-3 p-4"
      >
        <span
          class="grid h-10 w-10 shrink-0 place-items-center rounded-btn"
          :class="c.bg"
        >
          <AppIcon
            :name="c.icon"
            :size="20"
            :class="c.tone"
          />
        </span>
        <div class="min-w-0">
          <p class="truncate text-xs text-slate-500">
            {{ c.label }}
          </p>
          <p
            class="font-display text-xl font-bold"
            :class="c.tone"
          >
            {{ c.value }}
          </p>
        </div>
      </div>
    </div>

    <!-- ===== TOOLBAR ===== -->
    <div class="mb-3 flex flex-wrap items-center gap-2">
      <!-- Search -->
      <div class="relative min-w-[14rem] flex-1">
        <AppIcon
          name="search"
          :size="16"
          class="pointer-events-none absolute left-3 top-2.5 text-slate-400"
        />
        <input
          v-model="serverFilters.q"
          type="text"
          placeholder="Tìm theo tên hoặc mã dự án…"
          class="input pl-9"
        >
      </div>

      <!-- Filter button -->
      <button
        type="button"
        class="btn-ghost relative border border-slate-200"
        @click="showFilters = true"
      >
        <AppIcon
          name="filter"
          :size="16"
        /> Bộ lọc
        <span
          v-if="activeFilterCount"
          class="grid h-5 min-w-5 place-items-center rounded-full bg-brand px-1 text-[11px] font-semibold text-white"
        >{{ activeFilterCount }}</span>
      </button>

      <!-- Column chooser (list only) -->
      <div
        v-if="view === 'list'"
        class="relative"
      >
        <button
          type="button"
          class="btn-ghost border border-slate-200"
          @click="showColumns = !showColumns"
        >
          <AppIcon
            name="columns"
            :size="16"
          /> Cột
        </button>
        <div
          v-if="showColumns"
          class="absolute right-0 z-30 mt-1 w-60 rounded-card border border-slate-200 bg-white p-2 shadow-elevation-2"
          @mouseleave="showColumns = false"
        >
          <p class="px-2 py-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
            Cột hiển thị
          </p>
          <label
            v-for="c in COLUMNS"
            :key="c.key"
            class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm text-slate-600 hover:bg-slate-50"
          >
            <input
              type="checkbox"
              class="rounded"
              :checked="visibleColumns.includes(c.key)"
              @change="toggleColumn(c.key)"
            >
            {{ c.label }}
          </label>
        </div>
      </div>

      <div
        v-if="view === 'list'"
        ref="exportBtnRef"
        class="relative"
      >
        <button
          type="button"
          class="btn-ghost border border-slate-200"
          @click="showExportMenu = !showExportMenu"
        >
          <AppIcon
            name="download"
            :size="16"
          /> Xuất
          <AppIcon
            name="chevron-down"
            :size="14"
            class="opacity-50"
            :class="showExportMenu && 'rotate-180'"
          />
        </button>
        <div
          v-if="showExportMenu"
          ref="exportMenuRef"
          class="absolute right-0 z-30 mt-1 w-56 rounded-xl border border-slate-200 bg-white py-1 shadow-elevation-2"
        >
          <p class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-400">
            Phạm vi
          </p>
          <label class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-slate-50">
            <input
              v-model="exportScope"
              type="radio"
              value="filtered"
              class="text-brand"
            >
            <span>Đang hiển thị ({{ displayedProjects.length }})</span>
          </label>
          <label class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-slate-50">
            <input
              v-model="exportScope"
              type="radio"
              value="all"
              class="text-brand"
            >
            <span>Tất cả ({{ projects.data.length }})</span>
          </label>
          <div class="my-1 border-t border-slate-100" />
          <button
            type="button"
            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50"
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
            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50"
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
      <button
        type="button"
        class="btn-ghost border border-slate-200"
        title="Làm mới"
        @click="refresh"
      >
        <AppIcon
          name="refresh"
          :size="16"
        />
      </button>

      <!-- View toggle -->
      <div class="inline-flex rounded-btn border border-slate-200 bg-white p-0.5">
        <button
          type="button"
          class="flex items-center gap-1.5 rounded-[4px] px-3 py-1 text-sm font-medium transition"
          :class="view === 'list' ? 'bg-brand text-white' : 'text-slate-500 hover:bg-slate-100'"
          @click="view = 'list'"
        >
          <AppIcon
            name="report-history"
            :size="15"
          /> Bảng
        </button>
        <button
          type="button"
          class="flex items-center gap-1.5 rounded-[4px] px-3 py-1 text-sm font-medium transition"
          :class="view === 'kanban' ? 'bg-brand text-white' : 'text-slate-500 hover:bg-slate-100'"
          @click="view = 'kanban'"
        >
          <AppIcon
            name="board"
            :size="15"
          /> Kanban
        </button>
      </div>

      <Link
        v-if="can.create"
        href="/projects/create"
        class="btn-primary"
      >
        <AppIcon
          name="add"
          :size="16"
        /> Tạo dự án
      </Link>
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
      <span class="text-slate-500">{{ displayedProjects.length }} dự án</span>
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

    <!-- ===== FILTER DRAWER ===== -->
    <Drawer
      :show="showFilters"
      title="Bộ lọc nâng cao"
      @close="showFilters = false"
    >
      <div class="space-y-4">
        <div>
          <label class="label">Loại dự án</label>
          <select
            v-model="serverFilters.type"
            class="input"
          >
            <option value="">
              Tất cả loại
            </option>
            <option
              v-for="o in typeOptions"
              :key="o.value"
              :value="o.value"
            >
              {{ o.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="label">Trạng thái</label>
          <select
            v-model="serverFilters.status"
            class="input"
          >
            <option value="">
              Tất cả trạng thái
            </option>
            <option
              v-for="o in statusOptions"
              :key="o.value"
              :value="o.value"
            >
              {{ o.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="label">Phạm vi áp dụng</label>
          <select
            v-model="serverFilters.scope"
            class="input"
          >
            <option value="">
              Tất cả phạm vi
            </option>
            <option
              v-for="o in scopeOptions"
              :key="o.value"
              :value="o.value"
            >
              {{ o.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="label">Phòng ban</label>
          <select
            v-model="serverFilters.department_id"
            class="input"
          >
            <option value="">
              Tất cả phòng ban
            </option>
            <option
              v-for="d in departmentOptions"
              :key="d.id"
              :value="d.id"
            >
              {{ d.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="label">Khu vực</label>
          <select
            v-model="clientFilters.region"
            class="input"
          >
            <option value="">
              Tất cả khu vực
            </option>
            <option
              v-for="r in regionOptions"
              :key="r.value"
              :value="r.value"
            >
              {{ r.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="label">Chủ dự án</label>
          <select
            v-model="clientFilters.manager_id"
            class="input"
          >
            <option value="">
              Tất cả
            </option>
            <option
              v-for="e in employees"
              :key="e.id"
              :value="e.id"
            >
              {{ e.name }}
            </option>
          </select>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600">
          <input
            v-model="serverFilters.mine"
            true-value="1"
            false-value=""
            type="checkbox"
            class="rounded"
          > Chỉ dự án của tôi
        </label>
        <div class="border-t border-slate-100 pt-4">
          <label class="label">Lưu bộ lọc hiện tại</label>
          <div class="flex items-center gap-2">
            <input
              v-model="newFilterName"
              type="text"
              placeholder="Tên bộ lọc…"
              class="input"
              @keyup.enter="saveCurrentFilter"
            >
            <button
              type="button"
              class="btn-ghost shrink-0 border border-slate-200"
              @click="saveCurrentFilter"
            >
              <AppIcon
                name="save"
                :size="16"
              />
            </button>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-between">
          <button
            type="button"
            class="btn-ghost"
            @click="resetFilters"
          >
            Xoá lọc
          </button>
          <button
            type="button"
            class="btn-primary"
            @click="showFilters = false"
          >
            Áp dụng
          </button>
        </div>
      </template>
    </Drawer>
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
