<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import ProjectCard from '@/Components/Project/ProjectCard.vue';
import ProjectDataGrid from '@/Components/Project/ProjectDataGrid.vue';
import Drawer from '@/Components/Ui/Drawer.vue';
import { COLUMNS, DEFAULT_VISIBLE, cellValue } from '@/Components/Project/projectColumns';
import { currency } from '@/composables/useFormat';
import { useDialog } from '@/composables/useDialog';

const props = defineProps({
    projects: { type: Object, required: true }, // { data: [...] }
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

// ---- Persisted UI state ---------------------------------------------------
const VIEW_KEY = 'va-qlda.projects.view';
const GROUP_KEY = 'va-qlda.projects.groupby';
const GRIDGROUP_KEY = 'va-qlda.projects.gridgroup';
const COLS_KEY = 'va-qlda.projects.columns';
const SAVED_KEY = 'va-qlda.projects.savedfilters';

const view = ref(localStorage.getItem(VIEW_KEY) || 'list');
const groupBy = ref(localStorage.getItem(GROUP_KEY) || 'type'); // kanban grouping
const gridGroupByDept = ref(localStorage.getItem(GRIDGROUP_KEY) === '1');
watch(view, (v) => localStorage.setItem(VIEW_KEY, v));
watch(groupBy, (v) => localStorage.setItem(GROUP_KEY, v));
watch(gridGroupByDept, (v) => localStorage.setItem(GRIDGROUP_KEY, v ? '1' : '0'));

// Visible columns
const loadCols = () => {
    try {
        const saved = JSON.parse(localStorage.getItem(COLS_KEY));
        if (Array.isArray(saved) && saved.length) return saved.filter((k) => COLUMNS.some((c) => c.key === k));
    } catch (e) { /* ignore */ }
    return [...DEFAULT_VISIBLE];
};
const visibleColumns = ref(loadCols());
watch(visibleColumns, (v) => localStorage.setItem(COLS_KEY, JSON.stringify(v)), { deep: true });
const toggleColumn = (key) => {
    const s = new Set(visibleColumns.value);
    s.has(key) ? s.delete(key) : s.add(key);
    // Keep registry order
    visibleColumns.value = COLUMNS.filter((c) => s.has(c.key)).map((c) => c.key);
};

// ---- Server-side filters (pushed to controller) ---------------------------
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

// ---- Client-side post-filters (over the returned set) ---------------------
const clientFilters = reactive({ manager_id: '', region: '', budgetMin: null, budgetMax: null });

const displayedProjects = computed(() => {
    let list = props.projects.data;
    if (clientFilters.manager_id) list = list.filter((p) => p.manager?.id === Number(clientFilters.manager_id));
    if (clientFilters.region) list = list.filter((p) => (p.scope_regions || []).includes(clientFilters.region));
    if (clientFilters.budgetMin != null && clientFilters.budgetMin !== '') list = list.filter((p) => (p.budget ?? 0) >= Number(clientFilters.budgetMin));
    if (clientFilters.budgetMax != null && clientFilters.budgetMax !== '') list = list.filter((p) => (p.budget ?? 0) <= Number(clientFilters.budgetMax));
    return list;
});

const activeFilterCount = computed(() => {
    let n = 0;
    ['status', 'type', 'scope', 'department_id', 'mine'].forEach((k) => { if (serverFilters[k]) n++; });
    if (clientFilters.manager_id) n++;
    if (clientFilters.region) n++;
    if (clientFilters.budgetMin) n++;
    if (clientFilters.budgetMax) n++;
    return n;
});

const resetFilters = () => {
    Object.assign(serverFilters, { status: '', type: '', scope: '', department_id: '', mine: '', q: serverFilters.q });
    Object.assign(clientFilters, { manager_id: '', region: '', budgetMin: null, budgetMax: null });
};

// ---- Saved filters (localStorage) -----------------------------------------
const savedFilters = ref(JSON.parse(localStorage.getItem(SAVED_KEY) || '[]'));
const newFilterName = ref('');
const persistSaved = () => localStorage.setItem(SAVED_KEY, JSON.stringify(savedFilters.value));
const saveCurrentFilter = () => {
    const name = newFilterName.value.trim();
    if (!name) return;
    savedFilters.value = [...savedFilters.value.filter((f) => f.name !== name), {
        name,
        server: { ...serverFilters, q: '' },
        client: { ...clientFilters },
    }];
    persistSaved();
    newFilterName.value = '';
};
const applySaved = (f) => {
    Object.assign(serverFilters, f.server);
    Object.assign(clientFilters, f.client);
};
const deleteSaved = (name) => {
    savedFilters.value = savedFilters.value.filter((f) => f.name !== name);
    persistSaved();
};

// ---- UI popovers / drawer -------------------------------------------------
const showColumns = ref(false);
const showFilters = ref(false);

// ---- CSV export -----------------------------------------------------------
const exportCsv = () => {
    const cols = [{ key: 'name', label: 'Tên dự án' }, ...COLUMNS.filter((c) => visibleColumns.value.includes(c.key))];
    const esc = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const header = cols.map((c) => esc(c.label)).join(',');
    const rows = displayedProjects.value.map((p) => cols.map((c) => esc(c.key === 'name' ? p.name : cellValue(p, c.key))).join(','));
    const csv = '﻿' + [header, ...rows].join('\r\n'); // BOM for Excel UTF-8
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `du-an-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};

const refresh = () => router.reload({ preserveScroll: true });

// ---- Row actions ----------------------------------------------------------
const remove = async (project) => {
    if (await dialog.confirm({ title: 'Xoá dự án', message: `Xoá "${project.name}"? Hành động này không thể hoàn tác.`, tone: 'danger', confirmText: 'Xoá' })) {
        router.delete(`/projects/${project.id}`, { preserveScroll: true });
    }
};
const duplicate = (project) => {
    router.post(`/projects/${project.id}/duplicate`, {}, { preserveScroll: true });
};

// ---- Kanban (unchanged behaviour) -----------------------------------------
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
            <h1 class="font-display font-semibold text-slate-800">Danh mục dự án</h1>
        </template>

        <!-- Dashboard summary -->
        <div class="mb-5 grid grid-cols-2 gap-4 lg:grid-cols-6">
            <div v-for="c in cards" :key="c.label" class="card flex items-center gap-3 p-4">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-btn" :class="c.bg"><AppIcon :name="c.icon" :size="20" :class="c.tone" /></span>
                <div class="min-w-0">
                    <p class="truncate text-xs text-slate-500">{{ c.label }}</p>
                    <p class="font-display text-xl font-bold" :class="c.tone">{{ c.value }}</p>
                </div>
            </div>
            <div class="card p-4">
                <p class="text-xs text-slate-500">Ngân sách dự kiến</p>
                <p class="mt-1 font-display text-base font-bold text-slate-800">{{ currency(summary.budget) }}</p>
            </div>
            <div class="card p-4">
                <p class="text-xs text-slate-500">Đã sử dụng</p>
                <p class="mt-1 font-display text-base font-bold text-amber-600">{{ currency(summary.spentBudget) }}</p>
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-amber-500" :style="{ width: Math.min(100, summary.budget ? Math.round((summary.spentBudget || 0) / summary.budget * 100) : 0) + '%' }"></div>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <div class="relative min-w-[14rem] flex-1">
                <AppIcon name="search" :size="16" class="pointer-events-none absolute left-3 top-2.5 text-slate-400" />
                <input v-model="serverFilters.q" type="text" placeholder="Tìm theo tên hoặc mã dự án…" class="input pl-9" />
            </div>

            <button type="button" class="btn-ghost relative border border-slate-200" @click="showFilters = true">
                <AppIcon name="filter" :size="16" /> Bộ lọc
                <span v-if="activeFilterCount" class="grid h-5 min-w-5 place-items-center rounded-full bg-brand px-1 text-[11px] font-semibold text-white">{{ activeFilterCount }}</span>
            </button>

            <!-- Column chooser -->
            <div v-if="view === 'list'" class="relative">
                <button type="button" class="btn-ghost border border-slate-200" @click="showColumns = !showColumns">
                    <AppIcon name="columns" :size="16" /> Cột
                </button>
                <div v-if="showColumns" class="absolute right-0 z-30 mt-1 w-60 rounded-card border border-slate-200 bg-white p-2 shadow-elevation-2" @mouseleave="showColumns = false">
                    <p class="px-2 py-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Cột hiển thị</p>
                    <label v-for="c in COLUMNS" :key="c.key" class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm text-slate-600 hover:bg-slate-50">
                        <input type="checkbox" class="rounded" :checked="visibleColumns.includes(c.key)" @change="toggleColumn(c.key)" />
                        {{ c.label }}
                    </label>
                </div>
            </div>

            <button v-if="view === 'list'" type="button" class="btn-ghost border border-slate-200" @click="exportCsv">
                <AppIcon name="download" :size="16" /> CSV
            </button>
            <button type="button" class="btn-ghost border border-slate-200" title="Làm mới" @click="refresh">
                <AppIcon name="refresh" :size="16" />
            </button>

            <!-- View toggle -->
            <div class="inline-flex rounded-btn border border-slate-200 bg-white p-0.5">
                <button type="button" class="flex items-center gap-1.5 rounded-[4px] px-3 py-1 text-sm font-medium transition" :class="view === 'list' ? 'bg-brand text-white' : 'text-slate-500 hover:bg-slate-100'" @click="view = 'list'">
                    <AppIcon name="report-history" :size="15" /> Bảng
                </button>
                <button type="button" class="flex items-center gap-1.5 rounded-[4px] px-3 py-1 text-sm font-medium transition" :class="view === 'kanban' ? 'bg-brand text-white' : 'text-slate-500 hover:bg-slate-100'" @click="view = 'kanban'">
                    <AppIcon name="board" :size="15" /> Kanban
                </button>
            </div>

            <Link v-if="can.create" href="/projects/create" class="btn-primary">
                <AppIcon name="add" :size="16" /> Tạo dự án
            </Link>
        </div>

        <!-- Secondary toolbar (list) -->
        <div v-if="view === 'list'" class="mb-3 flex flex-wrap items-center gap-3 text-sm">
            <label class="flex cursor-pointer items-center gap-2 text-slate-600">
                <input v-model="gridGroupByDept" type="checkbox" class="rounded" /> Nhóm theo phòng ban
            </label>
            <span class="text-slate-300">|</span>
            <span class="text-slate-500">{{ displayedProjects.length }} dự án</span>
            <div v-if="savedFilters.length" class="ml-auto flex flex-wrap items-center gap-1.5">
                <span class="text-xs text-slate-400">Bộ lọc đã lưu:</span>
                <span v-for="f in savedFilters" :key="f.name" class="inline-flex items-center gap-1 rounded-full bg-slate-100 py-0.5 pl-2.5 pr-1 text-xs text-slate-600">
                    <button class="hover:text-brand" @click="applySaved(f)">{{ f.name }}</button>
                    <button class="grid h-4 w-4 place-items-center rounded-full text-slate-400 hover:bg-slate-200 hover:text-rose-500" @click="deleteSaved(f.name)"><AppIcon name="close" :size="11" /></button>
                </span>
            </div>
        </div>

        <!-- Kanban group-by selector -->
        <div v-if="view === 'kanban'" class="mb-3 flex items-center gap-2 text-sm">
            <span class="text-slate-500">Nhóm theo:</span>
            <div class="inline-flex rounded-btn border border-slate-200 bg-white p-0.5">
                <button type="button" class="rounded-[4px] px-3 py-1 font-medium transition" :class="groupBy === 'type' ? 'bg-brand text-white' : 'text-slate-500 hover:bg-slate-100'" @click="groupBy = 'type'">Loại dự án</button>
                <button type="button" class="rounded-[4px] px-3 py-1 font-medium transition" :class="groupBy === 'department' ? 'bg-brand text-white' : 'text-slate-500 hover:bg-slate-100'" @click="groupBy = 'department'">Phòng ban</button>
            </div>
        </div>

        <!-- ===== List / DataGrid view ===== -->
        <ProjectDataGrid
            v-if="view === 'list'"
            :projects="displayedProjects"
            :visible="visibleColumns"
            :group-by-department="gridGroupByDept"
            :department-options="departmentOptions"
            @remove="remove"
            @duplicate="duplicate"
        />

        <!-- ===== Kanban view ===== -->
        <div v-else class="flex gap-4 overflow-x-auto pb-2">
            <div
                v-for="col in columns" :key="col.key"
                class="flex w-80 shrink-0 flex-col rounded-card bg-slate-100/70"
                @dragover.prevent @drop="onDrop(col)"
            >
                <div class="flex items-center gap-2 px-3 py-2.5">
                    <span class="h-2.5 w-2.5 rounded-full" :class="dot[col.color] || dot.slate"></span>
                    <span class="text-sm font-semibold text-slate-700">{{ col.label }}</span>
                    <span class="text-xs font-normal text-slate-400">{{ col.projects.length }}</span>
                </div>
                <div class="flex-1 space-y-3 px-2.5 pb-3">
                    <ProjectCard
                        v-for="p in col.projects" :key="p.id"
                        :project="p" :draggable="!!p.can?.update"
                        :show-type="groupBy === 'department'" :show-department="groupBy === 'type'"
                        @dragstart="dragId = p.id" @remove="remove"
                    />
                    <p v-if="col.projects.length === 0" class="px-1 py-8 text-center text-xs text-slate-400">Kéo dự án vào đây</p>
                </div>
            </div>
        </div>

        <!-- ===== Advanced filter drawer ===== -->
        <Drawer :show="showFilters" title="Bộ lọc nâng cao" @close="showFilters = false">
            <div class="space-y-4">
                <div>
                    <label class="label">Loại dự án</label>
                    <select v-model="serverFilters.type" class="input">
                        <option value="">Tất cả</option>
                        <option v-for="o in typeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Trạng thái</label>
                    <select v-model="serverFilters.status" class="input">
                        <option value="">Tất cả</option>
                        <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Phạm vi áp dụng</label>
                    <select v-model="serverFilters.scope" class="input">
                        <option value="">Tất cả</option>
                        <option v-for="o in scopeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Phòng ban</label>
                    <select v-model="serverFilters.department_id" class="input">
                        <option value="">Tất cả</option>
                        <option v-for="d in departmentOptions" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Khu vực</label>
                    <select v-model="clientFilters.region" class="input">
                        <option value="">Tất cả</option>
                        <option v-for="r in regionOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Chủ dự án</label>
                    <select v-model="clientFilters.manager_id" class="input">
                        <option value="">Tất cả</option>
                        <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Ngân sách (VNĐ)</label>
                    <div class="flex items-center gap-2">
                        <input v-model.number="clientFilters.budgetMin" type="number" min="0" step="1000000" placeholder="Từ" class="input" />
                        <span class="text-slate-400">—</span>
                        <input v-model.number="clientFilters.budgetMax" type="number" min="0" step="1000000" placeholder="Đến" class="input" />
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input v-model="serverFilters.mine" true-value="1" false-value="" type="checkbox" class="rounded" /> Chỉ dự án của tôi
                </label>

                <div class="border-t border-slate-100 pt-4">
                    <label class="label">Lưu bộ lọc hiện tại</label>
                    <div class="flex items-center gap-2">
                        <input v-model="newFilterName" type="text" placeholder="Tên bộ lọc…" class="input" @keyup.enter="saveCurrentFilter" />
                        <button type="button" class="btn-ghost shrink-0 border border-slate-200" @click="saveCurrentFilter"><AppIcon name="save" :size="16" /></button>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-between">
                    <button type="button" class="btn-ghost" @click="resetFilters">Xoá lọc</button>
                    <button type="button" class="btn-primary" @click="showFilters = false">Áp dụng</button>
                </div>
            </template>
        </Drawer>
    </AppLayout>
</template>
