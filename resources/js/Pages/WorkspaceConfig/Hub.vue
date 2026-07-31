<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridSegmentedControl from '@/shared/ui/DatagridSegmentedControl.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { useClientPagination } from '@/shared/composables/useClientPagination';
import { useDialog } from '@/composables/useDialog';
import WorkspaceConfigSummaryBar from '@/modules/workspace-config/components/WorkspaceConfigSummaryBar.vue';
import WorkspaceProfileGrid from '@/modules/workspace-config/components/WorkspaceProfileGrid.vue';
import WorkspaceInsightsBanner from '@/modules/workspace-config/components/WorkspaceInsightsBanner.vue';
import WorkspaceCoverageMatrix from '@/modules/workspace-config/components/WorkspaceCoverageMatrix.vue';
import WorkspaceProfileDrawer from '@/modules/workspace-config/components/WorkspaceProfileDrawer.vue';
import { exportWorkspaceHubWorkbook } from '@/modules/workspace-config/composables/useWorkspaceHubExport';

const VIEW_STORAGE = 'va-workspace.workspace-config.view';
const SORT_STORAGE = 'va-workspace.workspace-config.sort';
const DENSITY_STORAGE = 'va-workspace.workspace-config.density';
const COVERAGE_STORAGE = 'va-workspace.workspace-config.coverage';

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
            is_super_admin: false,
        }),
    },
    statusOptions: { type: Array, default: () => [] },
    readinessOptions: { type: Array, default: () => [] },
});

const dialog = useDialog();
const search = ref('');
const viewMode = ref('grid');
const sortBy = ref('status');
const density = ref('comfortable');
const showCoverage = ref(false);
const selectedCodes = ref([]);
const previewCode = ref(null);
const bulkLoading = ref(false);
const localFilters = reactive({
    status: '',
    readiness: '',
});

const statusSegments = computed(() => {
    const base = [
        { key: '', label: 'Tất cả', title: 'Tất cả workspace' },
        { key: 'active', label: 'Đang dùng', icon: 'done' },
        { key: 'draft', label: 'Nháp', icon: 'documents' },
    ];
    if (props.viewer.can_manage) {
        base.push({ key: 'missing', label: 'Chưa kích hoạt', icon: 'system-config' });
        if (props.filters.include_archived) {
            base.push({ key: 'archived', label: 'Lưu trữ', icon: 'documents' });
        }
    }
    return base;
});

const readinessSegments = computed(() => [
    { key: '', label: 'Mọi mức', title: 'Không lọc mức sẵn sàng' },
    { key: 'ready', label: 'Sẵn sàng', icon: 'award' },
    { key: 'partial', label: 'Đang cấu hình', icon: 'documents' },
    { key: 'empty', label: 'Trống', icon: 'system-config' },
]);

const viewTabs = [
    { key: 'grid', label: 'Lưới', icon: 'cards', title: 'Dạng thẻ lưới' },
    { key: 'list', label: 'Danh sách', icon: 'table', title: 'Dạng danh sách' },
];

const densityTabs = [
    { key: 'comfortable', label: 'Rộng', title: 'Thẻ đầy đủ chi tiết' },
    { key: 'compact', label: 'Gọn', title: 'Thẻ mật độ cao' },
];

const sortTabs = [
    { key: 'status', label: 'Trạng thái', title: 'Ưu tiên trạng thái profile' },
    { key: 'readiness', label: 'Sẵn sàng', title: 'Ưu tiên mức hoàn thiện' },
    { key: 'name', label: 'Tên A–Z', title: 'Sắp xếp theo tên phòng ban' },
    { key: 'criteria', label: 'Tiêu chí', title: 'Nhiều tiêu chí trước' },
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

    list = [...list].sort((a, b) => {
        if (sortBy.value === 'name') {
            return a.department_name.localeCompare(b.department_name, 'vi');
        }
        if (sortBy.value === 'criteria') {
            return (b.criteria_count - a.criteria_count)
                || a.department_name.localeCompare(b.department_name, 'vi');
        }
        if (sortBy.value === 'readiness') {
            return (readyRank(a.readiness?.key) - readyRank(b.readiness?.key))
                || (b.readiness?.percent - a.readiness?.percent)
                || a.department_name.localeCompare(b.department_name, 'vi');
        }
        return (statusRank(a.status) - statusRank(b.status))
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

function onQuickFilter(payload) {
    localFilters.status = payload?.status ?? '';
    localFilters.readiness = payload?.readiness ?? '';
}

function setViewMode(mode) {
    viewMode.value = mode;
    try { localStorage.setItem(VIEW_STORAGE, mode); } catch { /* ignore */ }
}

function setSortBy(mode) {
    sortBy.value = mode;
    try { localStorage.setItem(SORT_STORAGE, mode); } catch { /* ignore */ }
}

function setDensity(mode) {
    density.value = mode;
    try { localStorage.setItem(DENSITY_STORAGE, mode); } catch { /* ignore */ }
}

function toggleCoverage() {
    showCoverage.value = !showCoverage.value;
    try { localStorage.setItem(COVERAGE_STORAGE, showCoverage.value ? '1' : '0'); } catch { /* ignore */ }
}

function toggleIncludeArchived() {
    router.get('/workspace-config', {
        include_archived: props.filters.include_archived ? 0 : 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
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
        return;
    }
    if (insight.action === 'filter_partial') {
        localFilters.status = '';
        localFilters.readiness = 'partial';
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

onMounted(() => {
    try {
        const savedView = localStorage.getItem(VIEW_STORAGE);
        if (savedView === 'grid' || savedView === 'list') viewMode.value = savedView;
        const savedSort = localStorage.getItem(SORT_STORAGE);
        if (sortTabs.some((t) => t.key === savedSort)) sortBy.value = savedSort;
        const savedDensity = localStorage.getItem(DENSITY_STORAGE);
        if (savedDensity === 'comfortable' || savedDensity === 'compact') density.value = savedDensity;
        showCoverage.value = localStorage.getItem(COVERAGE_STORAGE) === '1';
    } catch { /* ignore */ }
});

const emptyHint = computed(() => {
    if (!props.viewer.can_manage && !props.viewer.own_department_code) {
        return 'Tài khoản chưa gắn phòng ban HRM — không thể mở workspace phòng ban.';
    }
    if (props.workspaces.length === 0) {
        return 'Chưa có phòng ban nào trong danh mục hoặc bạn không được phép xem.';
    }
    return 'Không có workspace khớp bộ lọc hiện tại. Thử xóa lọc hoặc đổi từ khóa tìm kiếm.';
});

const resultHint = computed(() => {
    const n = filteredWorkspaces.value.length;
    const total = props.workspaces.length;
    if (n === total) return `${total} phòng ban`;
    return `Hiển thị ${n}/${total} phòng ban`;
});
</script>

<template>
  <Head title="Cấu hình workspace" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Cấu hình workspace"
        subtitle="Trung tâm cấu hình theo phòng ban — phủ sóng module, kích hoạt hàng loạt, checklist onboard"
        icon="system-config"
        icon-color="brand"
        :badge="summary.total || null"
      >
        <DatagridToolbarActionButton
          icon="documents"
          title="Xuất Excel tổng quan"
          @click="exportHub"
        >
          Xuất
        </DatagridToolbarActionButton>
        <DatagridToolbarActionButton
          :icon="showCoverage ? 'done' : 'table'"
          :active="showCoverage"
          title="Bật/tắt ma trận phủ module"
          @click="toggleCoverage"
        >
          {{ showCoverage ? 'Ẩn ma trận' : 'Ma trận' }}
        </DatagridToolbarActionButton>
        <DatagridToolbarActionButton
          v-if="viewer.can_manage"
          icon="documents"
          :active="filters.include_archived"
          title="Hiện workspace đã lưu trữ"
          @click="toggleIncludeArchived"
        >
          {{ filters.include_archived ? 'Ẩn lưu trữ' : 'Hiện lưu trữ' }}
        </DatagridToolbarActionButton>
      </PageHeader>
    </template>

    <WorkspaceConfigSummaryBar
      class="mb-5"
      :summary="summary"
      :active-status="localFilters.status"
      :active-readiness="localFilters.readiness"
      :can-manage="viewer.can_manage"
      @quick-filter="onQuickFilter"
    />

    <WorkspaceInsightsBanner
      :insights="insights"
      :can-manage="viewer.can_manage"
      @action="onInsightAction"
    />

    <WorkspaceCoverageMatrix
      v-if="showCoverage"
      :coverage="coverage"
      @select-department="openPreview"
    />

    <section
      class="kpi-strip relative mb-5 overflow-hidden rounded-card border border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white shadow-sm"
      aria-label="Danh sách workspace phòng ban"
    >
      <div
        class="kpi-strip__bg-outer pointer-events-none absolute -right-6 top-0 h-full w-1/2 bg-gradient-to-l from-brand/[0.04] to-transparent"
        aria-hidden="true"
      />

      <header class="relative border-b border-slate-100/80 px-5 py-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
          <div class="min-w-0">
            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
              Workspace
            </p>
            <h2 class="mt-1 font-display text-base font-semibold text-slate-800">
              Phòng ban
            </h2>
            <p class="mt-1 text-sm text-slate-500">
              Bấm thẻ để xem nhanh. «Chưa kích hoạt» = chưa có profile; readiness phản ánh nội dung module.
            </p>
          </div>
          <p class="text-xs font-medium tabular-nums text-slate-400">
            {{ resultHint }}
          </p>
        </div>
      </header>

      <div class="relative space-y-3 border-b border-slate-100 px-4 py-3">
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
          <div class="ml-auto flex shrink-0 flex-wrap items-center justify-end gap-2">
            <DatagridSegmentedControl
              :model-value="density"
              :items="densityTabs"
              aria-label="Mật độ thẻ"
              icon-only-below-sm
              @update:model-value="setDensity"
            />
            <DatagridSegmentedControl
              :model-value="viewMode"
              :items="viewTabs"
              aria-label="Chế độ hiển thị"
              icon-only-below-sm
              @update:model-value="setViewMode"
            />
          </div>
        </div>

        <div class="flex w-full min-w-0 flex-wrap items-center gap-2">
          <DatagridSegmentedControl
            v-model="localFilters.status"
            :items="statusSegments"
            aria-label="Lọc trạng thái profile"
            icon-only-below-sm
          />
          <DatagridSegmentedControl
            v-model="localFilters.readiness"
            :items="readinessSegments"
            aria-label="Lọc mức sẵn sàng nội dung"
            icon-only-below-sm
          />
          <div class="ml-auto">
            <DatagridSegmentedControl
              :model-value="sortBy"
              :items="sortTabs"
              aria-label="Sắp xếp danh sách"
              icon-only-below-sm
              @update:model-value="setSortBy"
            />
          </div>
        </div>

        <div
          v-if="viewer.can_manage && (selectedCodes.length || selectedEnsureable.length)"
          class="flex flex-wrap items-center gap-2 rounded-xl bg-brand/[0.04] px-3 py-2 ring-1 ring-brand/15"
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

      <div class="relative p-4 md:p-5">
        <WorkspaceProfileGrid
          v-if="paginatedItems.length"
          :workspaces="paginatedItems"
          :can-manage="viewer.can_manage"
          :layout="viewMode"
          :density="density"
          :selectable="viewer.can_manage"
          :selected-codes="selectedCodes"
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
        class="relative border-t border-slate-100 px-4 py-3"
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
