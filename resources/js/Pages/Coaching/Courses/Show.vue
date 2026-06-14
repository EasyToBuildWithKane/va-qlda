<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import CoachingWorkspace from '@/modules/coaching/components/CoachingWorkspace.vue';
import CoachingSessionFormModal from '@/modules/coaching/components/CoachingSessionFormModal.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { currency, date, hours as fmtHours } from '@/composables/useFormat';

const props = defineProps({
    course: { type: Object, required: true },
    nextSessionNumber: { type: Number, default: 1 },
    sessionStatuses: { type: Array, default: () => [] },
});

const sessionModal = ref(false);

const sessions = computed(() => props.course.sessions ?? []);

const courseStatusColor = computed(() => {
    const v = props.course.status?.value;
    if (v === 'active') return 'emerald';
    if (v === 'completed') return 'sky';
    if (v === 'cancelled') return 'rose';
    return 'slate';
});

function sessionStatusColor(value) {
    if (value === 'in_progress') return 'amber';
    if (value === 'completed') return 'emerald';
    if (value === 'cancelled') return 'rose';
    return 'slate';
}

/* ---- Sessions datagrid (client-side) ---- */
const SESSION_COLUMNS = [
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

const FILTER_CONTROLS_DEF = [{ key: 'status', label: 'Trạng thái', default: true }];
const {
    visibleFilters, showFilterPanelDd, enabledFilterControlCount, hasFilterRow,
    persistVisibleFilters, openFilterPanel, FILTER_CONTROLS,
} = useVisibleFilterControls(FILTER_CONTROLS_DEF, 'va-qlda.coaching.sessions.visible-filters');

const filterForm = reactive({ q: '', status: '' });

const filteredSessions = computed(() => {
    const term = filterForm.q.trim().toLowerCase();
    return sessions.value.filter((s) => {
        if (filterForm.status && s.status?.value !== filterForm.status) return false;
        if (!term) return true;
        return [s.title, s.topic, String(s.session_number)]
            .filter(Boolean)
            .some((v) => String(v).toLowerCase().includes(term));
    });
});

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
  <Head :title="course.name" />
  <AppLayout>
    <template #header>
      <PageHeader
        :title="course.name"
        :subtitle="course.code"
        icon="knowledge"
        back-href="/coaching/courses"
      >
        <Link
          v-if="course.can?.update"
          :href="route('coaching.courses.edit', { course: course.id })"
          class="btn-ghost h-9 px-3 text-sm"
        >
          Sửa khóa
        </Link>
        <button
          v-if="course.can?.update"
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
      <!-- KPI -->
      <div class="mb-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div class="card p-4">
          <p class="text-xs font-medium text-slate-500">
            Trạng thái
          </p>
          <div class="mt-2">
            <Badge
              v-if="course.status"
              :label="course.status.label"
              :color="courseStatusColor"
            />
          </div>
        </div>
        <div class="card p-4">
          <p class="text-xs font-medium text-slate-500">
            Tiến độ hoàn thành
          </p>
          <p class="mt-1 font-display text-2xl font-semibold text-brand">
            {{ course.progress_percent ?? 0 }}%
          </p>
          <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
            <div
              class="h-full bg-brand transition-all"
              :style="{ width: `${course.progress_percent ?? 0}%` }"
            />
          </div>
        </div>
        <div class="card p-4">
          <p class="text-xs font-medium text-slate-500">
            Số buổi học
          </p>
          <p class="mt-1 font-display text-2xl font-semibold text-slate-800">
            {{ course.sessions_count ?? sessions.length }}
          </p>
        </div>
        <div class="card p-4">
          <p class="text-xs font-medium text-slate-500">
            Học phí dự kiến
          </p>
          <p class="mt-1 font-display text-lg font-semibold text-slate-800">
            {{ currency(course.total_fee) }}
          </p>
        </div>
      </div>

      <div class="grid gap-5 xl:grid-cols-3">
        <!-- Thông tin -->
        <section class="card overflow-hidden xl:col-span-2">
          <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3.5">
            <h2 class="font-display text-sm font-semibold text-slate-800">
              Thông tin khóa học
            </h2>
          </div>
          <dl class="grid gap-4 p-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
              <dt class="text-xs font-medium text-slate-500">
                Mô tả
              </dt>
              <dd class="mt-1 whitespace-pre-wrap text-sm text-slate-700">
                {{ course.description || '—' }}
              </dd>
            </div>
            <div class="sm:col-span-2">
              <dt class="text-xs font-medium text-slate-500">
                Mục tiêu
              </dt>
              <dd class="mt-1 whitespace-pre-wrap font-mono text-sm text-slate-700">
                {{ course.objectives || '—' }}
              </dd>
            </div>
            <div>
              <dt class="text-xs font-medium text-slate-500">
                Ngày bắt đầu
              </dt>
              <dd class="mt-1 text-sm text-slate-800">
                {{ date(course.start_date) }}
              </dd>
            </div>
            <div>
              <dt class="text-xs font-medium text-slate-500">
                Ngày kết thúc
              </dt>
              <dd class="mt-1 text-sm text-slate-800">
                {{ date(course.end_date) }}
              </dd>
            </div>
            <div>
              <dt class="text-xs font-medium text-slate-500">
                Đơn giá / giờ
              </dt>
              <dd class="mt-1 text-sm text-slate-800">
                {{ currency(course.hourly_rate) }}
              </dd>
            </div>
            <div>
              <dt class="text-xs font-medium text-slate-500">
                Tổng giờ dự kiến
              </dt>
              <dd class="mt-1 text-sm text-slate-800">
                {{ course.total_hours != null ? fmtHours(course.total_hours) : '—' }}
              </dd>
            </div>
          </dl>
        </section>

        <!-- Người tham gia -->
        <section class="card overflow-hidden">
          <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3.5">
            <h2 class="font-display text-sm font-semibold text-slate-800">
              Học viên &amp; Coach
            </h2>
          </div>
          <div class="space-y-4 p-5">
            <div>
              <p class="text-xs font-medium text-slate-500">
                Học viên
              </p>
              <p class="mt-1 text-sm font-medium text-slate-800">
                {{ course.student_display || '—' }}
              </p>
            </div>
            <div>
              <p class="text-xs font-medium text-slate-500">
                Coach / Mentor
              </p>
              <p class="mt-1 text-sm font-medium text-slate-800">
                {{ course.coach_display || '—' }}
              </p>
            </div>
          </div>
        </section>
      </div>

      <!-- Lịch buổi học (datagrid) -->
      <section class="mt-5 card overflow-visible">
        <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/60 px-5 py-3.5 sm:flex-row sm:items-center">
          <div class="min-w-0 flex-1">
            <h2 class="font-display text-sm font-semibold text-slate-800">
              Lịch buổi học
            </h2>
            <p class="mt-0.5 text-xs text-slate-500">
              Bấm vào buổi để xem tài liệu, bài tập và ghi chú.
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <DatagridToolbarSearch
              v-model="filterForm.q"
              input-id="coaching-session-search"
              placeholder="Tên buổi, chủ đề…"
              compact
            />
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
            <button
              v-if="course.can?.update"
              type="button"
              class="inline-flex h-9 items-center gap-1 rounded-btn border border-brand/25 bg-brand/5 px-2.5 text-xs font-medium text-brand hover:bg-brand/10"
              @click="sessionModal = true"
            >
              <AppIcon
                name="add"
                :size="14"
              />
              Thêm buổi
            </button>
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
              v-for="s in sessionStatuses"
              :key="s.value"
              :value="s.value"
            >
              {{ s.label }}
            </option>
          </select>
        </div>

        <div
          v-if="!sessions.length"
          class="px-6 py-10 text-center text-sm text-slate-500"
        >
          Chưa có buổi học.
          <button
            v-if="course.can?.update"
            type="button"
            class="ml-1 font-medium text-brand hover:underline"
            @click="sessionModal = true"
          >
            Thêm buổi đầu tiên
          </button>
        </div>
        <div
          v-else-if="!filteredSessions.length"
          class="px-6 py-10 text-center text-sm text-slate-500"
        >
          Không có buổi học phù hợp bộ lọc.
        </div>

        <div
          v-else
          class="overflow-x-auto"
        >
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="border-b border-slate-100 bg-slate-50/50 text-xs font-medium text-slate-500">
                <th class="px-5 py-3 w-12">
                  #
                </th>
                <th class="px-4 py-3">
                  Tên buổi
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
                v-for="s in filteredSessions"
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
                  {{ s.materials?.length ?? 0 }}
                </td>
                <td
                  v-if="isColVisible('assignments')"
                  class="px-4 py-3 text-right text-slate-600"
                >
                  {{ s.assignments?.length ?? 0 }}
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
      </section>
    </CoachingWorkspace>

    <CoachingSessionFormModal
      :show="sessionModal"
      :course-id="course.id"
      :next-session-number="nextSessionNumber"
      @close="sessionModal = false"
    />
  </AppLayout>
</template>
