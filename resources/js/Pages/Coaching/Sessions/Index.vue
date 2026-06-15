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
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridSegmentedControl from '@/shared/ui/DatagridSegmentedControl.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterDatePicker from '@/shared/ui/FilterDatePicker.vue';
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
const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const VIEW_TABS = [
    { key: 'groups', label: 'Nhóm', icon: 'grid', title: 'Nhóm theo khóa' },
    { key: 'table', label: 'Bảng', icon: 'list', title: 'Bảng chi tiết' },
];

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
    { key: 'date_range', label: 'Thời gian', default: false },
    { key: 'scheduled', label: 'Lịch học', default: false },
    { key: 'has_materials', label: 'Tài liệu', default: false },
    { key: 'has_assignments', label: 'Bài tập', default: false },
];

const {
    visibleFilters, showFilterPanelDd, enabledFilterControlCount, hasFilterRow,
    persistVisibleFilters, openFilterPanel, FILTER_CONTROLS,
} = useVisibleFilterControls(FILTER_CONTROLS_DEF, 'va-qlda.coaching.sessions.visible-filters.v3');

const canAddSession = computed(() => props.selectedCourse?.can?.update === true);

const summaryActiveStatus = computed(() => filterForm.status);

const filterDdRef = ref(null);
const colDdRef = ref(null);
const exportDdRef = ref(null);
const showExportDd = ref(false);

const onDocClick = (e) => {
    if (e.target.closest?.('[data-filter-visibility-panel]')) return;
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
  <AppLayout>
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

    <div class="min-w-0">
      <CoachingWorkspace>
        <section class="card min-w-0 overflow-visible">
          <CoachingSessionsSummaryBar
            :summary="summary"
            :active-status="summaryActiveStatus"
            @filter-status="onSummaryStatus"
          />

          <div class="border-b border-slate-100 bg-slate-50/40 px-4 py-3.5 sm:px-5 lg:py-4">
            <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
              <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
                <DatagridToolbarSearch
                  v-model="filterForm.q"
                  input-id="coaching-session-search"
                  placeholder="Tên buổi, chủ đề, khóa…"
                  stretch
                  inline-actions
                  hide-label
                  input-height="h-10"
                />
              </div>

              <div class="flex shrink-0 items-center gap-2">
                <div
                  ref="filterDdRef"
                  class="relative shrink-0"
                >
                  <DatagridToolbarActionButton
                    icon="filter"
                    :active="showFilterPanelDd"
                    :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
                    @click="openFilter"
                  >
                    Lọc
                  </DatagridToolbarActionButton>
                  <FilterVisibilityDropdown
                    v-model="visibleFilters"
                    :show="showFilterPanelDd"
                    :anchor-ref="filterDdRef"
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
                    title="Cột hiển thị"
                    @click="openCol"
                  >
                    Cột
                  </DatagridToolbarActionButton>
                  <ColumnVisibilityDropdown
                    v-model="visibleCols"
                    :show="showColDd"
                    :columns="SESSION_COLUMNS"
                    :anchor-ref="colDdRef"
                    :fixed-labels="['#', 'Tên buổi', 'Thao tác']"
                    @persist="persistVisibleColumns"
                  />
                </div>

                <div
                  ref="exportDdRef"
                  class="relative shrink-0"
                >
                  <DatagridToolbarActionButton
                    icon="export"
                    :active="showExportDd"
                    :disabled="exportLoading"
                    title="Xuất Excel / CSV"
                    @click="toggleExport"
                  >
                    {{ exportLoading ? 'Đang xuất…' : 'Xuất' }}
                  </DatagridToolbarActionButton>
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
              </div>

              <div class="ml-auto flex min-w-0 shrink-0 flex-wrap items-center justify-end gap-2">
                <DatagridSegmentedControl
                  :model-value="viewMode"
                  :items="VIEW_TABS"
                  aria-label="Chế độ xem danh sách"
                  icon-only-below-sm
                  @update:model-value="setViewMode"
                />
                <button
                  v-if="viewMode === 'groups' && groupedSessions.length"
                  type="button"
                  class="inline-flex h-10 shrink-0 items-center gap-1 rounded-btn border border-slate-200 bg-white px-3 text-xs font-medium text-slate-600 hover:border-slate-300"
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
              class="grid grid-cols-1 gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
            >
              <DatagridFilterField v-if="visibleFilters.status">
                <label
                  for="coaching-session-filter-status"
                  class="sr-only"
                >Trạng thái</label>
                <select
                  id="coaching-session-filter-status"
                  v-model="filterForm.status"
                  :class="FILTER_CONTROL_CLASS"
                >
                  <option value="">
                    Trạng thái
                  </option>
                  <option
                    v-for="s in options.statuses"
                    :key="s.value"
                    :value="s.value"
                  >
                    {{ s.label }}
                  </option>
                </select>
              </DatagridFilterField>

              <DatagridFilterField v-if="visibleFilters.course">
                <label
                  for="coaching-session-filter-course"
                  class="sr-only"
                >Khóa học</label>
                <select
                  id="coaching-session-filter-course"
                  v-model="filterForm.course"
                  :class="FILTER_CONTROL_CLASS"
                >
                  <option value="">
                    Khóa học
                  </option>
                  <option
                    v-for="c in options.courses"
                    :key="c.id"
                    :value="String(c.id)"
                  >
                    {{ c.code }} — {{ c.name }}
                  </option>
                </select>
              </DatagridFilterField>

              <div
                v-if="visibleFilters.date_range"
                class="min-w-0 w-full sm:col-span-2 xl:col-span-2"
              >
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:gap-3">
                  <FilterDatePicker
                    v-model="filterForm.date_from"
                    placeholder="Từ ngày"
                    :max-date="filterForm.date_to || null"
                  />
                  <FilterDatePicker
                    v-model="filterForm.date_to"
                    placeholder="Đến ngày"
                    :min-date="filterForm.date_from || null"
                  />
                </div>
              </div>

              <DatagridFilterField v-if="visibleFilters.scheduled">
                <label
                  for="coaching-session-filter-scheduled"
                  class="sr-only"
                >Lịch học</label>
                <select
                  id="coaching-session-filter-scheduled"
                  v-model="filterForm.scheduled"
                  :class="FILTER_CONTROL_CLASS"
                >
                  <option value="">
                    Lịch học
                  </option>
                  <option value="1">
                    Đã có ngày học
                  </option>
                  <option value="0">
                    Chưa lên lịch
                  </option>
                </select>
              </DatagridFilterField>

              <DatagridFilterField v-if="visibleFilters.has_materials">
                <label
                  for="coaching-session-filter-materials"
                  class="sr-only"
                >Tài liệu</label>
                <select
                  id="coaching-session-filter-materials"
                  v-model="filterForm.has_materials"
                  :class="FILTER_CONTROL_CLASS"
                >
                  <option value="">
                    Tài liệu
                  </option>
                  <option value="1">
                    Đã có tài liệu
                  </option>
                  <option value="0">
                    Chưa có tài liệu
                  </option>
                </select>
              </DatagridFilterField>

              <DatagridFilterField v-if="visibleFilters.has_assignments">
                <label
                  for="coaching-session-filter-assignments"
                  class="sr-only"
                >Bài tập</label>
                <select
                  id="coaching-session-filter-assignments"
                  v-model="filterForm.has_assignments"
                  :class="FILTER_CONTROL_CLASS"
                >
                  <option value="">
                    Bài tập
                  </option>
                  <option value="1">
                    Đã có bài tập
                  </option>
                  <option value="0">
                    Chưa có bài tập
                  </option>
                </select>
              </DatagridFilterField>

              <div
                v-if="appliedFilterCount || filterForm.q"
                class="col-span-full flex justify-end pt-0.5"
              >
                <button
                  type="button"
                  class="inline-flex h-10 items-center px-2 text-xs font-medium text-brand hover:underline"
                  @click="clearFilters"
                >
                  Đặt lại bộ lọc
                </button>
              </div>
            </div>
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

          <div>
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
