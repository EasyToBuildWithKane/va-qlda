<script setup>
import { reactive, ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import StatusBadge from '@/Components/DailyReport/StatusBadge.vue';
import GradePill from '@/Components/DailyReport/GradePill.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import { date as formatDate } from '@/composables/useFormat';

const PER_PAGE_OPTIONS = [5, 10, 15, 20];
const confirmDelete = useConfirmDelete();

const props = defineProps({
    reports: { type: Object, required: true }, // { data, meta, links }
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    grades: { type: Array, default: () => [] },
    projects: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    canFilterEmployee: { type: Boolean, default: false },
});

// ---- Filters --------------------------------------------------------------
const filterForm = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    project_id: props.filters.project_id ?? '',
    employee_id: props.filters.employee_id ?? '',
    grade: props.filters.grade ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

const perPage = ref(Number(props.filters.per_page) || props.reports.meta?.per_page || 10);

function routeParams(resetPage = false) {
    const params = Object.fromEntries(
        Object.entries({ ...filterForm, per_page: perPage.value }).filter(([, v]) => v !== '' && v != null),
    );
    if (resetPage) params.page = 1;
    return params;
}

const applyFilters = (resetPage = true) => {
    router.get('/daily-reports', routeParams(resetPage), {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    Object.keys(filterForm).forEach((k) => (filterForm[k] = ''));
    applyFilters(true);
};

function onPerPageChange(n) {
    perPage.value = n;
    applyFilters(true);
}

const activeCount = computed(() =>
    Object.values(filterForm).filter((v) => v !== '' && v != null).length,
);

// Debounce the keyword box; apply the rest immediately on change.
let kwTimer = null;
watch(() => filterForm.q, () => {
    clearTimeout(kwTimer);
    kwTimer = setTimeout(() => applyFilters(true), 350);
});

watch(
    () => [
        filterForm.status,
        filterForm.project_id,
        filterForm.employee_id,
        filterForm.grade,
        filterForm.from,
        filterForm.to,
    ],
    () => applyFilters(true),
);

const HISTORY_FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái' },
    { key: 'project', label: 'Dự án' },
    { key: 'employee', label: 'Người báo cáo' },
    { key: 'grade', label: 'Xếp loại' },
    { key: 'from', label: 'Từ ngày', default: false },
    { key: 'to', label: 'Đến ngày', default: false },
];

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(HISTORY_FILTER_CONTROLS, 'va-qlda.reports.visible-filters');

const filterPanelDdRef = ref(null);

// ---- Show / hide columns --------------------------------------------------
const COLS_KEY = 'va-qlda.reports.columns';
const columns = reactive([
    { key: 'date', label: 'Ngày', visible: true },
    { key: 'employee', label: 'Người báo cáo', visible: props.canFilterEmployee, manager: true },
    { key: 'title', label: 'Tiêu đề', visible: true },
    { key: 'status', label: 'Trạng thái', visible: true },
    { key: 'score', label: 'Điểm', visible: true },
    { key: 'feedback', label: 'Phản hồi', visible: true },
]);
const visible = (key) => columns.find((c) => c.key === key)?.visible ?? false;
const visibleCount = computed(() => columns.filter((c) => c.visible).length + 1); // +actions
const colsMenu = ref(false);

const persistColumns = () => {
    localStorage.setItem(
        COLS_KEY,
        JSON.stringify(Object.fromEntries(columns.map((c) => [c.key, c.visible]))),
    );
};

onMounted(() => {
    try {
        if (!props.canFilterEmployee) {
            visibleFilters.value.employee = false;
        }

        const saved = JSON.parse(localStorage.getItem(COLS_KEY) || 'null');
        if (saved) {
            if (saved.grade !== undefined && saved.score === undefined) {
                saved.score = saved.grade;
            }
            columns.forEach((c) => {
                if (c.key in saved && !(c.manager && !props.canFilterEmployee)) {
                    c.visible = !!saved[c.key];
                }
            });
        }
    } catch { /* ignore corrupt prefs */ }
    document.addEventListener('click', onDocClick);
});
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));

// Close dropdowns when clicking outside.
const colsRef = ref(null);
const onDocClick = (e) => {
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) showFilterPanelDd.value = false;
    if (colsMenu.value && colsRef.value && !colsRef.value.contains(e.target)) colsMenu.value = false;
};

const removeReport = (r) => {
    confirmDelete(
        `Xoá báo cáo nháp "${r.title}" (${r.date})? Thao tác không thể hoàn tác.`,
        () => router.delete(`/daily-reports/${r.id}`, { preserveScroll: true }),
    );
};

function hasFeedback(r) {
    if (r.has_feedback) return true;
    if (r.status === 'draft' && (r.review_notes || '').trim()) return true;
    return Boolean((r.score?.notes || '').trim());
}

</script>

<template>
  <Head title="Lịch sử báo cáo" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Lịch sử báo cáo"
        subtitle="Xem lại tất cả báo cáo đã nộp"
        icon="report-history"
        icon-color="sky"
        :badge="reports.meta?.total"
      />
    </template>

    <!-- Toolbar -->
    <div class="card mb-4 overflow-visible">
      <div class="border-b border-slate-100 px-5 py-3">
        <div class="flex flex-wrap items-center gap-2">
          <DatagridToolbarSearch
            v-model="filterForm.q"
            input-id="daily-reports-search"
            placeholder="Tiêu đề, người báo cáo, dự án…"
          />

          <div
            ref="filterPanelDdRef"
            class="relative shrink-0"
          >
            <button
              type="button"
              class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium transition select-none"
              :class="showFilterPanelDd
                ? 'border-brand/40 bg-brand/5 text-brand'
                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
              :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
              @click="openFilterPanel(() => { colsMenu.value = false; })"
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
              :controls="FILTER_CONTROLS.filter((f) => f.key !== 'employee' || canFilterEmployee)"
              @persist="persistVisibleFilters"
            />
          </div>

          <div
            ref="colsRef"
            class="relative shrink-0"
          >
            <button
              type="button"
              class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium transition select-none"
              :class="colsMenu
                ? 'border-brand/40 bg-brand/5 text-brand'
                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
              title="Cột hiển thị"
              @click="colsMenu = !colsMenu"
            >
              <AppIcon
                name="columns"
                :size="15"
              />
              <span>Cột</span>
            </button>
            <div
              v-if="colsMenu"
              class="absolute right-0 z-20 mt-1 w-56 rounded-card border border-slate-200 bg-white p-1.5 shadow-elevation-2"
            >
              <p class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                Hiển thị cột
              </p>
              <label
                v-for="c in columns"
                :key="c.key"
                class="flex cursor-pointer items-center gap-2 rounded-btn px-2 py-1.5 text-sm text-slate-600 hover:bg-slate-50"
                :class="{ 'opacity-40 pointer-events-none': c.manager && !canFilterEmployee }"
              >
                <input
                  v-model="c.visible"
                  type="checkbox"
                  class="rounded"
                  @change="persistColumns"
                >
                {{ c.label }}
              </label>
            </div>
          </div>
        </div>
      </div>

      <Transition name="fade-slide">
        <div
          v-if="hasFilterRow"
          class="flex flex-wrap items-center gap-2 border-t border-slate-100 px-5 py-3"
        >
          <select
            v-if="visibleFilters.status"
            v-model="filterForm.status"
            class="input h-9 w-44 text-sm"
            aria-label="Trạng thái"
            title="Lọc theo trạng thái duyệt"
          >
            <option value="">
              Trạng thái: Tất cả
            </option>
            <option
              v-for="s in statuses"
              :key="s.value"
              :value="s.value"
            >
              {{ s.label }}
            </option>
          </select>

          <select
            v-if="visibleFilters.project"
            v-model="filterForm.project_id"
            class="input h-9 min-w-[11rem] text-sm sm:w-52"
            aria-label="Dự án"
            title="Lọc báo cáo theo dự án"
          >
            <option value="">
              Dự án: Tất cả
            </option>
            <option
              v-for="p in projects"
              :key="p.id"
              :value="p.id"
            >
              {{ p.name }}
            </option>
          </select>

          <select
            v-if="visibleFilters.employee && canFilterEmployee"
            v-model="filterForm.employee_id"
            class="input h-9 min-w-[10rem] text-sm sm:w-48"
            aria-label="Người báo cáo"
            title="Lọc theo người gửi báo cáo"
          >
            <option value="">
              Người báo cáo: Tất cả
            </option>
            <option
              v-for="e in employees"
              :key="e.id"
              :value="e.id"
            >
              {{ e.name }}
            </option>
          </select>

          <select
            v-if="visibleFilters.grade"
            v-model="filterForm.grade"
            class="input h-9 w-40 text-sm"
            aria-label="Xếp loại"
            title="Lọc theo xếp loại điểm"
          >
            <option value="">
              Xếp loại: Tất cả
            </option>
            <option
              v-for="g in grades"
              :key="g.value"
              :value="g.value"
            >
              {{ g.label }}
            </option>
          </select>

          <input
            v-if="visibleFilters.from"
            v-model="filterForm.from"
            type="date"
            class="input h-9 w-40 text-sm"
            aria-label="Từ ngày"
            title="Chỉ lấy báo cáo từ ngày này"
          >

          <input
            v-if="visibleFilters.to"
            v-model="filterForm.to"
            type="date"
            class="input h-9 w-40 text-sm"
            aria-label="Đến ngày"
            title="Chỉ lấy báo cáo đến ngày này"
          >

          <button
            v-if="activeCount"
            type="button"
            class="text-xs font-medium text-brand hover:underline"
            @click="clearFilters"
          >
            Đặt lại
          </button>
        </div>
      </Transition>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="border-b border-slate-200 bg-slate-50/80 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th
                v-if="visible('date')"
                class="whitespace-nowrap px-4 py-2.5"
              >
                Ngày
              </th>
              <th
                v-if="visible('employee')"
                class="px-4 py-2.5"
              >
                Người báo cáo
              </th>
              <th
                v-if="visible('title')"
                class="min-w-[12rem] px-4 py-2.5"
              >
                Tiêu đề
              </th>
              <th
                v-if="visible('status')"
                class="whitespace-nowrap px-4 py-2.5"
              >
                Trạng thái
              </th>
              <th
                v-if="visible('score')"
                class="whitespace-nowrap px-4 py-2.5"
              >
                Điểm
              </th>
              <th
                v-if="visible('feedback')"
                class="w-16 px-2 py-2.5 text-center"
                title="Có phản hồi từ người duyệt"
              >
                PB
              </th>
              <th class="whitespace-nowrap px-4 py-2.5 text-right">
                Thao tác
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="r in reports.data"
              :key="r.id"
              class="transition hover:bg-slate-50/80"
            >
              <td
                v-if="visible('date')"
                class="whitespace-nowrap px-4 py-3 align-middle text-slate-600"
              >
                <span class="tabular-nums">{{ formatDate(r.date) }}</span>
                <span
                  v-if="r.is_late"
                  class="ml-1.5 rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold text-danger"
                >Trễ</span>
              </td>
              <td
                v-if="visible('employee')"
                class="max-w-[10rem] truncate px-4 py-3 align-middle text-slate-600"
                :title="r.employee?.name"
              >
                {{ r.employee?.name ?? '—' }}
              </td>
              <td
                v-if="visible('title')"
                class="max-w-md px-4 py-3 align-middle"
              >
                <Link
                  :href="`/daily-reports/${r.id}`"
                  class="line-clamp-2 font-medium text-slate-800 hover:text-brand"
                  :title="r.title"
                >
                  {{ r.title }}
                </Link>
              </td>
              <td
                v-if="visible('status')"
                class="whitespace-nowrap px-4 py-3 align-middle"
              >
                <StatusBadge
                  :label="r.status_label"
                  :color="r.status_color"
                />
              </td>
              <td
                v-if="visible('score')"
                class="whitespace-nowrap px-4 py-3 align-middle"
              >
                <div
                  v-if="r.score"
                  class="inline-flex items-center gap-2"
                >
                  <GradePill
                    :grade="r.score.grade"
                    :color="r.score.grade_color"
                  />
                  <span class="text-xs font-semibold tabular-nums text-slate-600">
                    {{ Number(r.score.total_score ?? 0).toFixed(1) }}
                  </span>
                </div>
                <span
                  v-else
                  class="text-slate-300"
                >—</span>
              </td>
              <td
                v-if="visible('feedback')"
                class="px-2 py-3 text-center align-middle"
              >
                <span
                  v-if="hasFeedback(r)"
                  class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-amber-50 text-amber-600"
                  title="Có phản hồi — mở chi tiết để xem"
                >
                  <AppIcon
                    name="message"
                    :size="14"
                  />
                </span>
                <span
                  v-else
                  class="text-slate-200"
                  aria-hidden="true"
                >·</span>
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-right align-middle">
                <div class="inline-flex items-center justify-end gap-1">
                  <Link
                    :href="`/daily-reports/${r.id}`"
                    class="inline-flex h-8 items-center rounded-btn px-2.5 text-xs font-medium text-brand hover:bg-brand/5"
                  >
                    Chi tiết
                  </Link>
                  <button
                    v-if="r.can?.delete"
                    type="button"
                    class="inline-flex h-8 items-center rounded-btn px-2 text-xs font-medium text-danger hover:bg-rose-50"
                    title="Xoá bản nháp"
                    @click="removeReport(r)"
                  >
                    Xoá
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="reports.data.length === 0">
              <td
                :colspan="visibleCount"
                class="px-4 py-14 text-center text-slate-400"
              >
                Không tìm thấy báo cáo nào khớp bộ lọc.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <DatagridPaginationFooter
        v-if="reports.meta?.total"
        variant="bar"
        :meta="reports.meta"
        :per-page="perPage"
        :per-page-options="PER_PAGE_OPTIONS"
        @update:per-page="onPerPageChange"
      />
    </div>
  </AppLayout>
</template>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.fade-slide-enter-from,
.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>
