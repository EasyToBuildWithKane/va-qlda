<script setup>
import { reactive, ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import Badge from '@/shared/ui/Badge.vue';
import BlockerFormModal from '@/modules/project/components/BlockerFormModal.vue';
import BlockerDetailModal from '@/modules/project/components/BlockerDetailModal.vue';
import BlockerCommentModal from '@/modules/project/components/BlockerCommentModal.vue';
import BlockerRowActions from '@/modules/project/components/BlockerRowActions.vue';
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
const detailModalBlocker = ref(null);
const detailModalTab = ref('detail');
const showDetailModal = ref(false);
const commentModalBlocker = ref(null);
const showCommentModal = ref(false);

function openDetailModal(b, tab = 'detail') {
    detailModalBlocker.value = b;
    detailModalTab.value = tab;
    showDetailModal.value = true;
}

function closeDetailModal() {
    showDetailModal.value = false;
    detailModalBlocker.value = null;
}

function openCommentModal(b) {
    commentModalBlocker.value = b;
    showCommentModal.value = true;
}

function closeCommentModal() {
    showCommentModal.value = false;
    commentModalBlocker.value = null;
}

function onDetailEditResolution(b) {
    closeDetailModal();
    openResolve(b);
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
}

onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));

function blockerRowClass(b) {
    const classes = [
        'blocker-data-row',
        'border-b',
        'border-slate-100',
        'bg-white',
        'transition-colors',
    ];
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
              <th
                class="w-11 px-1 py-2 text-center align-middle"
                aria-label="Thao tác"
              >
                <span class="sr-only">Thao tác</span>
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
                        @click.stop="openCommentModal(b)"
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
                        class="mx-auto grid h-8 w-8 place-items-center rounded-lg border border-transparent text-slate-400 transition hover:border-slate-200 hover:bg-white hover:text-brand"
                        title="Xem chi tiết"
                        @click.stop="openDetailModal(b, 'detail')"
                      >
                        <AppIcon
                          name="eye"
                          :size="16"
                        />
                      </button>
                    </td>
                    <td class="px-1 py-2 align-top">
                      <BlockerRowActions
                        :blocker="b"
                        @detail="openDetailModal($event, 'detail')"
                        @comment="openCommentModal"
                        @resolve="openResolve"
                        @edit="open"
                        @delete="remove"
                      />
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

    <BlockerDetailModal
      :show="showDetailModal"
      :blocker="detailModalBlocker"
      :initial-tab="detailModalTab"
      :can-comment="can.comment"
      :can-update="detailModalBlocker?.can?.update && !isTerminal(detailModalBlocker)"
      @close="closeDetailModal"
      @edit-resolution="onDetailEditResolution"
    />

    <BlockerCommentModal
      :show="showCommentModal"
      :blocker="commentModalBlocker"
      :can-comment="can.comment"
      @close="closeCommentModal"
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
</style>
