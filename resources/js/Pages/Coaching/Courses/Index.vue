<script setup>
import { ref, computed, reactive, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Badge from '@/shared/ui/Badge.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import {
    exportCoachingCoursesCsv,
    exportCoachingCoursesWorkbook,
} from '@/composables/useCoachingExport';
import { currency, date } from '@/composables/useFormat';
import { useToast } from '@/shared/composables/useToast';
import CoachingCourseFormModal from '@/modules/coaching/components/CoachingCourseFormModal.vue';
import CoachingWorkspace from '@/modules/coaching/components/CoachingWorkspace.vue';

const PER_PAGE_OPTIONS = [10, 15, 20, 30];

const courseModal = ref(false);
const openCreateModal = () => { courseModal.value = true; };

const props = defineProps({
    courses: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const toast = useToast();
const inertiaPage = usePage();
const VIEW_KEY = 'va-qlda.coaching.courses.view';
const viewMode = ref(localStorage.getItem(VIEW_KEY) === 'table' ? 'table' : 'cards');

function setViewMode(mode) {
    viewMode.value = mode;
    localStorage.setItem(VIEW_KEY, mode);
}

const TABLE_COLUMNS = [
    { key: 'code', label: 'Mã', default: true },
    { key: 'student', label: 'Học viên', default: true },
    { key: 'coach', label: 'Coach', default: true },
    { key: 'status', label: 'Trạng thái', default: true },
    { key: 'progress', label: 'Tiến độ', default: true },
    { key: 'sessions', label: 'Số buổi', default: true },
    { key: 'dates', label: 'Thời gian', default: false },
    { key: 'fee', label: 'Học phí', default: false },
];

const {
    visibleCols,
    showColDd,
    visibleColumnCount,
    persistVisibleColumns,
    openColPanel,
    isColVisible,
} = useVisibleColumns(TABLE_COLUMNS, 'va-qlda.coaching.courses.columns');

const filterForm = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
});

const perPage = ref(Number(props.filters.per_page) || props.courses.meta?.per_page || 20);

const courseRows = computed(() => props.courses.data ?? []);

function routeParams(resetPage = false) {
    const params = Object.fromEntries(
        Object.entries({ ...filterForm, per_page: perPage.value }).filter(([, v]) => v !== '' && v != null),
    );
    if (resetPage) params.page = 1;
    return params;
}

function load(resetPage = true) {
    router.get(route('coaching.courses.index'), routeParams(resetPage), {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
}

let qTimer = null;
watch(() => filterForm.q, () => {
    clearTimeout(qTimer);
    qTimer = setTimeout(() => load(true), 350);
});

watch(() => filterForm.status, () => load(true));

function onPerPageChange(n) {
    perPage.value = n;
    load(true);
}

const FILTER_CONTROLS_DEF = [
    { key: 'status', label: 'Trạng thái', default: true },
];

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(FILTER_CONTROLS_DEF, 'va-qlda.coaching.courses.visible-filters');

const filterDdRef = ref(null);
const colDdRef = ref(null);
const exportDdRef = ref(null);
const showExportDd = ref(false);

const onDocClick = (e) => {
    if (filterDdRef.value && !filterDdRef.value.contains(e.target)) showFilterPanelDd.value = false;
    if (colDdRef.value && !colDdRef.value.contains(e.target)) showColDd.value = false;
    if (exportDdRef.value && !exportDdRef.value.contains(e.target)) showExportDd.value = false;
};
onMounted(() => {
    document.addEventListener('mousedown', onDocClick);
    const wantCreate = new URLSearchParams(inertiaPage.url.split('?')[1] ?? '').get('create') === '1';
    if (wantCreate && props.can.create) {
        courseModal.value = true;
        router.get(route('coaching.courses.index'), routeParams(false), {
            replace: true,
            preserveState: true,
            preserveScroll: true,
        });
    }
});
onUnmounted(() => document.removeEventListener('mousedown', onDocClick));

const openFilter = () => {
    openFilterPanel(() => { showColDd.value = false; showExportDd.value = false; });
};
const openCol = () => {
    openColPanel(() => { showFilterPanelDd.value = false; showExportDd.value = false; });
};
const toggleExport = () => {
    showExportDd.value = !showExportDd.value;
    if (showExportDd.value) {
        showFilterPanelDd.value = false;
        showColDd.value = false;
    }
};

const exportPayload = () => ({
    courses: courseRows.value,
    filters: { q: filterForm.q, status: filterForm.status },
});

function doExportCsv() {
    showExportDd.value = false;
    exportCoachingCoursesCsv(exportPayload());
    toast.success('Đã tải file CSV.');
}

function doExportExcel() {
    showExportDd.value = false;
    exportCoachingCoursesWorkbook(exportPayload());
    toast.success('Đã tải file Excel.');
}

const totalCols = computed(() => 2 + visibleColumnCount.value);

function statusColor(value) {
    if (value === 'active') return 'emerald';
    if (value === 'completed') return 'sky';
    if (value === 'cancelled') return 'rose';
    return 'slate';
}
</script>

<template>
  <Head title="Khóa học Coaching" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Khóa học"
        subtitle="Quản lý coaching và mentoring"
        icon="knowledge"
        :badge="courses.meta?.total"
      >
        <Link
          href="/coaching"
          class="btn-ghost h-9 px-3 text-sm"
        >
          Dashboard
        </Link>
        <button
          v-if="can.create"
          type="button"
          class="btn-primary h-9 gap-1.5 px-3 text-sm"
          @click="openCreateModal"
        >
          <AppIcon
            name="add"
            :size="15"
          />
          Thêm khóa
        </button>
      </PageHeader>
    </template>

    <CoachingWorkspace>
      <div class="card overflow-visible">
        <div class="flex flex-col gap-2.5 border-b border-slate-100 bg-slate-50/40 px-5 py-3.5 lg:flex-row lg:items-center">
          <DatagridToolbarSearch
            v-model="filterForm.q"
            input-id="coaching-course-search"
            placeholder="Mã, tên khóa, học viên, coach…"
          />

          <div class="flex shrink-0 flex-wrap items-center gap-2">
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
                :columns="TABLE_COLUMNS"
                :fixed-labels="['Khóa học', 'Thao tác']"
                @persist="persistVisibleColumns"
              />
            </div>

            <div
              ref="exportDdRef"
              class="relative"
            >
              <button
                type="button"
                class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium transition select-none"
                :class="showExportDd
                  ? 'border-brand/40 bg-brand/5 text-brand'
                  : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-800'"
                title="Xuất danh sách"
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
                class="absolute right-0 z-30 mt-1 min-w-[9rem] rounded-card border border-slate-200 bg-white py-1 shadow-lg"
              >
                <button
                  type="button"
                  class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                  @click="doExportCsv"
                >
                  CSV
                </button>
                <button
                  type="button"
                  class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                  @click="doExportExcel"
                >
                  Excel
                </button>
              </div>
            </div>

            <div class="flex rounded-btn border border-slate-200 p-0.5">
              <button
                type="button"
                class="inline-flex h-8 items-center gap-1 rounded-btn px-2.5 text-xs font-medium transition"
                :class="viewMode === 'cards' ? 'bg-brand/10 text-brand' : 'text-slate-500 hover:text-slate-700'"
                title="Thẻ"
                @click="setViewMode('cards')"
              >
                <AppIcon
                  name="grid"
                  :size="14"
                />
                Thẻ
              </button>
              <button
                type="button"
                class="inline-flex h-8 items-center gap-1 rounded-btn px-2.5 text-xs font-medium transition"
                :class="viewMode === 'table' ? 'bg-brand/10 text-brand' : 'text-slate-500 hover:text-slate-700'"
                title="Bảng"
                @click="setViewMode('table')"
              >
                <AppIcon
                  name="list"
                  :size="14"
                />
                Bảng
              </button>
            </div>
          </div>
        </div>

        <div
          v-if="hasFilterRow"
          class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50/30 px-5 py-2.5"
        >
          <select
            v-if="visibleFilters.status"
            v-model="filterForm.status"
            class="input h-9 w-48 text-sm"
            aria-label="Trạng thái khóa học"
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
        </div>

        <!-- Cards -->
        <div
          v-if="viewMode === 'cards'"
          class="divide-y divide-slate-100"
        >
          <div
            v-if="!courseRows.length"
            class="px-5 py-12 text-center text-sm text-slate-500"
          >
            Không có khóa học phù hợp.
          </div>
          <div
            v-for="c in courseRows"
            :key="c.id"
            class="flex flex-col gap-3 px-5 py-4 transition hover:bg-brand/[0.02] sm:flex-row sm:items-center sm:justify-between"
          >
            <div class="min-w-0 flex-1">
              <span class="font-mono text-xs text-slate-400">{{ c.code }}</span>
              <h2 class="font-display font-semibold text-slate-800">
                {{ c.name }}
              </h2>
              <p class="mt-0.5 text-xs text-slate-500">
                <template v-if="c.student_display">
                  Học viên: {{ c.student_display }}
                </template>
                <template v-if="c.coach_display">
                  · Coach: {{ c.coach_display }}
                </template>
              </p>
              <p
                v-if="c.sessions_count != null"
                class="mt-1 text-xs text-slate-400"
              >
                {{ c.sessions_count }} buổi
              </p>
            </div>
            <div class="flex shrink-0 items-center gap-3">
              <Badge
                v-if="c.status"
                :label="c.status.label"
                :color="statusColor(c.status.value)"
              />
              <Link
                :href="route('coaching.courses.show', { course: c.id })"
                class="btn-ghost h-8 px-3 text-xs"
              >
                Chi tiết
              </Link>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div
          v-else
          class="overflow-x-auto"
        >
          <table class="w-full min-w-[48rem] text-left text-sm">
            <thead>
              <tr class="border-b border-slate-100 bg-slate-50/60 text-xs font-medium text-slate-500">
                <th class="px-5 py-3">
                  Khóa học
                </th>
                <th
                  v-if="isColVisible('code')"
                  class="px-3 py-3"
                >
                  Mã
                </th>
                <th
                  v-if="isColVisible('student')"
                  class="px-3 py-3"
                >
                  Học viên
                </th>
                <th
                  v-if="isColVisible('coach')"
                  class="px-3 py-3"
                >
                  Coach
                </th>
                <th
                  v-if="isColVisible('status')"
                  class="px-3 py-3"
                >
                  Trạng thái
                </th>
                <th
                  v-if="isColVisible('progress')"
                  class="px-3 py-3 text-right"
                >
                  Tiến độ
                </th>
                <th
                  v-if="isColVisible('sessions')"
                  class="px-3 py-3 text-right"
                >
                  Buổi
                </th>
                <th
                  v-if="isColVisible('dates')"
                  class="px-3 py-3"
                >
                  Thời gian
                </th>
                <th
                  v-if="isColVisible('fee')"
                  class="px-3 py-3 text-right"
                >
                  Học phí
                </th>
                <th class="px-3 py-3 text-right">
                  Thao tác
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-if="!courseRows.length"
              >
                <td
                  :colspan="totalCols"
                  class="px-5 py-12 text-center text-sm text-slate-500"
                >
                  Không có khóa học phù hợp.
                </td>
              </tr>
              <tr
                v-for="c in courseRows"
                :key="c.id"
                class="border-b border-slate-50 transition hover:bg-brand/[0.02]"
              >
                <td class="px-5 py-3">
                  <p class="font-medium text-slate-800">
                    {{ c.name }}
                  </p>
                </td>
                <td
                  v-if="isColVisible('code')"
                  class="px-3 py-3 font-mono text-xs text-slate-500"
                >
                  {{ c.code }}
                </td>
                <td
                  v-if="isColVisible('student')"
                  class="px-3 py-3 text-slate-600"
                >
                  {{ c.student_display || '—' }}
                </td>
                <td
                  v-if="isColVisible('coach')"
                  class="px-3 py-3 text-slate-600"
                >
                  {{ c.coach_display || '—' }}
                </td>
                <td
                  v-if="isColVisible('status')"
                  class="px-3 py-3"
                >
                  <Badge
                    v-if="c.status"
                    :label="c.status.label"
                    :color="statusColor(c.status.value)"
                  />
                </td>
                <td
                  v-if="isColVisible('progress')"
                  class="px-3 py-3 text-right text-slate-600"
                >
                  {{ c.progress_percent != null ? `${c.progress_percent}%` : '—' }}
                </td>
                <td
                  v-if="isColVisible('sessions')"
                  class="px-3 py-3 text-right text-slate-600"
                >
                  {{ c.sessions_count ?? '—' }}
                </td>
                <td
                  v-if="isColVisible('dates')"
                  class="px-3 py-3 text-xs text-slate-500"
                >
                  {{ date(c.start_date) }} – {{ date(c.end_date) }}
                </td>
                <td
                  v-if="isColVisible('fee')"
                  class="px-3 py-3 text-right text-slate-600"
                >
                  {{ currency(c.total_fee) }}
                </td>
                <td class="px-3 py-3 text-right">
                  <Link
                    :href="route('coaching.courses.show', { course: c.id })"
                    class="text-xs font-medium text-brand hover:underline"
                  >
                    Chi tiết
                  </Link>
                </td>
              </tr>
            </tbody>
            <DatagridPaginationFooter
              v-if="courses.meta"
              :meta="courses.meta"
              :per-page="perPage"
              :per-page-options="PER_PAGE_OPTIONS"
              :colspan="totalCols"
              @update:per-page="onPerPageChange"
            />
          </table>
        </div>

        <DatagridPaginationFooter
          v-if="viewMode === 'cards' && courses.meta"
          variant="bar"
          :meta="courses.meta"
          :per-page="perPage"
          :per-page-options="PER_PAGE_OPTIONS"
          @update:per-page="onPerPageChange"
        />
      </div>
    </CoachingWorkspace>

    <CoachingCourseFormModal
      :show="courseModal"
      :statuses="options.statuses"
      @close="courseModal = false"
    />
  </AppLayout>
</template>
