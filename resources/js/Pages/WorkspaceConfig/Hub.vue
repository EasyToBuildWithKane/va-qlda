<script setup>
import {
    computed, onBeforeUnmount, onMounted, reactive, ref, watch,
} from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import { useClientPagination } from '@/shared/composables/useClientPagination';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { useDialog } from '@/composables/useDialog';
import WorkspaceProfileGrid from '@/modules/workspace-config/components/WorkspaceProfileGrid.vue';
import WorkspaceInsightsBanner from '@/modules/workspace-config/components/WorkspaceInsightsBanner.vue';
import WorkspaceProfileDrawer from '@/modules/workspace-config/components/WorkspaceProfileDrawer.vue';
import { exportWorkspaceHubWorkbook } from '@/modules/workspace-config/composables/useWorkspaceHubExport';

const SORT_STORAGE = 'va-workspace.workspace-config.sort';

const HUB_FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái', default: false },
    { key: 'readiness', label: 'Mức sẵn sàng', default: false },
    { key: 'sort', label: 'Sắp xếp', default: false },
];

const HUB_CARD_COLUMNS = [
    { key: 'criteria', label: 'Tiêu chí PB', default: true },
    { key: 'modules', label: 'Module', default: true },
    { key: 'readiness', label: 'Sẵn sàng %', default: true },
    { key: 'readiness_badge', label: 'Nhãn sẵn sàng', default: false },
    { key: 'source', label: 'Nguồn', default: false },
    { key: 'updated', label: 'Ngày cập nhật', default: false },
    { key: 'progress', label: 'Thanh tiến độ + module', default: false },
];

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const props = defineProps({
    workspaces: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({ total: 0 }) },
    insights: { type: Array, default: () => [] },
    coverage: { type: Object, default: () => ({ modules: [], rows: [] }) },
    filters: { type: Object, default: () => ({ include_archived: false }) },
    viewer: {
        type: Object,
        default: () => ({
            can_manage: false,
            own_department_code: null,
            own_department_name: null,
            is_super_admin: false,
        }),
    },
    statusOptions: { type: Array, default: () => [] },
    readinessOptions: { type: Array, default: () => [] },
});

const dialog = useDialog();
const search = ref('');
const sortBy = ref('status');
const selectedCodes = ref([]);
const previewCode = ref(null);
const bulkLoading = ref(false);
const localFilters = reactive({
    status: '',
    readiness: '',
});

const filterPanelDdRef = ref(null);
const colDdRef = ref(null);

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(HUB_FILTER_CONTROLS, 'va-workspace.workspace-config.visible-filters.v1');

const {
    visibleCols,
    showColDd,
    persistVisibleColumns,
    openColPanel,
    TABLE_COLUMNS,
} = useVisibleColumns(HUB_CARD_COLUMNS, 'va-workspace.workspace-config.columns.v1');

const statusFilterOptions = computed(() => {
    const base = [
        { value: '', label: 'Trạng thái' },
        { value: 'active', label: 'Đang dùng' },
        { value: 'draft', label: 'Nháp' },
    ];
    if (props.viewer.can_manage) {
        base.push({ value: 'missing', label: 'Chưa kích hoạt' });
        if (props.filters.include_archived) {
            base.push({ value: 'archived', label: 'Lưu trữ' });
        }
    }
    return base;
});

const readinessFilterOptions = [
    { value: '', label: 'Mức sẵn sàng' },
    { value: 'ready', label: 'Sẵn sàng' },
    { value: 'partial', label: 'Đang cấu hình' },
    { value: 'empty', label: 'Trống' },
];

const sortOptions = [
    { value: 'status', label: 'Theo trạng thái' },
    { value: 'readiness', label: 'Theo sẵn sàng' },
    { value: 'name', label: 'Tên A–Z' },
    { value: 'criteria', label: 'Theo tiêu chí' },
];

const filteredWorkspaces = computed(() => {
    const q = search.value.trim().toLowerCase();
    let list = props.workspaces.filter((ws) => {
        if (localFilters.status && ws.status !== localFilters.status) {
            return false;
        }
        if (localFilters.readiness && (ws.readiness?.key ?? 'empty') !== localFilters.readiness) {
            return false;
        }
        if (!q) return true;
        return ws.department_name.toLowerCase().includes(q)
            || ws.department_code.toLowerCase().includes(q);
    });

    const statusRank = (s) => ({ active: 0, draft: 1, missing: 2, archived: 3 }[s] ?? 4);
    const readyRank = (s) => ({ ready: 0, partial: 1, empty: 2 }[s] ?? 3);

    const mineRank = (ws) => (ws.is_mine ? 0 : 1);

    list = [...list].sort((a, b) => {
        if (sortBy.value === 'name') {
            return mineRank(a) - mineRank(b)
                || a.department_name.localeCompare(b.department_name, 'vi');
        }
        if (sortBy.value === 'criteria') {
            return mineRank(a) - mineRank(b)
                || (b.criteria_count - a.criteria_count)
                || a.department_name.localeCompare(b.department_name, 'vi');
        }
        if (sortBy.value === 'readiness') {
            return mineRank(a) - mineRank(b)
                || (readyRank(a.readiness?.key) - readyRank(b.readiness?.key))
                || (b.readiness?.percent - a.readiness?.percent)
                || a.department_name.localeCompare(b.department_name, 'vi');
        }
        return mineRank(a) - mineRank(b)
            || (statusRank(a.status) - statusRank(b.status))
            || (readyRank(a.readiness?.key) - readyRank(b.readiness?.key))
            || a.department_name.localeCompare(b.department_name, 'vi');
    });

    return list;
});

const {
    perPage,
    paginatedItems,
    meta: paginationMeta,
    setPerPage,
    goToPage,
    PER_PAGE_OPTIONS,
} = useClientPagination(filteredWorkspaces, 'va-workspace.workspace-config.perPage', 10);

watch(filteredWorkspaces, () => {
    goToPage(1);
});

const previewWorkspace = computed(() => {
    if (!previewCode.value) return null;
    return props.workspaces.find((w) => w.department_code === previewCode.value) ?? null;
});

const selectedEnsureable = computed(() => selectedCodes.value.filter((code) => {
    const ws = props.workspaces.find((w) => w.department_code === code);
    return ws?.can_ensure;
}));

function setSortBy(mode) {
    sortBy.value = mode;
    try { localStorage.setItem(SORT_STORAGE, mode); } catch { /* ignore */ }
}

function toggleSelect(code) {
    const set = new Set(selectedCodes.value);
    if (set.has(code)) set.delete(code);
    else set.add(code);
    selectedCodes.value = [...set];
}

function clearSelection() {
    selectedCodes.value = [];
}

function openPreview(ws) {
    previewCode.value = typeof ws === 'string' ? ws : ws.department_code;
}

async function bulkEnsure(codes) {
    const list = (codes ?? []).filter(Boolean);
    if (!list.length || !props.viewer.can_manage) return;

    const ok = await dialog.confirm({
        title: 'Kích hoạt workspace hàng loạt?',
        message: `Sẽ kích hoạt ${list.length} phòng ban đã chọn.`,
        confirmText: 'Kích hoạt',
        cancelText: 'Huỷ',
    });
    if (!ok) return;

    bulkLoading.value = true;
    router.post('/workspace-config/ensure-bulk', { codes: list }, {
        preserveScroll: true,
        onFinish: () => {
            bulkLoading.value = false;
            clearSelection();
        },
    });
}

function onInsightAction(insight) {
    if (insight.action === 'bulk_ensure') {
        bulkEnsure(insight.department_codes ?? []);
        return;
    }
    if (insight.action === 'filter_empty_active') {
        localFilters.status = 'active';
        localFilters.readiness = 'empty';
        visibleFilters.value = { ...visibleFilters.value, status: true, readiness: true };
        persistVisibleFilters();
        return;
    }
    if (insight.action === 'filter_partial') {
        localFilters.status = '';
        localFilters.readiness = 'partial';
        visibleFilters.value = { ...visibleFilters.value, readiness: true };
        persistVisibleFilters();
    }
}

function exportHub() {
    exportWorkspaceHubWorkbook({
        workspaces: filteredWorkspaces.value,
        summary: props.summary,
        coverage: {
            modules: props.coverage?.modules ?? [],
            rows: (props.coverage?.rows ?? []).filter((row) => filteredWorkspaces.value
                .some((w) => w.department_code === row.department_code)),
        },
    });
}

function openFilterPanelSafe() {
    openFilterPanel(() => {
        showColDd.value = false;
    });
}

function openColPanelSafe() {
    openColPanel(() => {
        showFilterPanelDd.value = false;
    });
}

function onToolbarClickOutside(e) {
    const t = e.target;
    if (t.closest?.('[data-filter-visibility-panel]')) return;
    if (t.closest?.('[data-column-visibility-panel]')) return;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(t)) {
        showFilterPanelDd.value = false;
    }
    if (colDdRef.value && !colDdRef.value.contains(t)) {
        showColDd.value = false;
    }
}

onMounted(() => {
    try {
        const savedSort = localStorage.getItem(SORT_STORAGE);
        if (sortOptions.some((t) => t.value === savedSort)) sortBy.value = savedSort;
    } catch { /* ignore */ }
    document.addEventListener('mousedown', onToolbarClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onToolbarClickOutside);
});

const ownDepartmentLabel = computed(() => {
    const name = (props.viewer.own_department_name || '').trim();
    const code = (props.viewer.own_department_code || '').trim();
    if (name && code) return `${name} (${code})`;
    return name || code || '';
});

const headerSubtitle = computed(() => {
    if (ownDepartmentLabel.value) {
        return `Phòng ban của bạn: ${ownDepartmentLabel.value}`;
    }
    if (props.viewer.can_manage) {
        return 'Bạn có quyền xem mọi phòng ban';
    }
    return 'Chưa xác định phòng ban từ HRM hoặc danh mục nội bộ';
});

const emptyHint = computed(() => {
    if (!props.viewer.can_manage && !ownDepartmentLabel.value) {
        return 'Tài khoản chưa gắn phòng ban HRM. Liên hệ quản trị để cập nhật hồ sơ.';
    }
    if (props.workspaces.length === 0) {
        return 'Chưa có phòng ban nào trong danh mục hoặc bạn không được phép xem.';
    }
    return 'Không có workspace khớp bộ lọc hiện tại. Thử xóa lọc hoặc đổi từ khóa tìm kiếm.';
});
</script>

<template>
  <Head title="Cấu hình workspace" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Cấu hình workspace"
        :subtitle="headerSubtitle"
        icon="system-config"
        icon-color="brand"
        :badge="viewer.own_department_code || null"
      />
    </template>

    <WorkspaceInsightsBanner
      :insights="insights"
      :can-manage="viewer.can_manage"
      @action="onInsightAction"
    />

    <section
      class="mb-5 overflow-hidden rounded-card border border-slate-200/80 bg-white shadow-sm"
      aria-label="Danh sách workspace phòng ban"
    >
      <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <div class="min-w-0 w-full basis-full lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              v-model="search"
              hide-label
              stretch
              inline-actions
              input-height="h-10"
              placeholder="Tìm phòng ban theo tên hoặc mã…"
              aria-label="Tìm phòng ban"
            />
          </div>

          <div class="ml-auto flex shrink-0 items-center gap-2">
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
                input-id-prefix="workspace-hub-filter-vis"
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
                title="Trường hiển thị trên thẻ"
                @click="openColPanelSafe"
              >
                Cột
              </DatagridToolbarActionButton>
              <ColumnVisibilityDropdown
                v-model="visibleCols"
                :show="showColDd"
                :columns="TABLE_COLUMNS"
                :anchor-ref="colDdRef"
                input-id-prefix="workspace-hub-col-vis"
                @persist="persistVisibleColumns"
              />
            </div>

            <DatagridToolbarActionButton
              icon="export"
              title="Xuất Excel tổng quan"
              @click="exportHub"
            >
              Xuất
            </DatagridToolbarActionButton>
          </div>
        </div>

        <div
          v-if="hasFilterRow"
          class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
        >
          <div v-if="visibleFilters.status">
            <select
              v-model="localFilters.status"
              :class="FILTER_CONTROL_CLASS"
              aria-label="Trạng thái"
            >
              <option
                v-for="opt in statusFilterOptions"
                :key="`status-${opt.value || 'all'}`"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
          </div>
          <div v-if="visibleFilters.readiness">
            <select
              v-model="localFilters.readiness"
              :class="FILTER_CONTROL_CLASS"
              aria-label="Mức sẵn sàng"
            >
              <option
                v-for="opt in readinessFilterOptions"
                :key="`ready-${opt.value || 'all'}`"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
          </div>
          <div v-if="visibleFilters.sort">
            <select
              :value="sortBy"
              :class="FILTER_CONTROL_CLASS"
              aria-label="Sắp xếp"
              @change="setSortBy($event.target.value)"
            >
              <option
                v-for="opt in sortOptions"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
          </div>
        </div>

        <div
          v-if="viewer.can_manage && (selectedCodes.length || selectedEnsureable.length)"
          class="mt-3 flex flex-wrap items-center gap-2 rounded-xl bg-brand/[0.06] px-3 py-2"
        >
          <p class="text-xs font-medium text-slate-700">
            Đã chọn {{ selectedCodes.length }}
            <span
              v-if="selectedEnsureable.length"
              class="text-slate-500"
            >· {{ selectedEnsureable.length }} có thể kích hoạt</span>
          </p>
          <button
            type="button"
            class="btn-primary inline-flex h-8 items-center gap-1.5 px-3 text-xs"
            :disabled="!selectedEnsureable.length || bulkLoading"
            @click="bulkEnsure(selectedEnsureable)"
          >
            <AppIcon
              name="plus"
              :size="14"
            />
            Kích hoạt đã chọn
          </button>
          <button
            type="button"
            class="btn-ghost inline-flex h-8 items-center px-3 text-xs"
            @click="clearSelection"
          >
            Bỏ chọn
          </button>
        </div>
      </div>

      <div class="p-4 md:p-5">
        <WorkspaceProfileGrid
          v-if="paginatedItems.length"
          :workspaces="paginatedItems"
          :selectable="viewer.can_manage"
          :selected-codes="selectedCodes"
          :visible-fields="visibleCols"
          @preview="openPreview"
          @toggle-select="toggleSelect"
        />
        <EmptyState
          v-else
          icon="department"
          title="Chưa có workspace để hiển thị"
          :description="emptyHint"
        />
      </div>

      <div
        v-if="paginationMeta.total > 0"
        class="px-4 py-3"
      >
        <DatagridPaginationFooter
          variant="bar"
          client
          :meta="paginationMeta"
          :per-page="perPage"
          :per-page-options="PER_PAGE_OPTIONS"
          @update:per-page="setPerPage"
          @page-change="goToPage"
        />
      </div>
    </section>

    <WorkspaceProfileDrawer
      :show="Boolean(previewWorkspace)"
      :workspace="previewWorkspace"
      :can-manage="viewer.can_manage"
      @close="previewCode = null"
    />
  </AppLayout>
</template>
