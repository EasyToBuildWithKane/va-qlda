<script setup>
import { reactive, ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import Badge from '@/shared/ui/Badge.vue';
import BlockerFormModal from '@/modules/project/components/BlockerFormModal.vue';
import BlockerAttachmentsBlock from '@/modules/project/components/BlockerAttachmentsBlock.vue';
import CommentThread from '@/shared/ui/CommentThread.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { date, datetime } from '@/composables/useFormat';
import { useDialog } from '@/composables/useDialog';

const PER_PAGE_OPTIONS = [5, 10, 15, 20];
const GROUP_GENERAL = '__general__';
const GENERAL_GROUP_LABEL = 'Thắc mắc chung';

const SEVERITY_RANK = { critical: 0, high: 1, medium: 2, low: 3 };

/** Viền trái theo mức độ — nền hàng giữ trắng để dễ đọc */
const SEVERITY_ACCENT = {
    critical: 'border-l-[3px] border-l-rose-600',
    high: 'border-l-[3px] border-l-rose-500',
    medium: 'border-l-[3px] border-l-amber-400',
    low: 'border-l-[3px] border-l-slate-200',
};

const TERMINAL_STATUSES = new Set(['resolved', 'closed']);

/** Phạm vi danh sách (khác giá trị enum BlockerStatus). */
const STATUS_SCOPE_OPTIONS = [
    { value: '', label: 'Mặc định — trừ đã đóng' },
    { value: 'active', label: 'Chưa kết thúc' },
    { value: 'all', label: 'Tất cả trạng thái' },
];

const props = defineProps({
    blockers: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const dialog = useDialog();
const modal = ref(false);
const editing = ref(null);
const filterPanelDdRef = ref(null);
const colDdRef = ref(null);
const statusUpdating = ref(new Set());
const perPage = ref(Number(props.filters.per_page) || props.blockers.meta?.per_page || 10);

const BLOCKER_FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái' },
    { key: 'severity', label: 'Mức độ' },
    { key: 'project', label: 'Dự án' },
    { key: 'owner', label: 'Người xử lý' },
    { key: 'raised_by', label: 'Người báo' },
    { key: 'overdue', label: 'Quá hạn' },
    { key: 'mine', label: 'Tôi xử lý' },
];

const BLOCKER_TABLE_COLUMNS = [
    { key: 'code', label: 'Mã' },
    { key: 'title', label: 'Vướng mắc' },
    { key: 'task', label: 'Công việc', default: false },
    { key: 'severity', label: 'Mức độ' },
    { key: 'status', label: 'Trạng thái' },
    { key: 'raised_by', label: 'Người báo', default: false },
    { key: 'owner', label: 'Người xử lý' },
    { key: 'raised_at', label: 'Ngày báo', default: false },
    { key: 'due_date', label: 'Hạn xử lý' },
    { key: 'resolved_at', label: 'Ngày xử lý xong', default: false },
    { key: 'comments', label: 'Bình luận', default: false },
    { key: 'description', label: 'Mô tả', default: false },
    { key: 'root_cause', label: 'Nguyên nhân', default: false },
];

const COLLAPSE_STORAGE_KEY = 'va-qlda.blockers.collapsed-groups';

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
const expandedRows = ref(new Set());
/** @type {import('vue').Ref<Record<number, 'detail'|'comments'>>} */
const rowExpandFocus = ref({});
const openActionMenuId = ref(null);

function isRowExpanded(id) {
    return expandedRows.value.has(id);
}

function closeActionMenu() {
    openActionMenuId.value = null;
}

function toggleActionMenu(id) {
    openActionMenuId.value = openActionMenuId.value === id ? null : id;
}

function expandRow(id, focus = 'detail') {
    const next = new Set(expandedRows.value);
    next.add(id);
    expandedRows.value = next;
    rowExpandFocus.value = { ...rowExpandFocus.value, [id]: focus };
}

function toggleRow(id) {
    const next = new Set(expandedRows.value);
    if (next.has(id)) {
        next.delete(id);
        const f = { ...rowExpandFocus.value };
        delete f[id];
        rowExpandFocus.value = f;
    } else {
        next.add(id);
        rowExpandFocus.value = { ...rowExpandFocus.value, [id]: 'detail' };
    }
    expandedRows.value = next;
    closeActionMenu();
}

async function openRowComments(b) {
    expandRow(b.id, 'comments');
    closeActionMenu();
    await nextTick();
    document.getElementById(`blocker-comments-${b.id}`)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function runAction(fn) {
    closeActionMenu();
    fn();
}

function blockerComments(b) {
    const raw = b?.comments;
    if (Array.isArray(raw)) return raw;
    if (raw?.data && Array.isArray(raw.data)) return raw.data;
    return [];
}

function normalizeEvidenceLinks(links) {
    const list = Array.isArray(links) ? links : [];
    return list
        .map((item) => ({
            label: (item?.label ?? '').trim(),
            url: (item?.url ?? '').trim(),
        }))
        .filter((item) => item.url);
}

function evidenceLinkLabel(item) {
    return item.label || item.url;
}

function blockerAttachments(b) {
    const raw = b?.attachments;
    if (Array.isArray(raw)) return raw;
    if (raw?.data && Array.isArray(raw.data)) return raw.data;
    return [];
}

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(BLOCKER_FILTER_CONTROLS, 'va-qlda.blockers.visible-filters');

const {
    visibleCols,
    showColDd,
    persistVisibleColumns,
    openColPanel,
    isColVisible,
    TABLE_COLUMNS,
} = useVisibleColumns(BLOCKER_TABLE_COLUMNS, 'va-qlda.blockers.columns');

const focusResolution = ref(false);

const open = (b = null, resolveMode = false) => {
    editing.value = b;
    focusResolution.value = resolveMode;
    modal.value = true;
};

const openResolve = (b) => open(b, true);

function closeModal() {
    modal.value = false;
    focusResolution.value = false;
}

const filterForm = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    severity: props.filters.severity ?? '',
    project_id: props.filters.project_id ?? '',
    owner_id: props.filters.owner_id ?? '',
    raised_by_id: props.filters.raised_by_id ?? '',
    mine: props.filters.mine ? '1' : '',
    overdue: props.filters.overdue ? '1' : '',
});

const appliedFilterCount = computed(() =>
    [
        filterForm.status,
        filterForm.severity,
        filterForm.project_id,
        filterForm.owner_id,
        filterForm.raised_by_id,
        filterForm.mine,
        filterForm.overdue,
    ].filter((v) => v !== '' && v != null).length,
);

/** +1 cột nhóm, +1 chi tiết, +1 thao tác */
const tableColspan = computed(() => TABLE_COLUMNS.filter((c) => isColVisible(c.key)).length + 3);

function sortBlockersByPriority(items) {
    return [...items].sort((a, b) => {
        const ra = SEVERITY_RANK[a.severity?.value] ?? 9;
        const rb = SEVERITY_RANK[b.severity?.value] ?? 9;
        if (ra !== rb) return ra - rb;
        const aOver = a.is_overdue && !isTerminal(a);
        const bOver = b.is_overdue && !isTerminal(b);
        if (aOver !== bOver) return aOver ? -1 : 1;
        return 0;
    });
}

function groupPriorityRank(items) {
    return Math.min(...items.map((b) => SEVERITY_RANK[b.severity?.value] ?? 9));
}

const groupedBlockers = computed(() => {
    const map = new Map();
    for (const b of props.blockers.data ?? []) {
        const key = b.project?.id ?? GROUP_GENERAL;
        if (!map.has(key)) {
            map.set(key, {
                key,
                label: b.project?.name ?? GENERAL_GROUP_LABEL,
                color: b.project?.color ?? null,
                items: [],
            });
        }
        map.get(key).items.push(b);
    }
    for (const g of map.values()) {
        g.items = sortBlockersByPriority(g.items);
    }
    return [...map.values()].sort((a, b) => {
        if (a.key === GROUP_GENERAL) return 1;
        if (b.key === GROUP_GENERAL) return -1;
        const pa = groupPriorityRank(a.items);
        const pb = groupPriorityRank(b.items);
        if (pa !== pb) return pa - pb;
        return a.label.localeCompare(b.label, 'vi');
    });
});

function routeParams() {
    return {
        q: filterForm.q || undefined,
        status: filterForm.status || undefined,
        severity: filterForm.severity || undefined,
        project_id: filterForm.project_id || undefined,
        owner_id: filterForm.owner_id || undefined,
        raised_by_id: filterForm.raised_by_id || undefined,
        mine: filterForm.mine || undefined,
        overdue: filterForm.overdue || undefined,
        per_page: perPage.value,
    };
}

function reloadBlockers() {
    router.get('/blockers', routeParams(), { preserveState: true, replace: true, preserveScroll: true });
}

let qTimer = null;
watch(() => filterForm.q, () => {
    clearTimeout(qTimer);
    qTimer = setTimeout(reloadBlockers, 350);
});

watch(
    () => [
        filterForm.status,
        filterForm.severity,
        filterForm.project_id,
        filterForm.owner_id,
        filterForm.raised_by_id,
        filterForm.mine,
        filterForm.overdue,
    ],
    reloadBlockers,
);

function onPerPageChange(n) {
    perPage.value = n;
    reloadBlockers();
}

function clearFilters() {
    filterForm.q = '';
    filterForm.status = '';
    filterForm.severity = '';
    filterForm.project_id = '';
    filterForm.owner_id = '';
    filterForm.raised_by_id = '';
    filterForm.mine = '';
    filterForm.overdue = '';
    reloadBlockers();
}

function onToolbarClickOutside(e) {
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
    if (colDdRef.value && !colDdRef.value.contains(e.target)) {
        showColDd.value = false;
    }
    if (openActionMenuId.value != null && !e.target.closest('[data-blocker-action-menu]')) {
        closeActionMenu();
    }
}

onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));

function blockerRowClass(b) {
    const expanded = isRowExpanded(b.id);
    const classes = [
        'blocker-data-row',
        'border-b',
        'border-slate-100',
        'bg-white',
        'transition-colors',
    ];
    if (expanded) {
        classes.push('bg-slate-50/80', 'shadow-[inset_0_-1px_0_0_rgb(226_232_240)]');
    }
    if (b.status?.value === 'closed') {
        classes.push('opacity-70', 'bg-slate-50/40');
        return classes;
    }
    if (b.status?.value === 'resolved') {
        classes.push('bg-emerald-50/25');
        return classes;
    }
    const sev = b.severity?.value;
    if (SEVERITY_ACCENT[sev]) {
        classes.push(SEVERITY_ACCENT[sev]);
    }
    if (b.is_overdue) {
        classes.push('ring-1 ring-inset ring-rose-100');
    }
    classes.push('hover:bg-slate-50/90');
    return classes;
}

function isTerminal(b) {
    return TERMINAL_STATUSES.has(b.status?.value);
}

function updateStatus(b, status) {
    if (!b.can?.update || b.status.value === status) return;
    statusUpdating.value.add(b.id);
    router.put(`/blockers/${b.id}`, { status }, {
        preserveScroll: true,
        onFinish: () => statusUpdating.value.delete(b.id),
    });
}

function markResolved(b) {
    updateStatus(b, 'resolved');
}

const remove = async (b) => {
    if (await dialog.confirm({ title: 'Xoá vướng mắc', message: `Xoá "${b.title}"?`, tone: 'danger', confirmText: 'Xoá' }))
        router.delete(`/blockers/${b.id}`, { preserveScroll: true });
};

function personCell(person) {
    if (!person?.name) return null;
    return person;
}

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
    collapsedGroups.value = new Set(groupedBlockers.value.map((g) => g.key));
    persistCollapsedGroups();
}

const allGroupsExpanded = computed(() =>
    groupedBlockers.value.length > 0
    && groupedBlockers.value.every((g) => isGroupExpanded(g.key)),
);

function toggleAllGroups() {
    if (allGroupsExpanded.value) collapseAllGroups();
    else expandAllGroups();
}

</script>

<template>
  <Head title="Vướng mắc" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Vướng mắc cần xử lý"
        subtitle="Theo dõi và giải quyết các vướng mắc trong dự án"
        icon="blockers"
        icon-color="amber"
        :badge="summary.open ?? null"
      />
    </template>

    <div class="mb-4 grid grid-cols-3 gap-3">
      <div class="card p-4">
        <p class="text-xs text-slate-500">
          Đang mở
        </p>
        <p class="mt-1 font-display text-2xl font-bold text-amber-600">
          {{ summary.open ?? 0 }}
        </p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-slate-500">
          Nghiêm trọng
        </p>
        <p class="mt-1 font-display text-2xl font-bold text-rose-600">
          {{ summary.critical ?? 0 }}
        </p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-slate-500">
          Đã giải quyết
        </p>
        <p class="mt-1 font-display text-2xl font-bold text-emerald-600">
          {{ summary.resolved ?? 0 }}
        </p>
      </div>
    </div>

    <div class="card overflow-visible">
      <div class="border-b border-slate-100 px-5 py-3">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
            <DatagridToolbarSearch
              v-model="filterForm.q"
              input-id="blockers-search"
              placeholder="Mã, tiêu đề, mô tả, nguyên nhân…"
            />
            <div
              ref="filterPanelDdRef"
              class="relative shrink-0"
            >
              <button
                type="button"
                class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium transition"
                :class="showFilterPanelDd
                  ? 'border-brand/40 bg-brand/5 text-brand'
                  : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
                @click="openFilterPanel(() => { showColDd = false; })"
              >
                <AppIcon
                  name="filter"
                  :size="15"
                />
                <span>Lọc</span>
              </button>
              <FilterVisibilityDropdown
                v-model="visibleFilters"
                :show="showFilterPanelDd"
                :controls="FILTER_CONTROLS"
                @persist="persistVisibleFilters"
              />
            </div>
            <div
              ref="colDdRef"
              class="relative shrink-0"
            >
              <button
                type="button"
                class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium transition"
                :class="showColDd
                  ? 'border-brand/40 bg-brand/5 text-brand'
                  : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                title="Cột hiển thị"
                @click="openColPanel(() => { showFilterPanelDd = false; })"
              >
                <AppIcon
                  name="columns"
                  :size="15"
                />
                <span>Cột</span>
              </button>
              <ColumnVisibilityDropdown
                v-model="visibleCols"
                :show="showColDd"
                :columns="TABLE_COLUMNS"
                :fixed-labels="['Trạng thái / Xử lý', 'Chi tiết']"
                @persist="persistVisibleColumns"
              />
            </div>
            <button
              v-if="groupedBlockers.length"
              type="button"
              class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600 hover:border-slate-300"
              :title="allGroupsExpanded ? 'Thu gọn tất cả nhóm dự án' : 'Mở tất cả nhóm dự án'"
              @click="toggleAllGroups"
            >
              <AppIcon
                name="chevron-down"
                :size="15"
                class="transition-transform"
                :class="allGroupsExpanded ? '' : '-rotate-90'"
              />
              <span class="hidden sm:inline">{{ allGroupsExpanded ? 'Thu nhóm' : 'Mở nhóm' }}</span>
            </button>
          </div>
          <button
            v-if="can.create"
            type="button"
            class="btn-primary h-9 shrink-0 gap-1.5 px-4 text-sm"
            @click="open()"
          >
            <AppIcon
              name="add"
              :size="15"
            />
            Ghi nhận vướng mắc
          </button>
        </div>
      </div>

      <div
        v-if="hasFilterRow"
        class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-3"
      >
        <select
          v-if="visibleFilters.status"
          v-model="filterForm.status"
          class="input h-9 min-w-[13rem] max-w-xs text-sm"
          aria-label="Lọc trạng thái"
        >
          <optgroup label="Phạm vi danh sách">
            <option
              v-for="o in STATUS_SCOPE_OPTIONS"
              :key="`scope-${o.value}`"
              :value="o.value"
            >
              {{ o.label }}
            </option>
          </optgroup>
          <optgroup label="Theo trạng thái cụ thể">
            <option
              v-for="o in options.status"
              :key="o.value"
              :value="o.value"
            >
              {{ o.label }}
            </option>
          </optgroup>
        </select>
        <select
          v-if="visibleFilters.severity"
          v-model="filterForm.severity"
          class="input h-9 w-40 text-sm"
          aria-label="Mức độ"
        >
          <option value="">
            Mức độ: Tất cả
          </option>
          <option
            v-for="o in options.severity"
            :key="o.value"
            :value="o.value"
          >
            {{ o.label }}
          </option>
        </select>
        <select
          v-if="visibleFilters.project"
          v-model="filterForm.project_id"
          class="input h-9 min-w-[11rem] text-sm sm:w-52"
          aria-label="Dự án"
        >
          <option value="">
            Dự án: Tất cả
          </option>
          <option
            v-for="p in options.projects"
            :key="p.id"
            :value="p.id"
          >
            {{ p.name }}
          </option>
        </select>
        <select
          v-if="visibleFilters.owner"
          v-model="filterForm.owner_id"
          class="input h-9 min-w-[10rem] text-sm sm:w-48"
          aria-label="Người xử lý"
        >
          <option value="">
            Người xử lý: Tất cả
          </option>
          <option
            v-for="e in options.employees"
            :key="e.id"
            :value="e.id"
          >
            {{ e.name }}
          </option>
        </select>
        <select
          v-if="visibleFilters.raised_by"
          v-model="filterForm.raised_by_id"
          class="input h-9 min-w-[10rem] text-sm sm:w-48"
          aria-label="Người báo"
        >
          <option value="">
            Người báo: Tất cả
          </option>
          <option
            v-for="e in options.employees"
            :key="`r-${e.id}`"
            :value="e.id"
          >
            {{ e.name }}
          </option>
        </select>
        <label
          v-if="visibleFilters.overdue"
          class="inline-flex h-9 items-center gap-2 rounded-btn border border-slate-200 bg-white px-3 text-sm text-slate-600"
        >
          <input
            v-model="filterForm.overdue"
            true-value="1"
            false-value=""
            type="checkbox"
            class="rounded border-slate-300 text-brand"
          >
          Chỉ quá hạn
        </label>
        <label
          v-if="visibleFilters.mine"
          class="inline-flex h-9 items-center gap-2 rounded-btn border border-slate-200 bg-white px-3 text-sm text-slate-600"
        >
          <input
            v-model="filterForm.mine"
            true-value="1"
            false-value=""
            type="checkbox"
            class="rounded border-slate-300 text-brand"
          >
          Tôi xử lý
        </label>
        <button
          v-if="appliedFilterCount || filterForm.q"
          type="button"
          class="text-xs font-medium text-brand hover:underline"
          @click="clearFilters"
        >
          Đặt lại
        </button>
      </div>

      <div
        v-if="groupedBlockers.length"
        class="blocker-table-wrap overflow-x-auto"
      >
        <table class="blocker-table w-full min-w-[42rem] border-collapse text-sm">
          <colgroup>
            <col class="w-9">
            <col
              v-for="c in TABLE_COLUMNS.filter((col) => isColVisible(col.key))"
              :key="c.key"
            >
            <col class="w-11">
          </colgroup>
          <thead class="sticky top-0 z-[1] border-b border-slate-200 bg-slate-50/95 text-[10px] font-semibold uppercase tracking-wide text-slate-500 backdrop-blur-sm">
            <tr>
              <th
                class="w-9 px-1 py-2 align-middle"
                aria-hidden="true"
              />
              <th
                v-if="isColVisible('code')"
                class="px-2 py-2 text-left align-middle"
              >
                Mã
              </th>
              <th
                v-if="isColVisible('title')"
                class="px-2 py-2 text-left align-middle"
              >
                Vướng mắc
              </th>
              <th
                v-if="isColVisible('task')"
                class="px-2 py-2 text-left align-middle"
              >
                CV
              </th>
              <th
                v-if="isColVisible('severity')"
                class="px-2 py-2 text-left align-middle"
              >
                Mức
              </th>
              <th
                v-if="isColVisible('status')"
                class="px-2 py-2 text-left align-middle blocker-col-status"
              >
                TT
              </th>
              <th
                v-if="isColVisible('raised_by')"
                class="px-2 py-2 text-left align-middle blocker-col-person"
              >
                Người báo
              </th>
              <th
                v-if="isColVisible('owner')"
                class="px-2 py-2 text-left align-middle blocker-col-person"
              >
                Xử lý
              </th>
              <th
                v-if="isColVisible('raised_at')"
                class="px-2 py-2 text-left align-middle"
              >
                Báo
              </th>
              <th
                v-if="isColVisible('due_date')"
                class="px-2 py-2 text-left align-middle"
              >
                Hạn
              </th>
              <th
                v-if="isColVisible('resolved_at')"
                class="px-2 py-2 text-left align-middle"
              >
                Xong
              </th>
              <th
                v-if="isColVisible('comments')"
                class="px-2 py-2 text-center align-middle"
              >
                BL
              </th>
              <th
                v-if="isColVisible('description')"
                class="px-2 py-2 text-left align-middle"
              >
                Mô tả
              </th>
              <th
                v-if="isColVisible('root_cause')"
                class="px-2 py-2 text-left align-middle"
              >
                NN
              </th>
              <th
                class="w-11 px-1 py-2 text-center align-middle"
                aria-label="Chi tiết"
              >
                <span class="sr-only">Chi tiết</span>
              </th>
              <th class="w-[5.75rem] px-1 py-2 text-center align-middle text-xs font-medium text-slate-500">
                Thao tác
              </th>
            </tr>
          </thead>
          <tbody>
            <template
              v-for="group in groupedBlockers"
              :key="group.key"
            >
              <tr
                class="blocker-group-row cursor-pointer border-y border-slate-200 bg-slate-100/70 transition hover:bg-slate-100"
                @click="toggleGroup(group.key)"
              >
                <td class="px-1 py-2 text-center align-middle">
                  <AppIcon
                    name="chevron-down"
                    :size="15"
                    class="inline-block text-slate-500 transition-transform"
                    :class="isGroupExpanded(group.key) ? '' : '-rotate-90'"
                  />
                </td>
                <td
                  :colspan="tableColspan - 1"
                  class="px-2 py-2 align-middle"
                >
                  <div class="flex items-center gap-2">
                    <span
                      class="h-2 w-2 shrink-0 rounded-full ring-2 ring-white"
                      :class="group.key === GROUP_GENERAL ? 'bg-slate-400' : ''"
                      :style="group.color ? { backgroundColor: group.color } : undefined"
                    />
                    <span class="min-w-0 flex-1 break-words text-sm font-semibold text-slate-800">{{ group.label }}</span>
                    <span class="shrink-0 rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold tabular-nums text-slate-600 ring-1 ring-slate-200/90">
                      {{ group.items.length }}
                    </span>
                  </div>
                </td>
              </tr>
              <template v-if="isGroupExpanded(group.key)">
                <template
                  v-for="b in group.items"
                  :key="b.id"
                >
                  <tr :class="blockerRowClass(b)">
                    <td class="px-1 py-2 align-middle">
                      <span
                        class="mx-auto block h-6 w-1 rounded-full bg-slate-200/80"
                        aria-hidden="true"
                      />
                    </td>
                    <td
                      v-if="isColVisible('code')"
                      class="blocker-cell-wrap px-2 py-2.5 align-top"
                    >
                      <span class="font-mono text-xs font-semibold text-brand">{{ b.code }}</span>
                    </td>
                    <td
                      v-if="isColVisible('title')"
                      class="blocker-cell-wrap blocker-col-title px-2 py-2.5 align-top"
                    >
                      <div class="min-w-0">
                        <p class="break-words text-sm font-medium leading-snug text-slate-800">
                          {{ b.title }}
                        </p>
                        <p
                          v-if="!isColVisible('code')"
                          class="mt-0.5 break-all font-mono text-[11px] text-brand/90"
                        >
                          {{ b.code }}
                        </p>
                        <p
                          v-if="b.is_overdue && !isTerminal(b)"
                          class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-rose-600"
                        >
                          Quá hạn
                        </p>
                      </div>
                    </td>
                    <td
                      v-if="isColVisible('task')"
                      class="blocker-cell-wrap px-2 py-2.5 align-top text-xs text-slate-600"
                    >
                      <span class="block break-words whitespace-pre-wrap">{{ b.task?.title ?? '—' }}</span>
                    </td>
                    <td
                      v-if="isColVisible('severity')"
                      class="px-2 py-2.5 align-top"
                    >
                      <Badge
                        :label="b.severity.label"
                        :color="b.severity.color"
                      />
                    </td>
                    <td
                      v-if="isColVisible('status')"
                      class="blocker-col-status px-2 py-2.5 align-top"
                    >
                      <div
                        v-if="b.can?.update"
                        class="flex min-w-0 items-center gap-1"
                      >
                        <select
                          :value="b.status.value"
                          class="input h-7 min-w-0 flex-1 px-2 py-0 text-xs"
                          :disabled="statusUpdating.has(b.id)"
                          :class="isTerminal(b) ? 'text-slate-500' : ''"
                          aria-label="Trạng thái"
                          @click.stop
                          @change="updateStatus(b, $event.target.value)"
                        >
                          <option
                            v-for="o in options.status"
                            :key="o.value"
                            :value="o.value"
                          >
                            {{ o.label }}
                          </option>
                        </select>
                        <button
                          v-if="!isTerminal(b)"
                          type="button"
                          class="grid h-7 w-7 shrink-0 place-items-center rounded-md border border-emerald-200/90 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 disabled:opacity-50"
                          title="Đánh dấu đã xử lý"
                          :disabled="statusUpdating.has(b.id)"
                          @click.stop="markResolved(b)"
                        >
                          <AppIcon
                            name="done"
                            :size="14"
                          />
                        </button>
                      </div>
                      <Badge
                        v-else
                        :label="b.status.label"
                        :color="b.status.color"
                      />
                    </td>
                    <td
                      v-if="isColVisible('raised_by')"
                      class="blocker-col-person blocker-cell-wrap px-2 py-2.5 align-top"
                    >
                      <div
                        v-if="personCell(b.raised_by)"
                        class="blocker-person flex min-w-0 items-start gap-1.5"
                      >
                        <Avatar
                          :name="b.raised_by.name"
                          :src="b.raised_by.avatar_path"
                          :size="22"
                          class="shrink-0"
                        />
                        <span class="min-w-0 break-words text-xs text-slate-700">{{ b.raised_by.name }}</span>
                      </div>
                      <span
                        v-else
                        class="text-xs text-slate-300"
                      >—</span>
                    </td>
                    <td
                      v-if="isColVisible('owner')"
                      class="blocker-col-person blocker-cell-wrap px-2 py-2.5 align-top"
                    >
                      <div
                        v-if="personCell(b.owner)"
                        class="blocker-person flex min-w-0 items-start gap-1.5"
                      >
                        <Avatar
                          :name="b.owner.name"
                          :src="b.owner.avatar_path"
                          :size="22"
                          class="shrink-0"
                        />
                        <span class="min-w-0 break-words text-xs text-slate-700">{{ b.owner.name }}</span>
                      </div>
                      <span
                        v-else
                        class="text-xs text-slate-300"
                      >—</span>
                    </td>
                    <td
                      v-if="isColVisible('raised_at')"
                      class="whitespace-nowrap px-2 py-2.5 align-top text-xs tabular-nums text-slate-500"
                    >
                      {{ b.raised_at ? date(b.raised_at) : '—' }}
                    </td>
                    <td
                      v-if="isColVisible('due_date')"
                      class="whitespace-nowrap px-2 py-2.5 align-top text-xs tabular-nums"
                      :class="b.is_overdue && !isTerminal(b) ? 'font-semibold text-rose-600' : 'text-slate-500'"
                    >
                      {{ b.due_date ? date(b.due_date) : '—' }}
                    </td>
                    <td
                      v-if="isColVisible('resolved_at')"
                      class="whitespace-nowrap px-2 py-2.5 align-top text-xs tabular-nums text-slate-500"
                    >
                      {{ b.resolved_at ? datetime(b.resolved_at) : '—' }}
                    </td>
                    <td
                      v-if="isColVisible('comments')"
                      class="px-2 py-2.5 text-center align-top text-xs tabular-nums"
                    >
                      <button
                        type="button"
                        class="tabular-nums text-slate-600 underline-offset-2 hover:text-brand hover:underline"
                        :title="(b.comments_count ?? 0) > 0 ? 'Xem bình luận' : 'Thêm bình luận'"
                        @click.stop="openRowComments(b)"
                      >
                        {{ b.comments_count ?? 0 }}
                      </button>
                    </td>
                    <td
                      v-if="isColVisible('description')"
                      class="blocker-cell-wrap px-2 py-2.5 align-top text-xs text-slate-600"
                    >
                      <span class="block break-words whitespace-pre-wrap">{{ b.description?.trim() || '—' }}</span>
                    </td>
                    <td
                      v-if="isColVisible('root_cause')"
                      class="blocker-cell-wrap px-2 py-2.5 align-top text-xs text-slate-600"
                    >
                      <span class="block break-words whitespace-pre-wrap">{{ b.root_cause?.trim() || '—' }}</span>
                    </td>
                    <td class="px-1 py-2 align-top">
                      <button
                        type="button"
                        class="mx-auto grid h-8 w-8 place-items-center rounded-lg border border-transparent text-slate-400 transition hover:border-slate-200 hover:bg-white hover:text-slate-700"
                        :class="isRowExpanded(b.id) ? 'border-slate-200 bg-white text-brand shadow-sm' : ''"
                        :aria-expanded="isRowExpanded(b.id)"
                        :title="isRowExpanded(b.id) ? 'Thu gọn chi tiết' : 'Xem chi tiết'"
                        @click.stop="toggleRow(b.id)"
                      >
                        <AppIcon
                          name="chevron-down"
                          :size="15"
                          class="transition-transform"
                          :class="isRowExpanded(b.id) ? 'rotate-180' : ''"
                        />
                      </button>
                    </td>
                    <td
                      class="relative px-1 py-2 align-top"
                      data-blocker-action-menu
                    >
                      <div class="relative flex justify-center">
                        <button
                          type="button"
                          class="inline-flex h-8 max-w-full items-center gap-0.5 rounded-lg border border-slate-200 bg-white px-1.5 text-[11px] font-medium text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
                          :class="openActionMenuId === b.id ? 'border-brand/30 text-brand' : ''"
                          :aria-expanded="openActionMenuId === b.id"
                          aria-haspopup="menu"
                          :aria-label="`Thao tác ${b.code}`"
                          @click.stop="toggleActionMenu(b.id)"
                        >
                          <span class="hidden min-[420px]:inline">Thao tác</span>
                          <AppIcon
                            name="chevron-down"
                            :size="13"
                            class="shrink-0 transition"
                            :class="openActionMenuId === b.id ? 'rotate-180' : ''"
                          />
                        </button>
                        <transition
                          enter-active-class="transition duration-150 ease-out"
                          enter-from-class="translate-y-1 opacity-0"
                          enter-to-class="translate-y-0 opacity-100"
                          leave-active-class="transition duration-100 ease-in"
                          leave-from-class="translate-y-0 opacity-100"
                          leave-to-class="translate-y-1 opacity-0"
                        >
                          <div
                            v-if="openActionMenuId === b.id"
                            class="absolute right-0 top-full z-30 mt-1 w-48 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
                            role="menu"
                            :aria-label="`Menu thao tác ${b.code}`"
                          >
                            <button
                              type="button"
                              role="menuitem"
                              class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                              @click.stop="toggleRow(b.id)"
                            >
                              <AppIcon
                                name="chevron-down"
                                :size="14"
                                class="shrink-0"
                                :class="isRowExpanded(b.id) ? 'rotate-180' : ''"
                              />
                              {{ isRowExpanded(b.id) ? 'Thu gọn chi tiết' : 'Xem chi tiết' }}
                            </button>
                            <button
                              type="button"
                              role="menuitem"
                              class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                              @click.stop="openRowComments(b)"
                            >
                              <AppIcon
                                name="comment"
                                :size="14"
                                class="shrink-0"
                              />
                              Bình luận
                              <span class="ml-auto tabular-nums text-xs text-slate-400">{{ b.comments_count ?? 0 }}</span>
                            </button>
                            <button
                              v-if="b.can?.update && !isTerminal(b)"
                              type="button"
                              role="menuitem"
                              class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-emerald-800 transition hover:bg-emerald-50"
                              @click.stop="runAction(() => openResolve(b))"
                            >
                              <AppIcon
                                name="meeting-notes"
                                :size="14"
                                class="shrink-0"
                              />
                              Hướng xử lý
                            </button>
                            <button
                              v-if="b.can?.update"
                              type="button"
                              role="menuitem"
                              class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                              @click.stop="runAction(() => open(b))"
                            >
                              <AppIcon
                                name="edit"
                                :size="14"
                                class="shrink-0"
                              />
                              Sửa
                            </button>
                            <button
                              v-if="b.can?.delete"
                              type="button"
                              role="menuitem"
                              class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-rose-700 transition hover:bg-rose-50"
                              @click.stop="runAction(() => remove(b))"
                            >
                              <AppIcon
                                name="delete"
                                :size="14"
                                class="shrink-0"
                              />
                              Xoá
                            </button>
                          </div>
                        </transition>
                      </div>
                    </td>
                  </tr>
                  <tr
                    v-if="isRowExpanded(b.id)"
                    class="blocker-detail-row border-b border-slate-100"
                  >
                    <td
                      :colspan="tableColspan"
                      class="bg-slate-50/90 px-3 py-3 align-top sm:px-5 sm:py-4"
                    >
                      <details
                        class="group rounded-xl border border-slate-200/90 bg-white shadow-sm"
                        open
                      >
                        <summary class="cursor-pointer list-none px-4 py-3 text-sm font-medium text-slate-700 marker:content-none [&::-webkit-details-marker]:hidden">
                          <span class="inline-flex items-center gap-2">
                            <AppIcon
                              name="chevron-down"
                              :size="14"
                              class="text-slate-400 transition group-open:rotate-180"
                            />
                            Nội dung chi tiết
                          </span>
                        </summary>
                        <section class="border-t border-slate-100 p-4 pt-3">
                          <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-1 border-b border-slate-100 pb-3 text-xs text-slate-500">
                            <span class="font-mono font-semibold text-brand">{{ b.code }}</span>
                            <span v-if="b.raised_at">Báo {{ date(b.raised_at) }}</span>
                            <span
                              :class="b.is_overdue && !isTerminal(b) ? 'font-semibold text-rose-600' : ''"
                            >
                              Hạn {{ b.due_date ? date(b.due_date) : '—' }}
                            </span>
                            <span v-if="b.resolved_at">Xong {{ datetime(b.resolved_at) }}</span>
                            <span>{{ b.comments_count ?? 0 }} bình luận</span>
                            <span v-if="b.updated_at">Cập nhật {{ datetime(b.updated_at) }}</span>
                          </div>
                          <dl class="grid gap-4 text-sm sm:grid-cols-2">
                            <div class="sm:col-span-2">
                              <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                Mô tả
                              </dt>
                              <dd class="mt-1 break-words whitespace-pre-wrap text-slate-700">
                                {{ b.description?.trim() || '—' }}
                              </dd>
                            </div>
                            <div class="sm:col-span-2">
                              <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                Nguyên nhân
                              </dt>
                              <dd class="mt-1 break-words whitespace-pre-wrap text-slate-700">
                                {{ b.root_cause?.trim() || '—' }}
                              </dd>
                            </div>
                            <div class="sm:col-span-2">
                              <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                Hướng xử lý
                              </dt>
                              <dd class="mt-1 break-words whitespace-pre-wrap text-slate-700">
                                {{ b.resolution?.trim() || '—' }}
                              </dd>
                            </div>
                            <div
                              v-if="b.task?.title"
                              class="sm:col-span-2"
                            >
                              <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                Công việc liên quan
                              </dt>
                              <dd class="mt-1 break-words text-slate-700">
                                {{ b.task.title }}
                              </dd>
                            </div>
                            <div
                              v-if="normalizeEvidenceLinks(b.evidence_links).length"
                              class="sm:col-span-2"
                            >
                              <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                Link dẫn chứng
                              </dt>
                              <dd class="mt-1">
                                <ul class="space-y-1.5">
                                  <li
                                    v-for="(link, linkIdx) in normalizeEvidenceLinks(b.evidence_links)"
                                    :key="linkIdx"
                                  >
                                    <a
                                      :href="link.url"
                                      target="_blank"
                                      rel="noopener noreferrer"
                                      class="inline-flex max-w-full items-start gap-1.5 text-sm font-medium text-brand hover:underline"
                                      @click.stop
                                    >
                                      <AppIcon
                                        name="dependency"
                                        :size="14"
                                        class="mt-0.5 shrink-0"
                                      />
                                      <span class="min-w-0 break-all">{{ evidenceLinkLabel(link) }}</span>
                                    </a>
                                  </li>
                                </ul>
                              </dd>
                            </div>
                            <div
                              v-if="blockerAttachments(b).length"
                              class="sm:col-span-2"
                            >
                              <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                Ảnh &amp; file đính kèm
                              </dt>
                              <dd class="mt-2">
                                <BlockerAttachmentsBlock
                                  :blocker-id="b.id"
                                  :attachments="blockerAttachments(b)"
                                  :can-upload="false"
                                  compact
                                />
                              </dd>
                            </div>
                          </dl>
                        </section>
                      </details>
                      <section
                        :id="`blocker-comments-${b.id}`"
                        class="mt-3 rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm"
                      >
                        <CommentThread
                          :comments="blockerComments(b)"
                          commentable-type="blocker"
                          :commentable-id="b.id"
                          :can-comment="can.comment"
                        />
                      </section>
                    </td>
                  </tr>
                </template>
              </template>
            </template>
          </tbody>
        </table>
      </div>

      <div
        v-else
        class="px-4 py-14 text-center text-sm text-slate-400"
      >
        Không có vướng mắc phù hợp bộ lọc.
      </div>

      <DatagridPaginationFooter
        variant="bar"
        :meta="blockers.meta"
        :per-page="perPage"
        :per-page-options="PER_PAGE_OPTIONS"
        @update:per-page="onPerPageChange"
      />
    </div>

    <BlockerFormModal
      :show="modal"
      :blocker="editing"
      :focus-resolution="focusResolution"
      :projects="options.projects"
      :employees="options.employees"
      :severity-options="options.severity"
      :status-options="options.status"
      :can-upload-attachments="can.create || editing?.can?.update"
      @close="closeModal"
      @saved="closeModal"
    />
  </AppLayout>
</template>

<style scoped>
.blocker-table-wrap {
    -webkit-overflow-scrolling: touch;
}
.blocker-table {
    table-layout: auto;
}
.blocker-table th,
.blocker-table td {
    overflow: visible;
    vertical-align: top;
}
.blocker-cell-wrap {
    min-width: 6rem;
    max-width: 28rem;
    word-break: break-word;
}
.blocker-col-title {
    min-width: 10rem;
    max-width: 22rem;
}
.blocker-col-status {
    min-width: 7.5rem;
    max-width: 11rem;
}
.blocker-col-person {
    min-width: 7rem;
    max-width: 12rem;
}
.blocker-detail-row td {
    overflow: visible;
    max-width: none;
}
</style>
