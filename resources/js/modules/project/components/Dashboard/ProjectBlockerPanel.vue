<script setup>
import { ref, computed, toRef, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import BlockerFormModal from '@/modules/project/components/BlockerFormModal.vue';
import BlockerRecheckModal from '@/modules/project/components/BlockerRecheckModal.vue';
import BlockerDataModal from '@/modules/project/components/Dashboard/BlockerDataModal.vue';
import BlockerInlineDetail from '@/modules/project/components/Dashboard/BlockerInlineDetail.vue';
import {
    BLOCKER_TABLE_COLUMNS,
    loadBlockerTableColumns,
    BLOCKER_TABLE_COLS_KEY,
} from '@/modules/project/components/Dashboard/blockerTableColumns';
import { date } from '@/composables/useFormat';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';
import {
    useBlockerTable,
    BLOCKER_SEVERITY_DOT,
    BLOCKER_SEVERITY_TEXT,
    BLOCKER_STATUS_TEXT,
} from '@/composables/useBlockerTable';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useFixedDropdownAnchor } from '@/shared/composables/useFixedDropdownAnchor';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';

const BLOCKER_FILTER_CONTROLS_DEF = [
    { key: 'status', label: 'Trạng thái', default: false },
    { key: 'severity', label: 'Mức độ', default: false },
    { key: 'owner', label: 'Người phụ trách', default: false },
];
const VISIBLE_FILTERS_KEY = 'va-workspace.project-blockers.visible-filters.v3';
const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm dark:border-slate-600 dark:bg-slate-800';

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
    /** `page` = tab Vướng mắc trên Project Show */
    layout: { type: String, default: 'panel' },
    /** KPI summary từ controller (aggregate) — dùng cho strip nhúng */
    blockerSummary: { type: Object, default: () => ({}) },
});

const canEditRow = (row) => props.canManage || row.can?.update;
const canCommentRow = () => props.canManage || props.canContribute;

const emit = defineEmits(['saved', 'highlight-end']);

const dialog = useDialog();
const toast = useToast();
const panelRef = ref(null);
const highlight = ref(false);
const table = useBlockerTable(toRef(() => props.blockers));
const search = table.search;
const filterStatus = table.filterStatus;
const filterSeverity = table.filterSeverity;
const filterOwner = table.filterOwner;

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(BLOCKER_FILTER_CONTROLS_DEF, VISIBLE_FILTERS_KEY);
const filterPanelDdRef = ref(null);

const modalOpen = ref(false);
const dataModalOpen = ref(false);
const dataModalInitialTab = ref('import');
const editing = ref(null);
const recheckOpen = ref(false);
const recheckTarget = ref(null);
const statusUpdating = ref(new Set());
const dataMenuRef = ref(null);
const dataMenu = ref(false);
const { panelStyle: dataMenuStyle } = useFixedDropdownAnchor(
    () => dataMenuRef.value,
    dataMenu,
    { width: 248, zIndex: 200 },
);
const exportRowCount = computed(() => table.filtered.value.length);
const actionMenuRowId = ref(null);
const actionBtnRefs = ref({});
const actionMenuRef = ref(null);
const actionMenuStyle = ref({ top: '0px', left: '0px' });

// ── Column visibility ──
const visibleColumns = ref(loadBlockerTableColumns());
watch(visibleColumns, (v) => localStorage.setItem(BLOCKER_TABLE_COLS_KEY, JSON.stringify(v)), { deep: true });
const colVisible = (key) => visibleColumns.value.includes(key);
const showColumnsMenu = ref(false);
const columnsBtnRef = ref(null);
const columnsMenuRef = ref(null);
const columnsMenuStyle = ref({ top: '0px', left: '0px' });

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
    showColumnsMenu.value = !showColumnsMenu.value;
    if (showColumnsMenu.value) {
        showFilterPanelDd.value = false;
        dataMenu.value = false;
        closeActionMenu();
        await nextTick();
        positionColumnsMenu();
    }
};

const toggleColumn = (key) => {
    const s = new Set(visibleColumns.value);
    if (s.has(key)) {
        if (s.size <= 1) return;
        s.delete(key);
    } else {
        s.add(key);
    }
    visibleColumns.value = BLOCKER_TABLE_COLUMNS.filter((c) => s.has(c.key)).map((c) => c.key);
};

const fixedColCount = computed(() => {
    let n = 1; // title
    n += 1; // actions
    return n + visibleColumns.value.length;
});

const setActionBtnRef = (rowId, el) => {
    if (el) actionBtnRefs.value[rowId] = el;
    else delete actionBtnRefs.value[rowId];
};

const positionActionMenu = (rowId) => {
    const btn = actionBtnRefs.value[rowId];
    if (!btn) return;
    const r = btn.getBoundingClientRect();
    const menuW = 200;
    actionMenuStyle.value = {
        top: `${r.bottom + 4}px`,
        left: `${Math.min(Math.max(8, r.right - menuW), window.innerWidth - menuW - 8)}px`,
    };
};

const closeActionMenu = () => { actionMenuRowId.value = null; };

const toggleActionMenu = async (row) => {
    if (actionMenuRowId.value === row.id) { closeActionMenu(); return; }
    actionMenuRowId.value = row.id;
    showColumnsMenu.value = false;
    dataMenu.value = false;
    await nextTick();
    positionActionMenu(row.id);
};

const onRepositionAll = () => {
    if (showColumnsMenu.value) positionColumnsMenu();
    if (actionMenuRowId.value) positionActionMenu(actionMenuRowId.value);
};

const menuToggleDetail = (row) => { table.toggleExpand(row.id); closeActionMenu(); };
const menuEdit = (row) => { closeActionMenu(); openEdit(row); };
const menuResolve = async (row) => { closeActionMenu(); await markResolved(row); };
const menuRecheck = (row) => {
    closeActionMenu();
    recheckTarget.value = row;
    recheckOpen.value = true;
};
const menuDelete = async (row) => { closeActionMenu(); await removeOne(row); };

const onRecheckClosed = () => {
    const hadTarget = Boolean(recheckTarget.value);
    recheckOpen.value = false;
    recheckTarget.value = null;
    if (hadTarget) {
        emit('saved', { type: 'rechecked' });
    }
};

const toggleDataMenu = () => {
    dataMenu.value = !dataMenu.value;
    if (dataMenu.value) {
        showColumnsMenu.value = false;
        showFilterPanelDd.value = false;
        closeActionMenu();
    }
};

const openDataModal = (tab = 'import') => {
    dataMenu.value = false;
    dataModalInitialTab.value = tab;
    dataModalOpen.value = true;
};

const runExportFromMenu = (format) => {
    dataMenu.value = false;
    if (!exportRowCount.value) {
        toast.warning('Không có dữ liệu để xuất.');
        return;
    }
    table.exportRisk(table.filtered.value, {
        projectCode: props.projectCode,
        projectName: props.projectName,
        format,
    });
    toast.success(format === 'csv' ? 'Đã xuất file CSV' : 'Đã xuất file Excel');
};

const openBlockerFilterPanel = () => {
    openFilterPanel(() => {
        showColumnsMenu.value = false;
        dataMenu.value = false;
        closeActionMenu();
    });
};

watch(
    () => [search.value, filterStatus.value, filterSeverity.value, filterOwner.value],
    () => { table.page.value = 1; },
);

const listSummary = computed(() => {
    const total = props.blockers.length;
    const shown = table.filtered.value.length;
    if (shown === total) return `${total} mục`;
    return `${shown} / ${total} mục (đã lọc)`;
});

const actionMenuRow = computed(() =>
    (props.blockers ?? []).find((r) => r.id === actionMenuRowId.value) ?? null,
);

const isTerminal = (row) => TERMINAL_STATUS.has(row.status?.value);
const canResolve = (row) => canEditRow(row) && !isTerminal(row);

const changeStatus = (row, status) => {
    if (isTerminal(row) || row.status?.value === status) return;
    statusUpdating.value = new Set([...statusUpdating.value, row.id]);
    router.put(`/blockers/${row.id}`, { status }, {
        preserveScroll: true,
        onSuccess: () => {
            if (status === 'resolved') toast.success('Đã xác nhận giải quyết vướng mắc');
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
    const inDataMenu = dataMenuRef.value?.contains(e.target)
        || e.target.closest?.('[data-blocker-data-panel]');
    const inFilterPanel = filterPanelDdRef.value?.contains(e.target)
        || e.target.closest?.('[data-filter-visibility-panel]');
    const inActionMenu = actionMenuRef.value?.contains(e.target);
    const inAnyActionBtn = Object.values(actionBtnRefs.value).some((el) => el?.contains(e.target));
    if (!inColumnsBtn && !inColumnsMenu) showColumnsMenu.value = false;
    if (!inDataMenu) dataMenu.value = false;
    if (!inFilterPanel) showFilterPanelDd.value = false;
    if (!inActionMenu && !inAnyActionBtn) closeActionMenu();
};

onMounted(() => {
    document.addEventListener('mousedown', onDocClick);
    window.addEventListener('resize', onRepositionAll);
    window.addEventListener('scroll', onRepositionAll, true);
});
onUnmounted(() => {
    document.removeEventListener('mousedown', onDocClick);
    window.removeEventListener('resize', onRepositionAll);
    window.removeEventListener('scroll', onRepositionAll, true);
});

const openCreate = () => { editing.value = null; modalOpen.value = true; };
const openEdit = (row) => { editing.value = row; modalOpen.value = true; };

const removeOne = async (row) => {
    if (!await dialog.confirm({ title: 'Xoá vướng mắc', message: `Xoá "${row.title}"?`, tone: 'danger', confirmText: 'Xoá' })) return;
    router.delete(`/blockers/${row.id}`, { preserveScroll: true });
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
    if (!text?.trim()) return displayOrEmpty(null, EMPTY_LABELS.generic);
    return text.length > max ? `${text.slice(0, max)}…` : text;
};

const openImport = () => openDataModal('import');

defineExpose({ scrollHere, openCreate, openImport });
</script>

<template>
  <div
    ref="panelRef"
    class="w-full min-w-0 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-elevation-1 transition ring-2 dark:border-slate-700/80 dark:bg-slate-900 dark:shadow-none"
    :class="highlight ? 'ring-brand/30' : 'ring-transparent'"
  >
    <!-- ── Header + toolbar ── -->
    <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800 sm:px-5 sm:py-4">
      <div
        v-if="layout !== 'page'"
        class="mb-3 flex flex-wrap items-center justify-between gap-3"
      >
        <div>
          <h2 class="font-display text-base font-bold text-slate-900 dark:text-slate-50">
            Vướng mắc
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            {{ listSummary }}
          </p>
        </div>
      </div>

      <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
        <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
          <DatagridToolbarSearch
            v-model="search"
            input-id="blocker-panel-search"
            placeholder="Mã, tiêu đề, mô tả…"
            stretch
            inline-actions
            hide-label
            input-height="h-10"
          />
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-2">
          <div
            ref="filterPanelDdRef"
            class="relative shrink-0"
          >
            <DatagridToolbarActionButton
              icon="filter"
              :active="showFilterPanelDd"
              :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
              @click.stop="openBlockerFilterPanel"
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
            ref="columnsBtnRef"
            class="relative shrink-0"
          >
            <DatagridToolbarActionButton
              icon="columns"
              :active="showColumnsMenu"
              title="Cột hiển thị"
              @click.stop="toggleColumnsMenu"
            >
              Cột
            </DatagridToolbarActionButton>
            <Teleport to="body">
              <div
                v-if="showColumnsMenu"
                ref="columnsMenuRef"
                class="fixed z-[200] w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-elevation-2 dark:border-slate-600 dark:bg-slate-900"
                :style="columnsMenuStyle"
                @click.stop
              >
                <p class="px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                  Cột hiển thị
                </p>
                <p class="px-2 pb-1 text-[10px] text-slate-400">
                  Cột «Vướng mắc» luôn hiển thị
                </p>
                <label
                  v-for="c in BLOCKER_TABLE_COLUMNS"
                  :key="c.key"
                  class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"
                >
                  <input
                    type="checkbox"
                    class="rounded border-slate-300 text-brand focus:ring-brand/30"
                    :checked="colVisible(c.key)"
                    :disabled="colVisible(c.key) && visibleColumns.length <= 1"
                    @change="toggleColumn(c.key)"
                  >
                  {{ c.label }}
                </label>
              </div>
            </Teleport>
          </div>

          <div
            ref="dataMenuRef"
            class="relative shrink-0"
          >
            <DatagridToolbarActionButton
              icon="upload"
              :active="dataMenu"
              title="Nhập · Xuất · Đối soát vướng mắc"
              @click.stop="toggleDataMenu"
            >
              Dữ liệu
            </DatagridToolbarActionButton>
          </div>
        </div>

        <div
          v-if="canManage"
          class="ml-auto flex w-full shrink-0 items-center justify-end gap-2 sm:w-auto lg:w-auto"
        >
          <button
            type="button"
            class="btn-primary inline-flex h-10 shrink-0 items-center gap-1.5 px-3 text-xs font-semibold sm:px-4 sm:text-sm"
            @click="openCreate"
          >
            <AppIcon
              name="add"
              :size="15"
            />
            Thêm vướng mắc
          </button>
        </div>
      </div>

      <!-- Data menu dropdown -->
      <Teleport to="body">
        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="opacity-0 scale-95"
          leave-active-class="transition duration-100 ease-in"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="dataMenu"
            :style="dataMenuStyle"
            class="overflow-hidden rounded-card border border-slate-200 bg-white p-1 shadow-elevation-2 dark:border-slate-600 dark:bg-slate-900"
            data-blocker-data-panel
          >
            <button
              type="button"
              class="flex w-full items-center gap-2.5 rounded-btn px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-800"
              @click="runExportFromMenu('xlsx')"
            >
              <AppIcon
                name="export"
                :size="15"
                class="shrink-0 text-emerald-600"
              />
              <div>
                <span class="block text-sm font-medium text-slate-700 dark:text-slate-200">Xuất trang này (.xlsx)</span>
                <span class="block text-[10px] text-slate-400">{{ exportRowCount }} mục · có định dạng</span>
              </div>
            </button>
            <hr class="my-1 border-slate-100 dark:border-slate-700">
            <button
              type="button"
              class="flex w-full items-center gap-2.5 rounded-btn px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-800"
              @click="openDataModal('import')"
            >
              <AppIcon
                name="upload"
                :size="15"
                class="shrink-0 text-brand"
              />
              <div>
                <span class="block text-sm font-medium text-slate-700 dark:text-slate-200">Nhập / Xuất / Đối soát…</span>
                <span class="block text-[10px] text-slate-400">File mẫu · xem trước · đối soát dữ liệu</span>
              </div>
            </button>
          </div>
        </Transition>
      </Teleport>

      <!-- Filter row -->
      <Transition name="fade-slide">
        <div
          v-if="hasFilterRow"
          class="grid grid-cols-1 gap-3 border-t border-slate-100 px-0 pt-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6 dark:border-slate-800"
        >
          <DatagridFilterField v-if="visibleFilters.status">
            <select
              v-model="filterStatus"
              :class="FILTER_CONTROL_CLASS"
              aria-label="Trạng thái"
            >
              <option value="all">
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

          <DatagridFilterField v-if="visibleFilters.severity">
            <select
              v-model="filterSeverity"
              :class="FILTER_CONTROL_CLASS"
              aria-label="Mức độ"
            >
              <option value="all">
                Mức độ
              </option>
              <option
                v-for="o in severityOptions"
                :key="o.value"
                :value="o.value"
              >
                {{ o.label }}
              </option>
            </select>
          </DatagridFilterField>

          <DatagridFilterField v-if="visibleFilters.owner">
            <select
              v-model="filterOwner"
              :class="FILTER_CONTROL_CLASS"
              aria-label="Người phụ trách"
            >
              <option value="all">
                Người phụ trách
              </option>
              <option value="none">
                Chưa giao
              </option>
              <option
                v-for="o in table.ownerOptions.value"
                :key="o.id"
                :value="String(o.id)"
              >
                {{ o.name }}
              </option>
            </select>
          </DatagridFilterField>

          <div
            v-if="search || filterStatus !== 'all' || filterSeverity !== 'all' || filterOwner !== 'all'"
            class="col-span-full flex justify-end"
          >
            <button
              type="button"
              class="text-xs font-medium text-brand"
              @click="table.resetFilters()"
            >
              Đặt lại bộ lọc
            </button>
          </div>
        </div>
      </Transition>

      <p
        v-if="layout === 'page'"
        class="mt-2 text-xs text-slate-500 dark:text-slate-400"
      >
        {{ listSummary }}
      </p>
    </div>

    <!-- ── Table ── -->
    <div class="overflow-x-auto">
      <table class="w-full min-w-[640px] border-separate border-spacing-0 text-sm">
        <thead>
          <tr class="text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            <th
              class="sticky top-0 z-10 min-w-[11rem] cursor-pointer border-b border-slate-200 bg-slate-50 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-800/95"
              @click="table.toggleSort('title')"
            >
              Vướng mắc <AppIcon
                name="sort"
                :size="11"
                class="inline opacity-40"
              />
            </th>
            <th
              v-if="colVisible('severity')"
              class="sticky top-0 z-10 w-[5.5rem] cursor-pointer border-b border-slate-200 bg-slate-50 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-800/95"
              @click="table.toggleSort('severity')"
            >
              Mức độ
            </th>
            <th
              v-if="colVisible('status')"
              class="sticky top-0 z-10 w-[6.5rem] cursor-pointer border-b border-slate-200 bg-slate-50 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-800/95"
              @click="table.toggleSort('status')"
            >
              Trạng thái
            </th>
            <th
              v-if="colVisible('owner')"
              class="sticky top-0 z-10 min-w-[9rem] cursor-pointer border-b border-slate-200 bg-slate-50 px-2 py-2 dark:border-slate-700 dark:bg-slate-800/95"
              @click="table.toggleSort('owner')"
            >
              Người phụ trách
            </th>
            <th
              v-if="colVisible('raised_by')"
              class="sticky top-0 z-10 min-w-[9rem] cursor-pointer border-b border-slate-200 bg-slate-50 px-2 py-2 dark:border-slate-700 dark:bg-slate-800/95"
              @click="table.toggleSort('raised_by')"
            >
              Người ghi nhận
            </th>
            <th
              v-if="colVisible('raised_at')"
              class="sticky top-0 z-10 cursor-pointer border-b border-slate-200 bg-slate-50 px-2 py-2 dark:border-slate-700 dark:bg-slate-800/95"
              @click="table.toggleSort('raised_at')"
            >
              Ngày phát hiện
            </th>
            <th
              v-if="colVisible('due_date')"
              class="sticky top-0 z-10 cursor-pointer border-b border-slate-200 bg-slate-50 px-2 py-2 dark:border-slate-700 dark:bg-slate-800/95"
              @click="table.toggleSort('due_date')"
            >
              Hạn XL
            </th>
            <th
              v-if="colVisible('root_cause')"
              class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 px-2 py-2 dark:border-slate-700 dark:bg-slate-800/95"
            >
              Nguyên nhân
            </th>
            <th
              v-if="colVisible('resolution')"
              class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 px-2 py-2 dark:border-slate-700 dark:bg-slate-800/95"
            >
              Hướng xử lý
            </th>
            <th
              v-if="colVisible('updated_at')"
              class="sticky top-0 z-10 cursor-pointer border-b border-slate-200 bg-slate-50 px-2 py-2 dark:border-slate-700 dark:bg-slate-800/95"
              @click="table.toggleSort('updated_at')"
            >
              Cập nhật
            </th>
            <th class="sticky top-0 z-10 w-10 border-b border-slate-200 bg-slate-50 px-1 py-1.5 text-right dark:border-slate-700 dark:bg-slate-800/95">
              <span class="sr-only">Thao tác</span>
            </th>
          </tr>
        </thead>
        <tbody v-if="loading">
          <tr
            v-for="i in 5"
            :key="i"
            class="animate-pulse"
          >
            <td
              :colspan="fixedColCount"
              class="border-b border-slate-100 px-3 py-3 dark:border-slate-800"
            >
              <div class="h-4 rounded bg-slate-200 dark:bg-slate-700" />
            </td>
          </tr>
        </tbody>
        <tbody v-else-if="!table.paginated.value.length">
          <tr>
            <td
              :colspan="fixedColCount"
              class="px-4 py-16 text-center"
            >
              <div class="mx-auto max-w-sm">
                <span class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800">
                  <AppIcon
                    name="blockers"
                    :size="24"
                  />
                </span>
                <p class="font-semibold text-slate-700 dark:text-slate-200">
                  {{ blockers.length ? 'Không có kết quả phù hợp' : 'Không có vướng mắc' }}
                </p>
                <p class="mt-1 text-sm text-slate-500">
                  {{ blockers.length ? 'Thử đổi từ khoá hoặc bộ lọc.' : 'Thêm mục mới để theo dõi vướng mắc dự án.' }}
                </p>
                <button
                  v-if="canManage"
                  type="button"
                  class="btn-primary mt-4 text-sm"
                  @click="openCreate"
                >
                  <AppIcon
                    name="add"
                    :size="14"
                  /> Thêm vướng mắc
                </button>
              </div>
            </td>
          </tr>
        </tbody>
        <template v-else>
          <template
            v-for="row in table.paginated.value"
            :key="row.id"
          >
            <tr
              class="group transition hover:bg-slate-50/80 dark:hover:bg-slate-800/50"
              :class="row.is_overdue ? 'bg-rose-50/20 dark:bg-rose-950/10' : ''"
            >
              <td class="border-b border-slate-100 px-2 py-1 dark:border-slate-800">
                <p class="line-clamp-2 text-[13px] font-medium leading-snug text-slate-800 dark:text-slate-100">
                  {{ row.title }}
                </p>
                <p
                  v-if="row.description?.trim()"
                  class="mt-1 line-clamp-3 whitespace-pre-wrap text-[11px] leading-snug text-slate-600 dark:text-slate-400"
                  :title="row.description"
                >
                  {{ row.description.trim() }}
                </p>
                <p class="mt-0.5 font-mono text-[10px] text-slate-400">
                  {{ row.code }}
                </p>
              </td>
              <td
                v-if="colVisible('severity')"
                class="border-b border-slate-100 px-2 py-1 dark:border-slate-800"
              >
                <span
                  class="inline-flex items-center gap-1.5 text-[11px] leading-tight"
                  :class="BLOCKER_SEVERITY_TEXT[row.severity?.value] || BLOCKER_SEVERITY_TEXT.medium"
                >
                  <span
                    class="h-1.5 w-1.5 shrink-0 rounded-full"
                    :class="BLOCKER_SEVERITY_DOT[row.severity?.value] || BLOCKER_SEVERITY_DOT.medium"
                  />
                  {{ row.severity?.label }}
                </span>
              </td>
              <td
                v-if="colVisible('status')"
                class="border-b border-slate-100 px-2 py-1 dark:border-slate-800"
              >
                <span
                  class="text-[11px]"
                  :class="BLOCKER_STATUS_TEXT[row.status?.value] || BLOCKER_STATUS_TEXT.open"
                >
                  {{ row.status?.label }}
                </span>
              </td>
              <td
                v-if="colVisible('owner')"
                class="border-b border-slate-100 px-2 py-1 dark:border-slate-800"
              >
                <div
                  v-if="row.owner"
                  class="flex max-w-[7.5rem] items-center gap-1"
                  :title="row.owner.name"
                >
                  <Avatar
                    :name="row.owner.name"
                    :src="row.owner.avatar_path"
                    :size="18"
                    class="shrink-0"
                  />
                  <span class="truncate text-[11px] text-slate-600 dark:text-slate-300">{{ row.owner.name }}</span>
                </div>
                <span
                  v-else
                  class="text-[11px] text-slate-400"
                >{{ EMPTY_LABELS.generic }}</span>
              </td>
              <td
                v-if="colVisible('raised_by')"
                class="border-b border-slate-100 px-2 py-1 dark:border-slate-800"
              >
                <div
                  v-if="row.raised_by"
                  class="flex max-w-[7.5rem] items-center gap-1"
                  :title="row.raised_by.name"
                >
                  <Avatar
                    :name="row.raised_by.name"
                    :src="row.raised_by.avatar_path"
                    :size="18"
                    class="shrink-0"
                  />
                  <span class="truncate text-[11px] text-slate-600 dark:text-slate-300">{{ row.raised_by.name }}</span>
                </div>
                <span
                  v-else
                  class="text-[11px] text-slate-400"
                >{{ EMPTY_LABELS.generic }}</span>
              </td>
              <td
                v-if="colVisible('raised_at')"
                class="border-b border-slate-100 px-2 py-2 text-xs text-slate-500 dark:border-slate-800"
              >
                {{ displayOrEmpty(date(row.raised_at), EMPTY_LABELS.notUpdated) }}
              </td>
              <td
                v-if="colVisible('due_date')"
                class="border-b border-slate-100 px-2 py-2 text-xs dark:border-slate-800"
                :class="row.is_overdue ? 'font-semibold text-rose-600' : 'text-slate-500'"
              >
                {{ displayOrEmpty(date(row.due_date), 'Chưa đặt hạn') }}
              </td>
              <td
                v-if="colVisible('root_cause')"
                class="border-b border-slate-100 px-2 py-2 text-xs text-slate-500 dark:border-slate-800"
              >
                {{ truncate(row.root_cause, 36) }}
              </td>
              <td
                v-if="colVisible('resolution')"
                class="border-b border-slate-100 px-2 py-2 text-xs text-slate-500 dark:border-slate-800"
              >
                {{ truncate(row.resolution, 36) }}
              </td>
              <td
                v-if="colVisible('updated_at')"
                class="border-b border-slate-100 px-2 py-2 text-xs text-slate-500 dark:border-slate-800"
              >
                {{ displayOrEmpty(date(row.updated_at), EMPTY_LABELS.notUpdated) }}
              </td>
              <td class="border-b border-slate-100 px-1 py-1 text-right dark:border-slate-800">
                <button
                  :ref="(el) => setActionBtnRef(row.id, el)"
                  type="button"
                  class="grid h-7 w-7 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700"
                  :class="actionMenuRowId === row.id || table.expanded.value.has(row.id) ? 'bg-slate-100 text-slate-700 dark:bg-slate-700' : ''"
                  title="Thao tác"
                  @click.stop="toggleActionMenu(row)"
                >
                  <AppIcon
                    name="more-horizontal"
                    :size="16"
                  />
                </button>
              </td>
            </tr>
            <tr
              v-if="table.expanded.value.has(row.id)"
              class="bg-slate-50/80 dark:bg-slate-800/40"
            >
              <td
                :colspan="fixedColCount"
                class="border-b border-slate-200 px-4 py-4 dark:border-slate-700"
              >
                <BlockerInlineDetail
                  :row="row"
                  :can-comment="canCommentRow()"
                />
              </td>
            </tr>
          </template>
        </template>
      </table>
    </div>

    <!-- ── Pagination ── -->
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
            <AppIcon
              name="chevron-left"
              :size="16"
            />
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
            <AppIcon
              name="chevron-right"
              :size="16"
            />
          </button>
        </nav>
      </div>
    </footer>

    <!-- ── Action menu ── -->
    <Teleport to="body">
      <div
        v-if="actionMenuRow"
        ref="actionMenuRef"
        class="fixed z-[200] w-[12.5rem] overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-elevation-2 dark:border-slate-600 dark:bg-slate-900"
        :style="actionMenuStyle"
        @click.stop
      >
        <button
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
          @click="menuToggleDetail(actionMenuRow)"
        >
          <AppIcon
            :name="table.expanded.value.has(actionMenuRow.id) ? 'chevron-down' : 'chevron-right'"
            :size="15"
            class="text-slate-400"
          />
          {{ table.expanded.value.has(actionMenuRow.id) ? 'Thu gọn chi tiết' : 'Xem chi tiết' }}
        </button>
        <button
          v-if="canEditRow(actionMenuRow)"
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
          @click="menuEdit(actionMenuRow)"
        >
          <AppIcon
            name="edit"
            :size="15"
            class="text-slate-400"
          />
          Chỉnh sửa &amp; tải file
        </button>
        <button
          v-if="canResolve(actionMenuRow)"
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-emerald-700 hover:bg-emerald-50 disabled:opacity-50 dark:text-emerald-400 dark:hover:bg-emerald-950/40"
          :disabled="statusUpdating.has(actionMenuRow.id)"
          @click="menuResolve(actionMenuRow)"
        >
          <AppIcon
            name="check"
            :size="15"
          />
          Giải quyết
        </button>
        <button
          v-if="actionMenuRow.can?.recheck"
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-violet-700 hover:bg-violet-50 dark:text-violet-300 dark:hover:bg-violet-950/40"
          @click="menuRecheck(actionMenuRow)"
        >
          <AppIcon
            name="review-reports"
            :size="15"
          />
          Kiểm tra lại
        </button>
        <button
          v-if="actionMenuRow.can?.delete"
          type="button"
          class="flex w-full items-center gap-2 border-t border-slate-100 px-3 py-2 text-left text-sm text-rose-600 hover:bg-rose-50 dark:border-slate-800 dark:hover:bg-rose-950/30"
          @click="menuDelete(actionMenuRow)"
        >
          <AppIcon
            name="delete"
            :size="15"
          />
          Xoá
        </button>
      </div>
    </Teleport>

    <!-- ── Modals ── -->
    <BlockerRecheckModal
      :show="recheckOpen"
      :blocker="recheckTarget"
      @close="onRecheckClosed"
    />
    <BlockerDataModal
      :show="dataModalOpen"
      :project-id="projectId"
      :project-code="projectCode"
      :project-name="projectName"
      :employees="employees"
      :severity-options="severityOptions"
      :status-options="statusOptions"
      :can-manage="canManage"
      :blockers="blockers"
      :initial-tab="dataModalInitialTab"
      @close="dataModalOpen = false"
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
      :can-upload-attachments="canManage || canContribute || (editing && canEditRow(editing))"
      @close="modalOpen = false"
      @saved="onSaved"
    />
  </div>
</template>
