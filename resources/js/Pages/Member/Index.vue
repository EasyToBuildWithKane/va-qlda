<script setup>
import {
    computed, reactive, ref, watch, onMounted, onBeforeUnmount,
} from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridSegmentedControl from '@/shared/ui/DatagridSegmentedControl.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import Badge from '@/shared/ui/Badge.vue';
import MemberDirectorySummaryBar from '@/modules/profile/components/MemberDirectorySummaryBar.vue';
import MemberDirectoryListRow from '@/modules/profile/components/MemberDirectoryListRow.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { useFixedDropdownAnchor } from '@/shared/composables/useFixedDropdownAnchor';
import { exportMemberDirectoryPage } from '@/modules/profile/composables/useMemberDirectoryExport';
import { useToast } from '@/shared/composables/useToast';

const VIEW_KEY = 'va-qlda.members.view';
const PER_PAGE_OPTIONS = [12, 24, 48];

const props = defineProps({
    members: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
});

const toast = useToast();

const filterForm = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    project: props.filters.project ?? '',
});

const perPage = ref(Number(props.filters.per_page) || props.members.meta?.per_page || 12);

const viewMode = ref(
    typeof localStorage !== 'undefined' && localStorage.getItem(VIEW_KEY) === 'table'
        ? 'table'
        : 'list',
);

function setViewMode(mode) {
    viewMode.value = mode;
    try {
        localStorage.setItem(VIEW_KEY, mode);
    } catch {
        /* ignore */
    }
}

const VIEW_TABS = [
    { key: 'list', label: 'Danh sách', icon: 'list', title: 'Danh sách gọn' },
    { key: 'table', label: 'Bảng', icon: 'report-history', title: 'Bảng chi tiết' },
];

const TABLE_COLUMNS = [
    { key: 'code', label: 'Mã', default: true },
    { key: 'role', label: 'Chức danh', default: true },
    { key: 'email', label: 'Email', default: false },
    { key: 'seniority', label: 'Cấp bậc', default: true },
    { key: 'skills', label: 'Kỹ năng', default: false },
    { key: 'projects', label: 'Dự án', default: true },
    { key: 'status', label: 'Trạng thái', default: true },
];

const {
    visibleCols,
    showColDd,
    visibleColumnCount,
    persistVisibleColumns,
    openColPanel,
    isColVisible,
} = useVisibleColumns(TABLE_COLUMNS, 'va-qlda.members.columns.v1');

const tableColspan = computed(() => 2 + visibleColumnCount.value);

const MEMBER_FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái', default: false },
];

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(MEMBER_FILTER_CONTROLS, 'va-qlda.members.visible-filters.v1');

const filterPanelDdRef = ref(null);
const colDdRef = ref(null);
const exportRef = ref(null);
const exportMenu = ref(false);
const exporting = ref(false);

const { panelStyle: exportPanelStyle } = useFixedDropdownAnchor(
    () => exportRef.value,
    exportMenu,
    { width: 220, zIndex: 85 },
);

function routeParams(resetPage = false) {
    const params = Object.fromEntries(
        Object.entries({ ...filterForm, per_page: perPage.value }).filter(([, v]) => v !== '' && v != null),
    );
    if (resetPage) params.page = 1;
    return params;
}

function applyFilters(resetPage = true) {
    router.get('/members', routeParams(resetPage), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function onKpiStatus(status) {
    filterForm.status = status || '';
    filterForm.project = '';
    applyFilters(true);
}

function onKpiProject(scope) {
    filterForm.project = scope || '';
    filterForm.status = '';
    applyFilters(true);
}

function onPerPageChange(n) {
    perPage.value = n;
    applyFilters(true);
}

let kwTimer = null;
watch(() => filterForm.q, () => {
    clearTimeout(kwTimer);
    kwTimer = setTimeout(() => applyFilters(true), 350);
});

watch(() => filterForm.status, () => applyFilters(true));

const onDocClick = (e) => {
    const t = e.target;
    if (t.closest?.('[data-filter-visibility-panel]')) return;
    if (t.closest?.('[data-column-visibility-panel]')) return;
    if (t.closest?.('[data-member-export-panel]')) return;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(t)) {
        showFilterPanelDd.value = false;
    }
    if (colDdRef.value && !colDdRef.value.contains(t)) {
        showColDd.value = false;
    }
    if (exportRef.value && !exportRef.value.contains(t)) {
        exportMenu.value = false;
    }
};

onMounted(() => document.addEventListener('mousedown', onDocClick));
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocClick));

function openFilterPanelSafe() {
    openFilterPanel(() => {
        showColDd.value = false;
        exportMenu.value = false;
    });
}

function openColPanelSafe() {
    openColPanel(() => {
        showFilterPanelDd.value = false;
        exportMenu.value = false;
    });
}

function toggleExportMenu() {
    exportMenu.value = !exportMenu.value;
    if (exportMenu.value) {
        showFilterPanelDd.value = false;
        showColDd.value = false;
    }
}

function runExport(format) {
    exportMenu.value = false;
    if (!props.members.data?.length) {
        toast.warning('Không có dữ liệu để xuất trên trang này.');
        return;
    }
    exporting.value = true;
    try {
        const count = exportMemberDirectoryPage(props.members.data, format);
        toast.success(`Đã xuất ${count} thành viên (trang hiện tại).`);
    } catch {
        toast.error('Xuất file thất bại. Thử lại sau.');
    } finally {
        exporting.value = false;
    }
}

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const statusLabel = (active) => (active ? 'Đang hoạt động' : 'Ngừng hoạt động');
</script>

<template>
  <Head title="Hồ sơ thành viên" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Hồ sơ thành viên"
        subtitle="Danh bạ năng lực & hồ sơ nhân sự"
        icon="member-profiles"
        :badge="summary.total"
      />
    </template>

    <MemberDirectorySummaryBar
      :summary="summary"
      :status-filter="filterForm.status"
      :project-filter="filterForm.project"
      @filter-status="onKpiStatus"
      @filter-project="onKpiProject"
    />

    <div class="card overflow-visible">
      <div class="border-b border-slate-100 px-5 py-4">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              v-model="filterForm.q"
              input-id="members-directory-search"
              placeholder="Tên, mã, chức danh, email…"
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

            <div
              ref="colDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="columns"
                :active="showColDd"
                :disabled="viewMode !== 'table'"
                title="Cột hiển thị (chế độ bảng)"
                @click="openColPanelSafe"
              >
                Cột
              </DatagridToolbarActionButton>
              <ColumnVisibilityDropdown
                v-model="visibleCols"
                :show="showColDd"
                :columns="TABLE_COLUMNS"
                :anchor-ref="colDdRef"
                :fixed-labels="['Thành viên']"
                input-id-prefix="members-col"
                @persist="persistVisibleColumns"
              />
            </div>

            <div
              ref="exportRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="export"
                :active="exportMenu"
                :disabled="exporting"
                title="Xuất trang hiện tại"
                @click="toggleExportMenu"
              >
                {{ exporting ? 'Đang xuất…' : 'Xuất' }}
              </DatagridToolbarActionButton>
            </div>
          </div>

          <div class="ml-auto flex shrink-0 flex-wrap items-center justify-end gap-2">
            <DatagridSegmentedControl
              :model-value="viewMode"
              :items="VIEW_TABS"
              aria-label="Chế độ hiển thị"
              icon-only-below-sm
              @update:model-value="setViewMode"
            />
          </div>
        </div>

        <Teleport to="body">
          <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0"
          >
            <div
              v-if="exportMenu"
              :style="exportPanelStyle"
              class="overflow-hidden rounded-card border border-slate-200 bg-white p-1 shadow-elevation-2"
              data-member-export-panel
            >
              <button
                type="button"
                class="flex w-full flex-col rounded-btn px-3 py-2 text-left hover:bg-slate-50"
                @click="runExport('xlsx')"
              >
                <span class="text-sm font-medium text-slate-700">Excel (.xlsx)</span>
                <span class="text-[10px] text-slate-400">Trang đang xem</span>
              </button>
              <button
                type="button"
                class="flex w-full rounded-btn px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
                @click="runExport('csv')"
              >
                CSV
              </button>
            </div>
          </Transition>
        </Teleport>
      </div>

      <div
        v-if="hasFilterRow"
        class="grid grid-cols-1 gap-3 border-t border-slate-100 px-5 py-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
      >
        <DatagridFilterField v-if="visibleFilters.status">
          <select
            v-model="filterForm.status"
            :class="FILTER_CONTROL_CLASS"
            aria-label="Trạng thái"
          >
            <option value="">
              Trạng thái
            </option>
            <option value="active">
              Đang hoạt động
            </option>
            <option value="inactive">
              Ngừng hoạt động
            </option>
          </select>
        </DatagridFilterField>
      </div>

      <EmptyState
        v-if="!members.data.length"
        icon="members"
        title="Không tìm thấy thành viên"
        description="Thử đổi từ khoá hoặc bộ lọc trạng thái."
        class="py-16"
      />

      <div
        v-else-if="viewMode === 'list'"
        class="space-y-2 p-4 sm:p-5"
      >
        <MemberDirectoryListRow
          v-for="m in members.data"
          :key="m.id"
          :member="m"
        />
      </div>

      <div
        v-else
        class="overflow-x-auto"
      >
        <table class="w-full text-sm">
          <thead class="border-b border-slate-100 bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th class="min-w-[14rem] px-5 py-3">
                Thành viên
              </th>
              <th
                v-if="isColVisible('code')"
                class="px-5 py-3"
              >
                Mã
              </th>
              <th
                v-if="isColVisible('role')"
                class="px-5 py-3"
              >
                Chức danh
              </th>
              <th
                v-if="isColVisible('email')"
                class="px-5 py-3"
              >
                Email
              </th>
              <th
                v-if="isColVisible('seniority')"
                class="px-5 py-3"
              >
                Cấp bậc
              </th>
              <th
                v-if="isColVisible('skills')"
                class="px-5 py-3"
              >
                Kỹ năng
              </th>
              <th
                v-if="isColVisible('projects')"
                class="px-5 py-3 text-center"
              >
                Dự án
              </th>
              <th
                v-if="isColVisible('status')"
                class="px-5 py-3 text-center"
              >
                Trạng thái
              </th>
              <th class="w-12 px-3 py-3" />
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="m in members.data"
              :key="m.id"
              class="group transition-colors hover:bg-slate-50/80"
            >
              <td class="px-5 py-3.5">
                <Link
                  :href="`/members/${m.id}`"
                  class="flex min-w-0 items-center gap-3"
                >
                  <Avatar
                    :name="m.name"
                    :src="m.avatar_path"
                    :size="36"
                  />
                  <div class="min-w-0">
                    <p class="truncate font-medium text-slate-800 group-hover:text-brand">
                      {{ m.name }}
                    </p>
                    <p
                      v-if="!isColVisible('code')"
                      class="font-mono text-[11px] text-slate-400"
                    >
                      {{ m.code }}
                    </p>
                  </div>
                </Link>
              </td>
              <td
                v-if="isColVisible('code')"
                class="px-5 py-3.5 font-mono text-xs text-slate-600"
              >
                {{ m.code }}
              </td>
              <td
                v-if="isColVisible('role')"
                class="max-w-[12rem] px-5 py-3.5 text-slate-600"
              >
                <span class="line-clamp-2">{{ m.role_title || '—' }}</span>
              </td>
              <td
                v-if="isColVisible('email')"
                class="max-w-[14rem] px-5 py-3.5 text-slate-600"
              >
                <span class="truncate">{{ m.email || '—' }}</span>
              </td>
              <td
                v-if="isColVisible('seniority')"
                class="px-5 py-3.5"
              >
                <Badge
                  v-if="m.seniority.value !== 'member'"
                  :label="m.seniority.label"
                  :color="m.seniority.color"
                />
                <span
                  v-else
                  class="text-slate-400"
                >—</span>
              </td>
              <td
                v-if="isColVisible('skills')"
                class="max-w-[16rem] px-5 py-3.5"
              >
                <div
                  v-if="m.skills_preview?.length"
                  class="flex flex-wrap gap-1"
                >
                  <span
                    v-for="s in m.skills_preview.slice(0, 3)"
                    :key="s"
                    class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-600"
                  >
                    {{ s }}
                  </span>
                  <span
                    v-if="m.skills_total > 3"
                    class="text-[10px] text-slate-400"
                  >+{{ m.skills_total - 3 }}</span>
                </div>
                <span
                  v-else
                  class="text-slate-400"
                >—</span>
              </td>
              <td
                v-if="isColVisible('projects')"
                class="max-w-[14rem] px-5 py-3.5 text-left"
              >
                <div
                  v-if="m.projects_preview?.length"
                  class="flex flex-wrap gap-1"
                >
                  <span
                    v-for="p in m.projects_preview.slice(0, 2)"
                    :key="p.id"
                    class="truncate rounded bg-slate-50 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 ring-1 ring-slate-100"
                    :title="p.name"
                  >
                    {{ p.code || p.name }}
                  </span>
                  <span
                    v-if="m.projects_count > 2"
                    class="text-[10px] tabular-nums text-slate-400"
                  >+{{ m.projects_count - 2 }}</span>
                </div>
                <span
                  v-else
                  class="text-slate-400"
                >—</span>
              </td>
              <td
                v-if="isColVisible('status')"
                class="px-5 py-3.5 text-center"
              >
                <span
                  class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium"
                  :class="m.is_active
                    ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100'
                    : 'bg-slate-100 text-slate-500 ring-1 ring-slate-200'"
                >
                  {{ statusLabel(m.is_active) }}
                </span>
              </td>
              <td class="px-3 py-3.5 text-right">
                <Link
                  :href="`/members/${m.id}`"
                  class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 opacity-0 transition group-hover:opacity-100 hover:bg-brand/5 hover:text-brand"
                  :title="`Xem hồ sơ ${m.name}`"
                >
                  <AppIcon
                    name="chevron-right"
                    :size="16"
                  />
                </Link>
              </td>
            </tr>
          </tbody>
          <DatagridPaginationFooter
            :meta="members.meta"
            :per-page="perPage"
            :per-page-options="PER_PAGE_OPTIONS"
            :colspan="tableColspan"
            @update:per-page="onPerPageChange"
          />
        </table>
      </div>

      <DatagridPaginationFooter
        v-if="members.data.length && viewMode === 'list'"
        variant="bar"
        :meta="members.meta"
        :per-page="perPage"
        :per-page-options="PER_PAGE_OPTIONS"
        @update:per-page="onPerPageChange"
      />
    </div>
  </AppLayout>
</template>
