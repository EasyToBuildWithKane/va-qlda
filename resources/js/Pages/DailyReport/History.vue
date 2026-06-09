<script setup>
import { reactive, ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import StatusBadge from '@/Components/DailyReport/StatusBadge.vue';
import GradePill from '@/Components/DailyReport/GradePill.vue';
import HistoryKpiStrip from '@/Components/DailyReport/HistoryKpiStrip.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import { useToast } from '@/shared/composables/useToast';
import { date as formatDate } from '@/composables/useFormat';
import { exportDailyReportHistory } from '@/modules/daily-report/composables/useDailyReportHistoryExport';

const PER_PAGE_OPTIONS = [5, 10, 15, 20];
const VIEW_KEY = 'va-qlda.reports.view';
const confirmDelete = useConfirmDelete();
const toast = useToast();

const props = defineProps({
    reports: { type: Object, required: true },
    summary: {
        type: Object,
        default: () => ({
            total: 0, draft: 0, submitted: 0, reviewed: 0, late: 0,
        }),
    },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    grades: { type: Array, default: () => [] },
    projects: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    canFilterEmployee: { type: Boolean, default: false },
    canReview: { type: Boolean, default: false },
});

const filterForm = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    project_id: props.filters.project_id ?? '',
    employee_id: props.filters.employee_id ?? '',
    grade: props.filters.grade ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    late: Boolean(props.filters.late),
});

const perPage = ref(Number(props.filters.per_page) || props.reports.meta?.per_page || 10);
const viewMode = ref('cards');
const collapsedDates = ref({});
const exportMenu = ref(false);
const exportRef = ref(null);

function routeParams(resetPage = false) {
    const params = Object.fromEntries(
        Object.entries({
            ...filterForm,
            per_page: perPage.value,
            late: filterForm.late ? '1' : '',
        }).filter(([, v]) => v !== '' && v != null && v !== false),
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
    Object.assign(filterForm, {
        q: '', status: '', project_id: '', employee_id: '', grade: '', from: '', to: '', late: false,
    });
    applyFilters(true);
};

function onPerPageChange(n) {
    perPage.value = n;
    applyFilters(true);
}

const activeCount = computed(() =>
    Object.entries(filterForm).filter(([k, v]) => {
        if (k === 'late') return v === true;
        return v !== '' && v != null;
    }).length,
);

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
        filterForm.late,
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
const colsRef = ref(null);
const colsMenu = ref(false);

const COLS_KEY = 'va-qlda.reports.columns';
const columns = reactive([
    { key: 'employee', label: 'Người báo cáo', visible: props.canFilterEmployee, manager: true },
    { key: 'title', label: 'Tiêu đề', visible: true },
    { key: 'projects', label: 'Dự án', visible: true },
    { key: 'status', label: 'Trạng thái', visible: true },
    { key: 'score', label: 'Điểm', visible: true },
    { key: 'feedback', label: 'Phản hồi', visible: true },
]);
const visible = (key) => columns.find((c) => c.key === key)?.visible ?? false;
const visibleColumnKeys = computed(() => columns.filter((c) => c.visible).map((c) => c.key));

const groupedByDate = computed(() => {
    const order = [];
    const map = new Map();
    for (const r of props.reports.data ?? []) {
        const d = r.date;
        if (!map.has(d)) {
            map.set(d, []);
            order.push(d);
        }
        map.get(d).push(r);
    }
    return order.map((date) => ({
        date,
        items: [...map.get(date)].sort((a, b) =>
            (a.employee?.name ?? '').localeCompare(b.employee?.name ?? '', 'vi'),
        ),
    }));
});

const tableRows = computed(() => [...(props.reports.data ?? [])]);

function projectChips(report) {
    return (report.projects ?? [])
        .map((p) => p?.code || p?.name)
        .filter(Boolean)
        .slice(0, 3);
}

function isDayCollapsed(date) {
    return collapsedDates.value[date] === true;
}

function toggleDay(date) {
    collapsedDates.value[date] = !collapsedDates.value[date];
}

function setViewMode(mode) {
    viewMode.value = mode;
    localStorage.setItem(VIEW_KEY, mode);
}

function onKpiStatus(status) {
    filterForm.late = false;
    filterForm.status = status;
}

function onKpiToggleLate() {
    filterForm.late = !filterForm.late;
    if (filterForm.late) filterForm.status = '';
}

const persistColumns = () => {
    localStorage.setItem(
        COLS_KEY,
        JSON.stringify(Object.fromEntries(columns.map((c) => [c.key, c.visible]))),
    );
};

function runExport(format) {
    exportMenu.value = false;
    const rows = props.reports.data ?? [];
    if (!rows.length) {
        toast.warning('Không có dữ liệu trên trang hiện tại để xuất.');
        return;
    }
    const name = exportDailyReportHistory({
        rows,
        visibleColumnKeys: visibleColumnKeys.value,
        filters: { ...filterForm, ...routeParams(false) },
        meta: props.reports.meta,
        format,
    });
    if (name) toast.success(`Đã tải ${name}`);
}

onMounted(() => {
    try {
        const savedView = localStorage.getItem(VIEW_KEY);
        if (savedView === 'cards' || savedView === 'table') viewMode.value = savedView;

        if (!props.canFilterEmployee) visibleFilters.value.employee = false;

        const saved = JSON.parse(localStorage.getItem(COLS_KEY) || 'null');
        if (saved) {
            if (saved.grade !== undefined && saved.score === undefined) saved.score = saved.grade;
            columns.forEach((c) => {
                if (c.key in saved && !(c.manager && !props.canFilterEmployee)) {
                    c.visible = !!saved[c.key];
                }
            });
        }
    } catch { /* ignore */ }
    document.addEventListener('click', onDocClick);
});
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));

const onDocClick = (e) => {
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) showFilterPanelDd.value = false;
    if (colsMenu.value && colsRef.value && !colsRef.value.contains(e.target)) colsMenu.value = false;
    if (exportMenu.value && exportRef.value && !exportRef.value.contains(e.target)) exportMenu.value = false;
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
        subtitle="Theo dõi báo cáo HORENSO theo ngày và trạng thái duyệt"
        icon="report-history"
        icon-color="sky"
        :badge="summary.total ?? reports.meta?.total"
      >
        <div class="flex shrink-0 flex-wrap items-center gap-2">
          <Link
            href="/daily-reports/today"
            class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-xs font-semibold"
          >
            <AppIcon
              name="report-today"
              :size="15"
            />
            Báo cáo hôm nay
          </Link>
          <Link
            v-if="canReview"
            href="/daily-reports/review"
            class="inline-flex h-9 items-center gap-1.5 rounded-btn border border-violet-200 bg-violet-50 px-3 text-xs font-medium text-violet-800 hover:bg-violet-100"
          >
            <AppIcon
              name="review-reports"
              :size="15"
            />
            Duyệt báo cáo
          </Link>
        </div>
      </PageHeader>
    </template>

    <HistoryKpiStrip
      :summary="summary"
      :status-filter="filterForm.status"
      :late-filter="filterForm.late"
      @set-status="onKpiStatus"
      @toggle-late="onKpiToggleLate"
    />

    <div class="card mb-4 overflow-visible">
      <div class="border-b border-slate-100 px-5 py-3">
        <div class="flex flex-wrap items-center gap-2">
          <DatagridToolbarSearch
            v-model="filterForm.q"
            input-id="daily-reports-search"
            placeholder="Tiêu đề, tên nhân viên…"
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
              @click="openFilterPanel(() => { colsMenu = false; exportMenu = false; })"
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
              @click="colsMenu = !colsMenu; exportMenu = false"
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

          <div
            ref="exportRef"
            class="relative shrink-0"
          >
            <button
              type="button"
              class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium transition select-none"
              :class="exportMenu
                ? 'border-brand/40 bg-brand/5 text-brand'
                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
              title="Xuất danh sách trang hiện tại"
              @click="exportMenu = !exportMenu; colsMenu = false"
            >
              <AppIcon
                name="export"
                :size="15"
              />
              <span>Xuất</span>
            </button>
            <div
              v-if="exportMenu"
              class="absolute right-0 z-20 mt-1 w-40 rounded-card border border-slate-200 bg-white p-1 shadow-elevation-2"
            >
              <button
                type="button"
                class="flex w-full rounded-btn px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
                @click="runExport('csv')"
              >
                CSV
              </button>
              <button
                type="button"
                class="flex w-full rounded-btn px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
                @click="runExport('xlsx')"
              >
                Excel
              </button>
            </div>
          </div>

          <div class="ml-auto flex shrink-0 rounded-btn border border-slate-200 p-0.5">
            <button
              type="button"
              class="inline-flex h-8 items-center gap-1 rounded-btn px-2.5 text-xs font-medium transition"
              :class="viewMode === 'cards' ? 'bg-brand/10 text-brand' : 'text-slate-500 hover:text-slate-700'"
              title="Nhóm theo ngày — thẻ ngang"
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
              title="Bảng danh sách"
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
          >

          <input
            v-if="visibleFilters.to"
            v-model="filterForm.to"
            type="date"
            class="input h-9 w-40 text-sm"
            aria-label="Đến ngày"
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

    <div class="card overflow-hidden">
      <div
        v-if="reports.data.length === 0"
        class="flex flex-col items-center gap-3 px-4 py-16 text-center"
      >
        <AppIcon
          name="report-history"
          :size="40"
          class="text-slate-300"
        />
        <p class="text-sm text-slate-500">
          Không tìm thấy báo cáo khớp bộ lọc.
        </p>
        <Link
          href="/daily-reports/today"
          class="text-sm font-medium text-brand hover:underline"
        >
          Tạo báo cáo hôm nay
        </Link>
      </div>

      <!-- Chế độ thẻ: nhóm theo ngày -->
      <div
        v-else-if="viewMode === 'cards'"
        class="divide-y divide-slate-100"
      >
        <section
          v-for="group in groupedByDate"
          :key="group.date"
          class="min-w-0"
        >
          <button
            type="button"
            class="flex w-full flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50/80 px-4 py-2.5 text-left transition hover:bg-slate-100/80"
            @click="toggleDay(group.date)"
          >
            <AppIcon
              :name="isDayCollapsed(group.date) ? 'chevron-right' : 'chevron-down'"
              :size="16"
              class="shrink-0 text-slate-400"
            />
            <span class="font-display text-sm font-semibold tabular-nums text-slate-800">
              {{ formatDate(group.date) }}
            </span>
            <span class="text-xs text-slate-500">
              {{ group.items.length }} báo cáo
            </span>
          </button>

          <div
            v-show="!isDayCollapsed(group.date)"
            class="flex gap-3 overflow-x-auto px-4 py-3 pb-4 snap-x snap-mandatory scroll-smooth"
          >
            <article
              v-for="r in group.items"
              :key="r.id"
              class="flex w-[min(100%,18rem)] min-w-[18rem] max-w-[21rem] shrink-0 snap-start flex-col rounded-card border border-slate-200 bg-white p-3 shadow-sm transition hover:border-brand/25 hover:shadow-md"
            >
              <div class="mb-2 flex items-start gap-2">
                <Avatar
                  v-if="visible('employee')"
                  :name="r.employee?.name ?? '?'"
                  :src="r.employee?.avatar_path"
                  :size="32"
                />
                <div class="min-w-0 flex-1">
                  <div
                    v-if="visible('employee')"
                    class="truncate text-xs font-semibold text-slate-700"
                    :title="r.employee?.name"
                  >
                    {{ r.employee?.name ?? '—' }}
                  </div>
                  <div
                    v-if="visible('projects') && projectChips(r).length"
                    class="mt-1 flex flex-wrap gap-1"
                  >
                    <span
                      v-for="(chip, i) in projectChips(r)"
                      :key="i"
                      class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600"
                    >
                      {{ chip }}
                    </span>
                  </div>
                </div>
                <div class="flex shrink-0 flex-col items-end gap-1">
                  <span
                    v-if="r.is_late"
                    class="rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold text-danger"
                  >Trễ</span>
                  <span
                    v-if="visible('feedback') && hasFeedback(r)"
                    class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-amber-50 text-amber-600"
                    title="Có phản hồi"
                  >
                    <AppIcon
                      name="feedback"
                      :size="12"
                    />
                  </span>
                </div>
              </div>

              <Link
                v-if="visible('title')"
                :href="`/daily-reports/${r.id}`"
                class="mb-2 line-clamp-2 text-sm font-medium leading-snug text-slate-800 hover:text-brand"
                :title="r.title"
              >
                {{ r.title }}
              </Link>

              <div class="mt-auto flex flex-wrap items-center gap-2 pt-1">
                <StatusBadge
                  v-if="visible('status')"
                  :label="r.status_label"
                  :color="r.status_color"
                />
                <div
                  v-if="visible('score') && r.score"
                  class="inline-flex items-center gap-1.5"
                >
                  <GradePill
                    :grade="r.score.grade"
                    :color="r.score.grade_color"
                  />
                  <span class="text-xs font-semibold tabular-nums text-slate-600">
                    {{ Number(r.score.total_score ?? 0).toFixed(1) }}
                  </span>
                </div>
              </div>

              <div class="mt-3 flex items-center gap-1 border-t border-slate-100 pt-2">
                <Link
                  :href="`/daily-reports/${r.id}`"
                  class="inline-flex h-8 flex-1 items-center justify-center rounded-btn text-xs font-medium text-brand hover:bg-brand/5"
                >
                  Chi tiết
                </Link>
                <button
                  v-if="r.can?.delete"
                  type="button"
                  class="inline-flex h-8 items-center rounded-btn px-2.5 text-xs font-medium text-danger hover:bg-rose-50"
                  @click="removeReport(r)"
                >
                  Xoá
                </button>
              </div>
            </article>
          </div>
        </section>
      </div>

      <!-- Chế độ bảng -->
      <div
        v-else
        class="overflow-x-auto"
      >
        <table class="w-full text-sm">
          <thead class="border-b border-slate-200 bg-slate-50/80 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th class="whitespace-nowrap px-4 py-2.5">
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
                v-if="visible('projects')"
                class="px-4 py-2.5"
              >
                Dự án
              </th>
              <th
                v-if="visible('status')"
                class="px-4 py-2.5"
              >
                Trạng thái
              </th>
              <th
                v-if="visible('score')"
                class="px-4 py-2.5"
              >
                Điểm
              </th>
              <th
                v-if="visible('feedback')"
                class="w-16 px-2 py-2.5 text-center"
              >
                Phản hồi
              </th>
              <th class="px-4 py-2.5 text-right">
                Thao tác
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="r in tableRows"
              :key="r.id"
              class="transition hover:bg-slate-50/80"
            >
              <td class="whitespace-nowrap px-4 py-3 align-middle text-slate-600">
                <span class="tabular-nums">{{ formatDate(r.date) }}</span>
                <span
                  v-if="r.is_late"
                  class="ml-1 rounded bg-rose-50 px-1 text-[10px] font-semibold text-danger"
                >Trễ</span>
              </td>
              <td
                v-if="visible('employee')"
                class="px-4 py-3 align-middle"
              >
                <div class="flex items-center gap-2">
                  <Avatar
                    :name="r.employee?.name ?? '?'"
                    :src="r.employee?.avatar_path"
                    :size="28"
                  />
                  <span class="max-w-[10rem] truncate text-slate-700">{{ r.employee?.name ?? '—' }}</span>
                </div>
              </td>
              <td
                v-if="visible('title')"
                class="max-w-md px-4 py-3 align-middle"
              >
                <Link
                  :href="`/daily-reports/${r.id}`"
                  class="line-clamp-2 font-medium text-slate-800 hover:text-brand"
                >
                  {{ r.title }}
                </Link>
              </td>
              <td
                v-if="visible('projects')"
                class="max-w-[8rem] truncate px-4 py-3 text-xs text-slate-500"
                :title="projectChips(r).join(', ')"
              >
                {{ projectChips(r).join(', ') || '—' }}
              </td>
              <td
                v-if="visible('status')"
                class="px-4 py-3 align-middle"
              >
                <StatusBadge
                  :label="r.status_label"
                  :color="r.status_color"
                />
              </td>
              <td
                v-if="visible('score')"
                class="px-4 py-3 align-middle"
              >
                <div
                  v-if="r.score"
                  class="inline-flex items-center gap-2"
                >
                  <GradePill
                    :grade="r.score.grade"
                    :color="r.score.grade_color"
                  />
                  <span class="text-xs font-semibold tabular-nums">{{ Number(r.score.total_score ?? 0).toFixed(1) }}</span>
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
                <AppIcon
                  v-if="hasFeedback(r)"
                  name="feedback"
                  :size="16"
                  class="inline text-amber-500"
                />
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-right align-middle">
                <Link
                  :href="`/daily-reports/${r.id}`"
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
