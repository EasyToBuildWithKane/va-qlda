<script setup>
import { ref, computed, toRef, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/Components/Project/Avatar.vue';
import BlockerFormModal from '@/Components/Project/BlockerFormModal.vue';
import RiskImportModal from '@/Components/Project/Dashboard/RiskImportModal.vue';
import RiskIssueDetailPanel from '@/Components/Project/Dashboard/RiskIssueDetailPanel.vue';
import {
    RISK_TABLE_COLUMNS,
    loadRiskTableColumns,
    RISK_TABLE_COLS_KEY,
} from '@/Components/Project/Dashboard/riskTableColumns';
import { date } from '@/composables/useFormat';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/composables/useToast';
import { useRiskTable, RISK_SEVERITY_STYLE, RISK_STATUS_STYLE } from '@/composables/useRiskTable';

const TERMINAL_STATUS = new Set(['resolved', 'closed']);

const props = defineProps({
    projectId: { type: Number, required: true },
    projectCode: { type: String, default: 'DA' },
    projectName: { type: String, default: '' },
    blockers: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    severityOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    canContribute: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
});

const canEditRow = (row) => props.canManage || row.can?.update;
const canCommentRow = () => props.canManage || props.canContribute;

const emit = defineEmits(['saved', 'highlight-end']);

const dialog = useDialog();
const toast = useToast();
const panelRef = ref(null);
const highlight = ref(false);
const bulkWorking = ref(false);
const bulkStatus = ref('in_progress');
const bulkOwnerId = ref(null);

const table = useRiskTable(toRef(() => props.blockers));

const modalOpen = ref(false);
const importModalOpen = ref(false);
const editing = ref(null);
const statusUpdating = ref(new Set());
const showColumns = ref(false);
const columnsBtnRef = ref(null);
const columnsMenuRef = ref(null);
const columnsMenuStyle = ref({ top: '0px', left: '0px' });
const exportBtnRef = ref(null);
const exportMenuRef = ref(null);
const showExportMenu = ref(false);
const exportMenuStyle = ref({ top: '0px', left: '0px' });

const positionColumnsMenu = () => {
    const btn = columnsBtnRef.value;
    if (!btn) return;
    const r = btn.getBoundingClientRect();
    const menuW = 224;
    columnsMenuStyle.value = {
        top: `${r.bottom + 4}px`,
        left: `${Math.min(Math.max(8, r.right - menuW), window.innerWidth - menuW - 8)}px`,
    };
};

const toggleColumnsMenu = async () => {
    showColumns.value = !showColumns.value;
    if (showColumns.value) {
        await nextTick();
        positionColumnsMenu();
    }
};

const onColumnsReposition = () => {
    if (showColumns.value) positionColumnsMenu();
    if (showExportMenu.value) positionExportMenu();
};

const positionExportMenu = () => {
    const btn = exportBtnRef.value;
    if (!btn) return;
    const r = btn.getBoundingClientRect();
    const menuW = 200;
    exportMenuStyle.value = {
        top: `${r.bottom + 4}px`,
        left: `${Math.min(Math.max(8, r.right - menuW), window.innerWidth - menuW - 8)}px`,
    };
};

const toggleExportMenu = async () => {
    showExportMenu.value = !showExportMenu.value;
    if (showExportMenu.value) {
        showColumns.value = false;
        await nextTick();
        positionExportMenu();
    }
};

const runExport = (format) => {
    showExportMenu.value = false;
    table.exportRisk(props.blockers, {
        projectCode: props.projectCode,
        projectName: props.projectName,
        format,
    });
    toast.success(format === 'csv' ? 'Đã xuất file CSV' : 'Đã xuất file Excel');
};

const visibleColumns = ref(loadRiskTableColumns());
watch(visibleColumns, (v) => localStorage.setItem(RISK_TABLE_COLS_KEY, JSON.stringify(v)), { deep: true });

const colVisible = (key) => visibleColumns.value.includes(key);

const toggleColumn = (key) => {
    const s = new Set(visibleColumns.value);
    if (s.has(key)) {
        if (s.size <= 1) return;
        s.delete(key);
    } else {
        s.add(key);
    }
    visibleColumns.value = RISK_TABLE_COLUMNS.filter((c) => s.has(c.key)).map((c) => c.key);
};

const fixedColCount = computed(() => {
    let n = 3; // expand + code + title
    if (props.canManage) n += 1; // checkbox
    n += 1; // actions
    return n + visibleColumns.value.length;
});

const isTerminal = (row) => TERMINAL_STATUS.has(row.status?.value);

const canResolve = (row) => canEditRow(row) && !isTerminal(row);

const changeStatus = (row, status) => {
    if (row.status?.value === status) return;
    statusUpdating.value = new Set([...statusUpdating.value, row.id]);
    router.put(`/blockers/${row.id}`, { status }, {
        preserveScroll: true,
        onSuccess: () => {
            if (status === 'resolved') {
                toast.success('Đã xác nhận giải quyết vướng mắc');
            }
        },
        onFinish: () => {
            const next = new Set(statusUpdating.value);
            next.delete(row.id);
            statusUpdating.value = next;
        },
    });
};

const markResolved = async (row) => {
    if (!await dialog.confirm({
        title: 'Xác nhận giải quyết',
        message: `Đánh dấu "${row.title}" đã được giải quyết?`,
        confirmText: 'Giải quyết',
    })) return;
    changeStatus(row, 'resolved');
};

const onDocClick = (e) => {
    const inColumnsBtn = columnsBtnRef.value?.contains(e.target);
    const inColumnsMenu = columnsMenuRef.value?.contains(e.target);
    const inExportBtn = exportBtnRef.value?.contains(e.target);
    const inExportMenu = exportMenuRef.value?.contains(e.target);
    if (!inColumnsBtn && !inColumnsMenu) showColumns.value = false;
    if (!inExportBtn && !inExportMenu) showExportMenu.value = false;
};

onMounted(() => {
    document.addEventListener('click', onDocClick);
    window.addEventListener('resize', onColumnsReposition);
    window.addEventListener('scroll', onColumnsReposition, true);
});
onUnmounted(() => {
    document.removeEventListener('click', onDocClick);
    window.removeEventListener('resize', onColumnsReposition);
    window.removeEventListener('scroll', onColumnsReposition, true);
});

const allPageSelected = computed(() => {
    const ids = table.paginated.value.map((r) => r.id);
    return ids.length > 0 && ids.every((id) => table.selected.value.has(id));
});

const openCreate = () => { editing.value = null; modalOpen.value = true; };
const openEdit = (row) => { editing.value = row; modalOpen.value = true; };

const removeOne = async (row) => {
    if (!await dialog.confirm({ title: 'Xoá rủi ro', message: `Xoá "${row.title}"?`, tone: 'danger', confirmText: 'Xoá' })) return;
    router.delete(`/blockers/${row.id}`, { preserveScroll: true });
};

const runBulk = async (action) => {
    const ids = [...table.selected.value];
    if (!ids.length) return;
    if (action === 'delete') {
        if (!await dialog.confirm({ title: 'Xoá hàng loạt', message: `Xoá ${ids.length} mục?`, tone: 'danger', confirmText: 'Xoá' })) return;
    }
    bulkWorking.value = true;
    router.post('/blockers/bulk', {
        ids,
        action,
        status: action === 'status' ? bulkStatus.value : undefined,
        owner_id: action === 'assignee' ? bulkOwnerId.value : undefined,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            table.clearSelection();
            toast.success('Đã cập nhật hàng loạt');
        },
        onFinish: () => { bulkWorking.value = false; },
    });
};

const onSaved = () => {
    emit('saved', {
        type: editing.value ? 'updated' : 'created',
        title: editing.value?.title,
    });
};

const onImported = ({ count }) => {
    toast.success(`Đã nhập ${count} vướng mắc từ file`);
    emit('saved', { type: 'imported', count });
};

const scrollHere = () => {
    panelRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    highlight.value = true;
    setTimeout(() => {
        highlight.value = false;
        emit('highlight-end');
    }, 2000);
};

const truncate = (text, max = 48) => {
    if (!text) return '—';
    return text.length > max ? `${text.slice(0, max)}…` : text;
};

defineExpose({ scrollHere });
</script>

<template>
    <div
        ref="panelRef"
        class="min-w-0 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-elevation-1 transition ring-2 dark:border-slate-700/80 dark:bg-slate-900 dark:shadow-none"
        :class="highlight ? 'ring-brand/30' : 'ring-transparent'"
    >
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
            <div>
                <h2 class="font-display text-base font-bold text-slate-900 dark:text-slate-50">Rủi ro & Vướng mắc</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ blockers.length }} mục</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <button
                        ref="columnsBtnRef"
                        type="button"
                        class="btn-ghost border border-slate-200 text-xs dark:border-slate-600"
                        @click.stop="toggleColumnsMenu"
                    >
                        <AppIcon name="columns" :size="14" /> Cột
                    </button>
                    <Teleport to="body">
                        <div
                            v-if="showColumns"
                            ref="columnsMenuRef"
                            class="fixed z-[200] w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-elevation-2 dark:border-slate-600 dark:bg-slate-900"
                            :style="columnsMenuStyle"
                            @click.stop
                        >
                            <p class="px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Cột hiển thị</p>
                            <p class="px-2 pb-1 text-[10px] text-slate-400">Mã & tiêu đề luôn hiển thị</p>
                            <label
                                v-for="c in RISK_TABLE_COLUMNS"
                                :key="c.key"
                                class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"
                            >
                                <input
                                    type="checkbox"
                                    class="rounded"
                                    :checked="colVisible(c.key)"
                                    :disabled="colVisible(c.key) && visibleColumns.length <= 1"
                                    @change="toggleColumn(c.key)"
                                />
                                {{ c.label }}
                            </label>
                        </div>
                    </Teleport>
                </div>
                <button
                    v-if="canManage"
                    type="button"
                    class="btn-ghost inline-flex items-center gap-1 border border-slate-200 text-xs dark:border-slate-600"
                    @click="importModalOpen = true"
                >
                    <AppIcon name="upload" :size="14" /> Nhập
                </button>
                <div class="relative">
                    <button
                        ref="exportBtnRef"
                        type="button"
                        class="btn-ghost inline-flex items-center gap-1 border border-slate-200 text-xs dark:border-slate-600"
                        :disabled="!blockers.length"
                        @click.stop="toggleExportMenu"
                    >
                        <AppIcon name="export" :size="14" /> Xuất
                        <AppIcon name="chevron-down" :size="12" class="opacity-60" />
                    </button>
                    <Teleport to="body">
                        <div
                            v-if="showExportMenu"
                            ref="exportMenuRef"
                            class="fixed z-[200] w-[12.5rem] overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-elevation-2 dark:border-slate-600 dark:bg-slate-900"
                            :style="exportMenuStyle"
                            @click.stop
                        >
                            <p class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Định dạng</p>
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
                                @click="runExport('xlsx')"
                            >
                                <AppIcon name="export" :size="15" class="text-emerald-600" />
                                <span>
                                    <span class="font-medium">Excel</span>
                                    <span class="block text-[10px] text-slate-400">.xlsx · có định dạng</span>
                                </span>
                            </button>
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
                                @click="runExport('csv')"
                            >
                                <AppIcon name="download" :size="15" class="text-sky-600" />
                                <span>
                                    <span class="font-medium">CSV</span>
                                    <span class="block text-[10px] text-slate-400">.csv · mở bằng Excel</span>
                                </span>
                            </button>
                        </div>
                    </Teleport>
                </div>
                <button v-if="canManage" type="button" class="btn-primary text-xs" @click="openCreate">
                    <AppIcon name="add" :size="14" /> Thêm rủi ro
                </button>
            </div>
        </div>

        <!-- Bulk bar -->
        <div
            v-if="table.selected.value.size && canManage"
            class="flex flex-wrap items-center gap-2 border-b border-brand/20 bg-brand/5 px-3 py-2 dark:bg-brand/10"
        >
            <span class="text-xs font-semibold text-brand">{{ table.selected.value.size }} đã chọn</span>
            <select v-model="bulkStatus" class="input py-1 text-xs dark:border-slate-600 dark:bg-slate-800">
                <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
            <button type="button" class="btn-ghost text-xs" :disabled="bulkWorking" @click="runBulk('status')">Đổi trạng thái</button>
            <select v-model="bulkOwnerId" class="input py-1 text-xs dark:border-slate-600 dark:bg-slate-800">
                <option :value="null">— Gỡ giao —</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
            <button type="button" class="btn-ghost text-xs" :disabled="bulkWorking" @click="runBulk('assignee')">Gán người</button>
            <button type="button" class="btn-ghost text-xs text-rose-600" :disabled="bulkWorking" @click="runBulk('delete')">Xoá</button>
            <button type="button" class="btn-ghost ml-auto text-xs" @click="table.clearSelection">Bỏ chọn</button>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] border-separate border-spacing-0 text-sm">
                <thead>
                    <tr class="text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <th v-if="canManage" class="sticky top-0 z-10 w-10 border-b border-slate-200 bg-slate-50 px-2 py-2 dark:border-slate-700 dark:bg-slate-800/95">
                            <input type="checkbox" :checked="allPageSelected" class="rounded" @change="table.toggleSelectAll" />
                        </th>
                        <th class="sticky top-0 z-10 w-9 border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/95" />
                        <th class="sticky top-0 z-10 cursor-pointer border-b border-slate-200 bg-slate-50 px-2 py-2 dark:border-slate-700 dark:bg-slate-800/95" @click="table.toggleSort('code')">
                            Mã <AppIcon name="sort" :size="11" class="inline opacity-40" />
                        </th>
                        <th class="sticky top-0 z-10 min-w-[12rem] cursor-pointer border-b border-slate-200 bg-slate-50 px-2 py-2 dark:border-slate-700 dark:bg-slate-800/95" @click="table.toggleSort('title')">
                            Tiêu đề <AppIcon name="sort" :size="11" class="inline opacity-40" />
                        </th>
                        <th v-if="colVisible('severity')" class="sticky top-0 z-10 cursor-pointer border-b border-slate-200 bg-slate-50 px-2 py-2 dark:border-slate-700 dark:bg-slate-800/95" @click="table.toggleSort('severity')">Mức độ</th>
                        <th v-if="colVisible('status')" class="sticky top-0 z-10 cursor-pointer border-b border-slate-200 bg-slate-50 px-2 py-2 dark:border-slate-700 dark:bg-slate-800/95" @click="table.toggleSort('status')">Trạng thái</th>
                        <th v-if="colVisible('owner')" class="sticky top-0 z-10 min-w-[9rem] cursor-pointer border-b border-slate-200 bg-slate-50 px-2 py-2 dark:border-slate-700 dark:bg-slate-800/95" @click="table.toggleSort('owner')">Người phụ trách</th>
                        <th v-if="colVisible('raised_by')" class="sticky top-0 z-10 min-w-[9rem] cursor-pointer border-b border-slate-200 bg-slate-50 px-2 py-2 dark:border-slate-700 dark:bg-slate-800/95" @click="table.toggleSort('raised_by')">Người ghi nhận</th>
                        <th v-if="colVisible('raised_at')" class="sticky top-0 z-10 cursor-pointer border-b border-slate-200 bg-slate-50 px-2 py-2 dark:border-slate-700 dark:bg-slate-800/95" @click="table.toggleSort('raised_at')">Ngày phát hiện</th>
                        <th v-if="colVisible('due_date')" class="sticky top-0 z-10 cursor-pointer border-b border-slate-200 bg-slate-50 px-2 py-2 dark:border-slate-700 dark:bg-slate-800/95" @click="table.toggleSort('due_date')">Hạn XL</th>
                        <th v-if="colVisible('root_cause')" class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 px-2 py-2 dark:border-slate-700 dark:bg-slate-800/95">Nguyên nhân</th>
                        <th v-if="colVisible('resolution')" class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 px-2 py-2 dark:border-slate-700 dark:bg-slate-800/95">Hướng xử lý</th>
                        <th v-if="colVisible('updated_at')" class="sticky top-0 z-10 cursor-pointer border-b border-slate-200 bg-slate-50 px-2 py-2 dark:border-slate-700 dark:bg-slate-800/95" @click="table.toggleSort('updated_at')">Cập nhật</th>
                        <th class="sticky top-0 z-10 min-w-[7rem] border-b border-slate-200 bg-slate-50 px-2 py-2 dark:border-slate-700 dark:bg-slate-800/95">Thao tác</th>
                    </tr>
                </thead>
                <tbody v-if="loading">
                    <tr v-for="i in 5" :key="i" class="animate-pulse">
                        <td :colspan="fixedColCount" class="border-b border-slate-100 px-3 py-3 dark:border-slate-800">
                            <div class="h-4 rounded bg-slate-200 dark:bg-slate-700" />
                        </td>
                    </tr>
                </tbody>
                <tbody v-else-if="!table.paginated.value.length">
                    <tr>
                        <td :colspan="fixedColCount" class="px-4 py-16 text-center">
                            <div class="mx-auto max-w-sm">
                                <span class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800">
                                    <AppIcon name="blockers" :size="24" />
                                </span>
                                <p class="font-semibold text-slate-700 dark:text-slate-200">Không có rủi ro / vướng mắc</p>
                                <p class="mt-1 text-sm text-slate-500">Thêm mục mới để theo dõi rủi ro dự án.</p>
                                <button v-if="canManage" type="button" class="btn-primary mt-4 text-sm" @click="openCreate">
                                    <AppIcon name="add" :size="14" /> Thêm rủi ro
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <template v-else>
                    <template v-for="row in table.paginated.value" :key="row.id">
                        <tr
                            class="group transition hover:bg-slate-50/80 dark:hover:bg-slate-800/50"
                            :class="row.is_overdue ? 'bg-rose-50/30 dark:bg-rose-950/10' : ''"
                        >
                            <td v-if="canManage" class="border-b border-slate-100 px-2 py-2 dark:border-slate-800">
                                <input type="checkbox" :checked="table.selected.value.has(row.id)" class="rounded" @change="table.toggleSelect(row.id)" />
                            </td>
                            <td class="border-b border-slate-100 px-1 py-2 dark:border-slate-800">
                                <button type="button" class="grid h-7 w-7 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700" @click="table.toggleExpand(row.id)">
                                    <AppIcon :name="table.expanded.value.has(row.id) ? 'chevron-down' : 'chevron-right'" :size="14" />
                                </button>
                            </td>
                            <td class="border-b border-slate-100 px-2 py-2 font-mono text-xs font-semibold text-slate-600 dark:border-slate-800 dark:text-slate-300">
                                {{ row.code }}
                            </td>
                            <td class="border-b border-slate-100 px-2 py-2 dark:border-slate-800">
                                <p class="font-medium text-slate-800 dark:text-slate-100">{{ row.title }}</p>
                            </td>
                            <td v-if="colVisible('severity')" class="border-b border-slate-100 px-2 py-2 dark:border-slate-800">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ring-1 ring-inset" :class="RISK_SEVERITY_STYLE[row.severity?.value] || RISK_SEVERITY_STYLE.medium">
                                    {{ row.severity?.label }}
                                </span>
                            </td>
                            <td v-if="colVisible('status')" class="border-b border-slate-100 px-2 py-2 dark:border-slate-800">
                                <select
                                    v-if="canEditRow(row)"
                                    :value="row.status?.value"
                                    class="h-7 max-w-[9.5rem] cursor-pointer rounded-lg border border-slate-200 bg-white py-0 pl-2 pr-7 text-[10px] font-semibold shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800"
                                    :class="RISK_STATUS_STYLE[row.status?.value] || RISK_STATUS_STYLE.open"
                                    :disabled="statusUpdating.has(row.id)"
                                    @change="changeStatus(row, $event.target.value)"
                                >
                                    <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                                <span
                                    v-else
                                    class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset"
                                    :class="RISK_STATUS_STYLE[row.status?.value] || RISK_STATUS_STYLE.open"
                                >
                                    {{ row.status?.label }}
                                </span>
                            </td>
                            <td v-if="colVisible('owner')" class="border-b border-slate-100 px-2 py-2 dark:border-slate-800">
                                <div v-if="row.owner" class="flex min-w-[8.5rem] items-center gap-1.5">
                                    <Avatar :name="row.owner.name" :src="row.owner.avatar_path" :size="22" class="shrink-0" />
                                    <span class="text-xs leading-snug text-slate-700 dark:text-slate-200">{{ row.owner.name }}</span>
                                </div>
                                <span v-else class="text-xs text-slate-400">—</span>
                            </td>
                            <td v-if="colVisible('raised_by')" class="border-b border-slate-100 px-2 py-2 dark:border-slate-800">
                                <div v-if="row.raised_by" class="flex min-w-[8.5rem] items-center gap-1.5">
                                    <Avatar :name="row.raised_by.name" :src="row.raised_by.avatar_path" :size="22" class="shrink-0" />
                                    <span class="text-xs leading-snug text-slate-700 dark:text-slate-200">{{ row.raised_by.name }}</span>
                                </div>
                                <span v-else class="text-xs text-slate-400">—</span>
                            </td>
                            <td v-if="colVisible('raised_at')" class="border-b border-slate-100 px-2 py-2 text-xs text-slate-500 dark:border-slate-800">{{ date(row.raised_at) }}</td>
                            <td v-if="colVisible('due_date')" class="border-b border-slate-100 px-2 py-2 text-xs dark:border-slate-800" :class="row.is_overdue ? 'font-semibold text-rose-600' : 'text-slate-500'">
                                {{ date(row.due_date) }}
                            </td>
                            <td v-if="colVisible('root_cause')" class="border-b border-slate-100 px-2 py-2 text-xs text-slate-500 dark:border-slate-800">{{ truncate(row.root_cause, 36) }}</td>
                            <td v-if="colVisible('resolution')" class="border-b border-slate-100 px-2 py-2 text-xs text-slate-500 dark:border-slate-800">{{ truncate(row.resolution, 36) }}</td>
                            <td v-if="colVisible('updated_at')" class="border-b border-slate-100 px-2 py-2 text-xs text-slate-500 dark:border-slate-800">{{ date(row.updated_at) }}</td>
                            <td class="border-b border-slate-100 px-2 py-2 dark:border-slate-800">
                                <span class="inline-flex gap-0.5 opacity-70 group-hover:opacity-100">
                                    <button
                                        v-if="canResolve(row)"
                                        type="button"
                                        class="inline-flex h-7 items-center gap-1 rounded-lg px-2 text-[10px] font-semibold text-emerald-700 hover:bg-emerald-50 disabled:opacity-50 dark:text-emerald-400 dark:hover:bg-emerald-950/40"
                                        :disabled="statusUpdating.has(row.id)"
                                        title="Đặt trạng thái: Đã giải quyết (tương đương chọn trong cột Trạng thái)"
                                        @click="markResolved(row)"
                                    >
                                        <AppIcon name="check" :size="13" />
                                        <span class="hidden sm:inline">Giải quyết</span>
                                    </button>
                                    <button
                                        v-if="canEditRow(row)"
                                        type="button"
                                        class="grid h-7 w-7 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700"
                                        title="Chỉnh sửa"
                                        @click="openEdit(row)"
                                    >
                                        <AppIcon name="edit" :size="14" />
                                    </button>
                                    <button
                                        v-if="row.can?.delete"
                                        type="button"
                                        class="grid h-7 w-7 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/30"
                                        title="Xoá"
                                        @click="removeOne(row)"
                                    >
                                        <AppIcon name="delete" :size="14" />
                                    </button>
                                </span>
                            </td>
                        </tr>
                        <tr v-if="table.expanded.value.has(row.id)" class="bg-slate-50/80 dark:bg-slate-800/40">
                            <td :colspan="fixedColCount" class="border-b border-slate-200 px-4 py-4 dark:border-slate-700">
                                <RiskIssueDetailPanel
                                    :row="row"
                                    :can-edit="canEditRow(row)"
                                    :can-comment="canCommentRow()"
                                />
                            </td>
                        </tr>
                    </template>
                </template>
            </table>
        </div>

        <!-- Pagination -->
        <footer
            v-if="table.sorted.value.length"
            class="flex flex-col gap-3 border-t border-slate-200/80 bg-slate-50/60 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-800/40"
        >
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Hiển thị
                <span class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">
                    {{ (table.page.value - 1) * table.pageSize.value + 1 }}–{{ Math.min(table.page.value * table.pageSize.value, table.sorted.value.length) }}
                </span>
                trong
                <span class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">{{ table.sorted.value.length }}</span>
                mục
            </p>

            <div class="flex flex-wrap items-center gap-3">
                <label class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                    <span class="whitespace-nowrap">Số dòng</span>
                    <select
                        v-model.number="table.pageSize.value"
                        class="h-8 cursor-pointer rounded-lg border border-slate-200 bg-white py-0 pl-2.5 pr-8 text-xs font-medium text-slate-700 shadow-sm transition hover:border-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-500"
                        @change="table.page.value = 1"
                    >
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                </label>

                <nav
                    class="inline-flex items-center gap-0.5 rounded-xl border border-slate-200/80 bg-white p-1 shadow-sm dark:border-slate-600 dark:bg-slate-900"
                    aria-label="Phân trang"
                >
                    <button
                        type="button"
                        class="grid h-8 w-8 place-items-center rounded-lg text-slate-600 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:text-slate-300 disabled:hover:bg-transparent dark:text-slate-300 dark:hover:bg-slate-800 dark:disabled:text-slate-600"
                        :disabled="table.page.value <= 1"
                        aria-label="Trang trước"
                        @click="table.page.value--"
                    >
                        <AppIcon name="chevron-left" :size="16" />
                    </button>
                    <span class="min-w-[5.5rem] select-none px-2 text-center text-xs font-semibold tabular-nums text-slate-700 dark:text-slate-200">
                        {{ table.page.value }}<span class="mx-1 font-normal text-slate-400">/</span>{{ table.pageCount.value }}
                    </span>
                    <button
                        type="button"
                        class="grid h-8 w-8 place-items-center rounded-lg text-slate-600 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:text-slate-300 disabled:hover:bg-transparent dark:text-slate-300 dark:hover:bg-slate-800 dark:disabled:text-slate-600"
                        :disabled="table.page.value >= table.pageCount.value"
                        aria-label="Trang sau"
                        @click="table.page.value++"
                    >
                        <AppIcon name="chevron-right" :size="16" />
                    </button>
                </nav>
            </div>
        </footer>

        <RiskImportModal
            :show="importModalOpen"
            :project-id="projectId"
            :project-code="projectCode"
            :project-name="projectName"
            :employees="employees"
            :severity-options="severityOptions"
            :status-options="statusOptions"
            @close="importModalOpen = false"
            @imported="onImported"
        />
        <BlockerFormModal
            :show="modalOpen"
            :blocker="editing"
            :projects="[{ id: projectId, name: projectName, code: projectCode }]"
            :employees="employees"
            :severity-options="severityOptions"
            :status-options="statusOptions"
            :default-project-id="projectId"
            :lock-project="true"
            :project-name="projectName"
            :project-code="projectCode"
            @close="modalOpen = false"
            @saved="onSaved"
        />
    </div>
</template>
