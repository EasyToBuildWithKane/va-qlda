<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import CoachingWorkspace from '@/modules/coaching/components/CoachingWorkspace.vue';
import CoachingSessionFormModal from '@/modules/coaching/components/CoachingSessionFormModal.vue';
import CoachingSessionsSummaryBar from '@/modules/coaching/components/CoachingSessionsSummaryBar.vue';
import CoachingSessionsGroupView from '@/modules/coaching/components/CoachingSessionsGroupView.vue';
import CoachingSessionsTableView from '@/modules/coaching/components/CoachingSessionsTableView.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { useCoachingSessionList, useCoachingSessionGroups } from '@/composables/useCoachingSessionList';
import {
    exportCoachingSessionsCsv,
    exportCoachingSessionsWorkbook,
} from '@/composables/useCoachingExport';
import { useToast } from '@/shared/composables/useToast';
import { useDialog } from '@/composables/useDialog';

const PER_PAGE_OPTIONS = [10, 15, 20, 30];
const VIEW_KEY = 'va-qlda.coaching.sessions.view';

const props = defineProps({
    sessions: { type: Object, required: true },
    summary: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    selectedCourse: { type: Object, default: null },
});

const toast = useToast();
const dialog = useDialog();
const sessionModal = ref(false);
const statusUpdating = ref(new Set());
const exportLoading = ref(false);
const viewMode = ref(localStorage.getItem(VIEW_KEY) === 'table' ? 'table' : 'groups');

const {
    filterForm,
    perPage,
    sessionRows,
    groupedSessions,
    routeParams,
    onPerPageChange,
    appliedFilterCount,
    clearFilters,
    setStatusFilter,
    fetchAllForExport,
    isNavigating,
} = useCoachingSessionList({
    filters: props.filters,
    getSessions: () => props.sessions,
});

const {
    isGroupExpanded,
    toggleGroup,
    toggleAllGroups,
    allGroupsExpanded,
} = useCoachingSessionGroups('va-qlda.coaching.sessions.collapsed-groups');

function setViewMode(mode) {
    viewMode.value = mode;
    localStorage.setItem(VIEW_KEY, mode);
}

const SESSION_COLUMNS = [
    { key: 'date', label: 'Ngày', default: true },
    { key: 'time', label: 'Giờ học', default: true },
    { key: 'hours', label: 'Tổng giờ', default: true },
    { key: 'status', label: 'Trạng thái', default: true },
    { key: 'topic', label: 'Tên buổi', default: true },
    { key: 'materials', label: 'Tài liệu', default: true },
    { key: 'assignments', label: 'Bài tập', default: true },
];

const {
    visibleCols, showColDd, visibleColumnCount, persistVisibleColumns, openColPanel, isColVisible,
} = useVisibleColumns(SESSION_COLUMNS, 'va-qlda.coaching.sessions.columns');

const FILTER_CONTROLS_DEF = [
    { key: 'status', label: 'Trạng thái', default: false },
    { key: 'course', label: 'Khóa học', default: false },
    { key: 'date_from', label: 'Từ ngày', default: false },
    { key: 'date_to', label: 'Đến ngày', default: false },
    { key: 'scheduled', label: 'Lịch học', default: false },
    { key: 'has_materials', label: 'Tài liệu', default: false },
    { key: 'has_assignments', label: 'Bài tập', default: false },
];

const {
    visibleFilters, showFilterPanelDd, enabledFilterControlCount, hasFilterRow,
    persistVisibleFilters, openFilterPanel, FILTER_CONTROLS,
} = useVisibleFilterControls(FILTER_CONTROLS_DEF, 'va-qlda.coaching.sessions.visible-filters.v2');

const canAddSession = computed(() => props.selectedCourse?.can?.update === true);

const summaryActiveStatus = computed(() => filterForm.status);

const filterDdRef = ref(null);
const colDdRef = ref(null);
const exportDdRef = ref(null);
const showExportDd = ref(false);

const onDocClick = (e) => {
    if (filterDdRef.value && !filterDdRef.value.contains(e.target)) showFilterPanelDd.value = false;
    if (colDdRef.value && !colDdRef.value.contains(e.target)) showColDd.value = false;
    if (exportDdRef.value && !exportDdRef.value.contains(e.target)) showExportDd.value = false;
};
onMounted(() => document.addEventListener('mousedown', onDocClick));
onUnmounted(() => document.removeEventListener('mousedown', onDocClick));

const openFilter = () => openFilterPanel(() => { showColDd.value = false; showExportDd.value = false; });
const openCol = () => openColPanel(() => { showFilterPanelDd.value = false; showExportDd.value = false; });
const toggleExport = () => {
    showExportDd.value = !showExportDd.value;
    if (showExportDd.value) {
        showFilterPanelDd.value = false;
        showColDd.value = false;
    }
};

function exportPayload(sessions, scopeLabel) {
    return {
        sessions,
        filters: { ...filterForm },
        summary: props.summary,
        scopeLabel,
    };
}

function doExportCsv() {
    showExportDd.value = false;
    exportCoachingSessionsCsv(exportPayload(sessionRows.value, 'Trang hiện tại'));
    toast.success('Đã tải file CSV.');
}

function doExportExcelPage() {
    showExportDd.value = false;
    exportCoachingSessionsWorkbook(exportPayload(sessionRows.value, 'Trang hiện tại'));
    toast.success('Đã tải file Excel.');
}

async function doExportExcelAll() {
    showExportDd.value = false;
    exportLoading.value = true;
    try {
        const { data, meta } = await fetchAllForExport();
        exportCoachingSessionsWorkbook(exportPayload(data, meta.truncated
            ? `Theo bộ lọc (tối đa ${meta.limit} buổi)`
            : 'Toàn bộ theo bộ lọc'));
        if (meta.truncated) {
            toast.success(`Đã xuất ${meta.exported}/${meta.total_matching} buổi (giới hạn ${meta.limit}).`);
        } else {
            toast.success(`Đã xuất ${meta.exported} buổi theo bộ lọc.`);
        }
    } catch {
        toast.error('Không xuất được dữ liệu. Thử lại sau.');
    } finally {
        exportLoading.value = false;
    }
}

function onSummaryStatus(status) {
    filterForm.scheduled = '';
    setStatusFilter(status);
}

function updateStatus(s, status) {
    if (!s.can?.update || s.status?.value === status) return;
    statusUpdating.value.add(s.id);
    router.patch(route('coaching.sessions.update', { session: s.id }), { status }, {
        preserveScroll: true,
        onFinish: () => statusUpdating.value.delete(s.id),
        onSuccess: () => toast.success('Đã cập nhật trạng thái.'),
    });
}

function goDetail(s) {
    router.visit(route('coaching.sessions.show', { session: s.id }));
}

async function removeSession(s) {
    if (!s.can?.delete) return;
    if (!await dialog.confirm({
        title: 'Xóa buổi học',
        message: `Xóa buổi ${s.session_number}: «${s.title}»? Hành động không hoàn tác.`,
        tone: 'danger',
        confirmText: 'Xóa',
    })) return;
    router.delete(route('coaching.sessions.destroy', { session: s.id }), {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã xóa buổi học.'),
    });
}
</script>

<template>
  <Head title="Danh sách buổi học" />
  <AppLayout flush>
    <template #header>
      <PageHeader
        title="Danh sách buổi học"
        subtitle="KPI theo bộ lọc · nhóm theo khóa · cập nhật nhanh"
        icon="weekly"
        :badge="summary.total"
        back-href="/coaching"
      >
        <Link
          href="/coaching/sessions/schedule"
          class="btn-ghost h-9 gap-1.5 px-3 text-sm"
        >
          <AppIcon
            name="calendar"
            :size="15"
          />
          Lịch học
        </Link>
        <button
          v-if="canAddSession"
          type="button"
          class="btn-primary h-9 gap-1.5 px-3 text-sm"
          @click="sessionModal = true"
        >
          <AppIcon
            name="add"
            :size="15"
          />
          Thêm buổi
        </button>
      </PageHeader>
    </template>

    <div class="flex min-h-0 min-w-0 flex-1 flex-col p-4 sm:p-6">
      <CoachingWorkspace class="flex min-h-0 min-w-0 flex-1 flex-col">
        <section class="card flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
          <CoachingSessionsSummaryBar
            :summary="summary"
            :active-status="summaryActiveStatus"
            @filter-status="onSummaryStatus"
          />

          <div class="flex flex-col gap-2.5 border-b border-slate-100 bg-slate-50/40 px-4 py-3.5 sm:px-5 lg:flex-row lg:items-center lg:gap-3">
            <div class="min-w-0 flex-1">
              <DatagridToolbarSearch
                v-model="filterForm.q"
                stretch
                input-id="coaching-session-search"
                placeholder="Tên buổi, chủ đề, khóa…"
              />
            </div>

            <div class="flex min-w-0 flex-wrap items-center gap-2 lg:shrink-0">
              <div
                ref="filterDdRef"
                class="relative"
              >
                <button
                  type="button"
                  class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium transition select-none"
                  :class="showFilterPanelDd
                    ? 'border-brand/40 bg-brand/5 text-brand'
                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-800'"
                  :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
                  @click="openFilter"
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
                class="relative"
              >
                <button
                  type="button"
                  class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium transition select-none"
                  :class="showColDd
                    ? 'border-brand/40 bg-brand/5 text-brand'
                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-800'"
                  title="Cột hiển thị"
                  @click="openCol"
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
                  :columns="SESSION_COLUMNS"
                  :fixed-labels="['#', 'Tên buổi', 'Thao tác']"
                  @persist="persistVisibleColumns"
                />
              </div>

              <div
                ref="exportDdRef"
                class="relative"
              >
                <button
                  type="button"
                  class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium transition select-none disabled:opacity-50"
                  :class="showExportDd
                    ? 'border-brand/40 bg-brand/5 text-brand'
                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-800'"
                  title="Xuất Excel / CSV"
                  :disabled="exportLoading"
                  @click="toggleExport"
                >
                  <AppIcon
                    name="export"
                    :size="15"
                  />
                  <span>Xuất</span>
                </button>
                <div
                  v-if="showExportDd"
                  class="absolute right-0 z-30 mt-1 min-w-[11rem] rounded-card border border-slate-200 bg-white py-1 shadow-lg"
                >
                  <button
                    type="button"
                    class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                    @click="doExportCsv"
                  >
                    CSV · trang này
                  </button>
                  <button
                    type="button"
                    class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                    @click="doExportExcelPage"
                  >
                    Excel · trang này
                  </button>
                  <button
                    type="button"
                    class="block w-full border-t border-slate-100 px-3 py-2 text-left text-xs font-medium text-brand hover:bg-brand/5"
                    @click="doExportExcelAll"
                  >
                    Excel · theo lọc
                  </button>
                </div>
              </div>

              <div class="flex rounded-btn border border-slate-200 p-0.5">
                <button
                  type="button"
                  class="inline-flex h-8 items-center gap-1 rounded-btn px-2.5 text-xs font-medium transition"
                  :class="viewMode === 'groups' ? 'bg-brand/10 text-brand' : 'text-slate-500 hover:text-slate-700'"
                  title="Nhóm theo khóa (cuộn ngang)"
                  @click="setViewMode('groups')"
                >
                  <AppIcon
                    name="grid"
                    :size="14"
                  />
                  <span class="hidden sm:inline">Nhóm</span>
                </button>
                <button
                  type="button"
                  class="inline-flex h-8 items-center gap-1 rounded-btn px-2.5 text-xs font-medium transition"
                  :class="viewMode === 'table' ? 'bg-brand/10 text-brand' : 'text-slate-500 hover:text-slate-700'"
                  title="Bảng chi tiết"
                  @click="setViewMode('table')"
                >
                  <AppIcon
                    name="list"
                    :size="14"
                  />
                  <span class="hidden sm:inline">Bảng</span>
                </button>
              </div>

              <button
                v-if="groupedSessions.length"
                type="button"
                class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600 hover:border-slate-300"
                :title="allGroupsExpanded(groupedSessions) ? 'Thu gọn tất cả khóa' : 'Mở tất cả khóa'"
                @click="toggleAllGroups(groupedSessions)"
              >
                <AppIcon
                  name="chevron-down"
                  :size="15"
                  class="transition-transform"
                  :class="allGroupsExpanded(groupedSessions) ? '' : '-rotate-90'"
                />
                <span class="hidden sm:inline">{{ allGroupsExpanded(groupedSessions) ? 'Thu nhóm' : 'Mở nhóm' }}</span>
              </button>
            </div>
          </div>

          <div
            v-if="hasFilterRow"
            class="grid grid-cols-1 gap-2 border-b border-slate-100 bg-slate-50/30 px-4 py-2.5 sm:grid-cols-2 sm:px-5 lg:grid-cols-[repeat(auto-fit,minmax(11rem,1fr))] lg:items-center"
          >
            <select
              v-if="visibleFilters.status"
              v-model="filterForm.status"
              class="input h-9 w-full min-w-0 text-sm"
              aria-label="Trạng thái buổi học"
            >
              <option value="">
                Trạng thái (tất cả)
              </option>
              <option
                v-for="s in options.statuses"
                :key="s.value"
                :value="s.value"
              >
                {{ s.label }}
              </option>
            </select>
            <select
              v-if="visibleFilters.course"
              v-model="filterForm.course"
              class="input h-9 w-full min-w-0 text-sm"
              aria-label="Khóa học"
            >
              <option value="">
                Khóa học (tất cả)
              </option>
              <option
                v-for="c in options.courses"
                :key="c.id"
                :value="String(c.id)"
              >
                {{ c.code }} — {{ c.name }}
              </option>
            </select>
            <input
              v-if="visibleFilters.date_from"
              v-model="filterForm.date_from"
              type="date"
              class="input h-9 w-full min-w-0 text-sm"
              aria-label="Từ ngày"
            >
            <input
              v-if="visibleFilters.date_to"
              v-model="filterForm.date_to"
              type="date"
              class="input h-9 w-full min-w-0 text-sm"
              aria-label="Đến ngày"
            >
            <select
              v-if="visibleFilters.scheduled"
              v-model="filterForm.scheduled"
              class="input h-9 w-full min-w-0 text-sm"
              aria-label="Lịch học"
            >
              <option value="">
                Lịch (mọi)
              </option>
              <option value="1">
                Đã có ngày học
              </option>
              <option value="0">
                Chưa lên lịch
              </option>
            </select>
            <select
              v-if="visibleFilters.has_materials"
              v-model="filterForm.has_materials"
              class="input h-9 w-full min-w-0 text-sm"
              aria-label="Lọc tài liệu"
            >
              <option value="">
                Tài liệu (mọi)
              </option>
              <option value="1">
                Đã có tài liệu
              </option>
              <option value="0">
                Chưa có tài liệu
              </option>
            </select>
            <select
              v-if="visibleFilters.has_assignments"
              v-model="filterForm.has_assignments"
              class="input h-9 w-full min-w-0 text-sm"
              aria-label="Lọc bài tập"
            >
              <option value="">
                Bài tập (mọi)
              </option>
              <option value="1">
                Đã có bài tập
              </option>
              <option value="0">
                Chưa có bài tập
              </option>
            </select>
            <button
              v-if="appliedFilterCount || filterForm.q"
              type="button"
              class="h-9 w-full min-w-0 justify-self-start text-xs font-medium text-brand hover:underline sm:col-span-2 lg:col-span-full"
              @click="clearFilters"
            >
              Đặt lại
            </button>
          </div>

          <div
            v-if="selectedCourse"
            class="border-b border-brand/10 bg-brand/[0.03] px-4 py-2 text-xs text-slate-600 sm:px-5"
          >
            Đang lọc theo khóa
            <Link
              :href="route('coaching.courses.show', { course: selectedCourse.id })"
              class="font-medium text-slate-800 hover:text-brand hover:underline"
            >
              {{ selectedCourse.name }}
            </Link>
            <Link
              :href="route('coaching.sessions.index')"
              class="ml-2 font-medium text-brand hover:underline"
            >
              Bỏ lọc
            </Link>
          </div>

          <div
            v-if="isNavigating"
            class="h-0.5 w-full shrink-0 overflow-hidden bg-slate-100"
          >
            <div class="h-full w-1/3 animate-pulse bg-brand" />
          </div>

          <div
            class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden"
            :class="viewMode === 'groups' ? 'flex flex-col' : ''"
          >
            <div
              v-if="!sessionRows.length"
              class="px-6 py-12 text-center text-sm text-slate-500"
            >
              Không có buổi học phù hợp với bộ lọc.
              <button
                type="button"
                class="ml-1 font-medium text-brand hover:underline"
                @click="clearFilters"
              >
                Xóa bộ lọc
              </button>
            </div>

            <CoachingSessionsGroupView
              v-else-if="viewMode === 'groups'"
              class="min-h-0 flex-1"
              :groups="groupedSessions"
              :status-options="options.statuses"
              :status-updating-ids="statusUpdating"
              :is-group-expanded="isGroupExpanded"
              @toggle-group="toggleGroup"
              @update-status="updateStatus"
              @detail="goDetail"
              @delete="removeSession"
            />

            <CoachingSessionsTableView
              v-else
              :groups="groupedSessions"
              :status-options="options.statuses"
              :status-updating-ids="statusUpdating"
              :is-group-expanded="isGroupExpanded"
              :is-col-visible="isColVisible"
              :visible-column-count="visibleColumnCount"
              @toggle-group="toggleGroup"
              @update-status="updateStatus"
              @detail="goDetail"
              @delete="removeSession"
            />
          </div>

          <div
            v-if="sessions.meta"
            class="shrink-0 border-t border-slate-100"
          >
            <DatagridPaginationFooter
              variant="bar"
              :meta="sessions.meta"
              :per-page="perPage"
              :per-page-options="PER_PAGE_OPTIONS"
              @update:per-page="onPerPageChange"
              @page-change="(p) => router.get(route('coaching.sessions.index'), { ...routeParams(false), page: p }, { preserveScroll: true })"
            />
          </div>
        </section>
      </CoachingWorkspace>
    </div>

    <CoachingSessionFormModal
      v-if="selectedCourse"
      :show="sessionModal"
      :course-id="selectedCourse.id"
      :next-session-number="selectedCourse.next_session_number"
      @close="sessionModal = false"
    />
  </AppLayout>
</template>
