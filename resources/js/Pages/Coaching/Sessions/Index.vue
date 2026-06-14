<script setup>
import { ref, computed, reactive, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import CoachingWorkspace from '@/modules/coaching/components/CoachingWorkspace.vue';
import CoachingSessionFormModal from '@/modules/coaching/components/CoachingSessionFormModal.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { date, hours as fmtHours } from '@/composables/useFormat';

const PER_PAGE_OPTIONS = [10, 15, 20, 30];

const props = defineProps({
    sessions: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    selectedCourse: { type: Object, default: null },
});

const sessionModal = ref(false);

const SESSION_COLUMNS = [
    { key: 'course', label: 'Khóa học', default: true },
    { key: 'date', label: 'Ngày', default: true },
    { key: 'hours', label: 'Giờ', default: true },
    { key: 'status', label: 'Trạng thái', default: true },
    { key: 'topic', label: 'Chủ đề', default: false },
    { key: 'materials', label: 'Tài liệu', default: false },
    { key: 'assignments', label: 'Bài tập', default: false },
];

const {
    visibleCols, showColDd, persistVisibleColumns, openColPanel, isColVisible,
} = useVisibleColumns(SESSION_COLUMNS, 'va-qlda.coaching.sessions.columns');

const FILTER_CONTROLS_DEF = [
    { key: 'status', label: 'Trạng thái', default: true },
    { key: 'course', label: 'Khóa học', default: true },
];
const {
    visibleFilters, showFilterPanelDd, enabledFilterControlCount, hasFilterRow,
    persistVisibleFilters, openFilterPanel, FILTER_CONTROLS,
} = useVisibleFilterControls(FILTER_CONTROLS_DEF, 'va-qlda.coaching.sessions.visible-filters');

const filterForm = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    course: props.filters.course ?? '',
});

const perPage = ref(Number(props.filters.per_page) || props.sessions.meta?.per_page || 20);
const sessionRows = computed(() => props.sessions.data ?? []);

function sessionStatusColor(value) {
    if (value === 'in_progress') return 'amber';
    if (value === 'completed') return 'emerald';
    if (value === 'cancelled') return 'rose';
    return 'slate';
}

function routeParams(resetPage = false) {
    const params = Object.fromEntries(
        Object.entries({ ...filterForm, per_page: perPage.value }).filter(([, v]) => v !== '' && v != null),
    );
    if (resetPage) params.page = 1;
    return params;
}

function load(resetPage = true) {
    router.get(route('coaching.sessions.index'), routeParams(resetPage), {
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
watch(() => filterForm.course, () => load(true));

function onPerPageChange(n) {
    perPage.value = n;
    load(true);
}

const canAddSession = computed(() => props.selectedCourse?.can?.update === true);

const filterDdRef = ref(null);
const colDdRef = ref(null);
const onDocClick = (e) => {
    if (filterDdRef.value && !filterDdRef.value.contains(e.target)) showFilterPanelDd.value = false;
    if (colDdRef.value && !colDdRef.value.contains(e.target)) showColDd.value = false;
};
onMounted(() => document.addEventListener('mousedown', onDocClick));
onUnmounted(() => document.removeEventListener('mousedown', onDocClick));

const openFilter = () => openFilterPanel(() => { showColDd.value = false; });
const openCol = () => openColPanel(() => { showFilterPanelDd.value = false; });
</script>

<template>
  <Head title="Danh sách buổi học" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Danh sách buổi học"
        subtitle="Tìm kiếm, lọc và mở chi tiết từng buổi"
        icon="weekly"
        back-href="/coaching"
      >
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

    <CoachingWorkspace>
      <section class="card overflow-visible">
        <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/60 px-5 py-3.5 lg:flex-row lg:items-center">
          <DatagridToolbarSearch
            v-model="filterForm.q"
            input-id="coaching-session-search"
            placeholder="Tên buổi, chủ đề, khóa học…"
          />
          <div class="flex flex-wrap items-center gap-2">
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
            class="input h-9 min-w-[12rem] max-w-md text-sm"
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
        </div>

        <div
          v-if="selectedCourse"
          class="border-b border-brand/10 bg-brand/[0.03] px-5 py-2 text-xs text-slate-600"
        >
          Đang lọc theo khóa
          <span class="font-medium text-slate-800">{{ selectedCourse.name }}</span>
          <Link
            :href="route('coaching.sessions.index')"
            class="ml-2 font-medium text-brand hover:underline"
          >
            Bỏ lọc
          </Link>
        </div>

        <div
          v-if="!sessionRows.length"
          class="px-6 py-12 text-center text-sm text-slate-500"
        >
          Không có buổi học phù hợp.
          <span v-if="!filterForm.course"> Chọn khóa học trong bộ lọc để thêm buổi mới.</span>
        </div>

        <div
          v-else
          class="overflow-x-auto"
        >
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="border-b border-slate-100 bg-slate-50/50 text-xs font-medium text-slate-500">
                <th class="w-12 px-5 py-3">
                  #
                </th>
                <th class="px-4 py-3">
                  Tên buổi
                </th>
                <th
                  v-if="isColVisible('course')"
                  class="px-4 py-3"
                >
                  Khóa học
                </th>
                <th
                  v-if="isColVisible('date')"
                  class="px-4 py-3"
                >
                  Ngày
                </th>
                <th
                  v-if="isColVisible('hours')"
                  class="px-4 py-3 text-right"
                >
                  Giờ
                </th>
                <th
                  v-if="isColVisible('status')"
                  class="px-4 py-3"
                >
                  Trạng thái
                </th>
                <th
                  v-if="isColVisible('topic')"
                  class="px-4 py-3"
                >
                  Chủ đề
                </th>
                <th
                  v-if="isColVisible('materials')"
                  class="px-4 py-3 text-right"
                >
                  Tài liệu
                </th>
                <th
                  v-if="isColVisible('assignments')"
                  class="px-4 py-3 text-right"
                >
                  Bài tập
                </th>
                <th class="px-4 py-3 text-right">
                  Thao tác
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="s in sessionRows"
                :key="s.id"
                class="border-b border-slate-50 transition hover:bg-brand/[0.02]"
              >
                <td class="px-5 py-3 font-mono text-xs text-slate-400">
                  {{ s.session_number }}
                </td>
                <td class="px-4 py-3 font-medium text-slate-800">
                  {{ s.title }}
                </td>
                <td
                  v-if="isColVisible('course')"
                  class="px-4 py-3"
                >
                  <Link
                    v-if="s.course"
                    :href="route('coaching.courses.show', { course: s.course.id })"
                    class="text-slate-700 hover:text-brand"
                  >
                    <span class="font-mono text-xs text-slate-400">{{ s.course.code }}</span>
                    <span class="ml-1">{{ s.course.name }}</span>
                  </Link>
                  <span v-else>—</span>
                </td>
                <td
                  v-if="isColVisible('date')"
                  class="px-4 py-3 text-slate-600"
                >
                  {{ date(s.date) }}
                </td>
                <td
                  v-if="isColVisible('hours')"
                  class="px-4 py-3 text-right text-slate-600"
                >
                  {{ s.total_hours != null ? fmtHours(s.total_hours) : '—' }}
                </td>
                <td
                  v-if="isColVisible('status')"
                  class="px-4 py-3"
                >
                  <Badge
                    v-if="s.status"
                    :label="s.status.label"
                    :color="sessionStatusColor(s.status.value)"
                  />
                </td>
                <td
                  v-if="isColVisible('topic')"
                  class="max-w-[16rem] truncate px-4 py-3 text-slate-600"
                >
                  {{ s.topic || '—' }}
                </td>
                <td
                  v-if="isColVisible('materials')"
                  class="px-4 py-3 text-right text-slate-600"
                >
                  {{ s.materials_count ?? s.materials?.length ?? 0 }}
                </td>
                <td
                  v-if="isColVisible('assignments')"
                  class="px-4 py-3 text-right text-slate-600"
                >
                  {{ s.assignments_count ?? s.assignments?.length ?? 0 }}
                </td>
                <td class="px-4 py-3 text-right">
                  <Link
                    :href="route('coaching.sessions.show', { session: s.id })"
                    class="text-xs font-medium text-brand hover:underline"
                  >
                    Chi tiết
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <DatagridPaginationFooter
          v-if="sessions.meta"
          :meta="sessions.meta"
          :per-page="perPage"
          :per-page-options="PER_PAGE_OPTIONS"
          @per-page-change="onPerPageChange"
          @page-change="(p) => router.get(route('coaching.sessions.index'), { ...routeParams(false), page: p }, { preserveScroll: true })"
        />
      </section>
    </CoachingWorkspace>

    <CoachingSessionFormModal
      v-if="selectedCourse"
      :show="sessionModal"
      :course-id="selectedCourse.id"
      :next-session-number="selectedCourse.next_session_number"
      @close="sessionModal = false"
    />
  </AppLayout>
</template>
