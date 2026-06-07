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
import Modal from '@/Components/Ui/Modal.vue';

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
    { key: 'date', label: 'Ngày', visible: true, fixed: false },
    { key: 'employee', label: 'Người báo cáo', visible: props.canFilterEmployee, manager: true },
    { key: 'title', label: 'Tiêu đề', visible: true },
    { key: 'projects', label: 'Dự án', visible: false },
    { key: 'status', label: 'Trạng thái', visible: true },
    { key: 'feedback', label: 'Phản hồi', visible: true },
    { key: 'grade', label: 'Xếp loại', visible: true },
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

/** @returns {{ kind: 'reject'|'review', label: string, text: string }|null} */
function reportFeedback(r) {
    const reject = (r.review_notes || '').trim();
    if (reject && r.status === 'draft') {
        return { kind: 'reject', label: 'Trả lại', text: reject };
    }
    const review = (r.score?.notes || '').trim();
    if (review) {
        return { kind: 'review', label: 'Nhận xét', text: review };
    }
    return null;
}

function feedbackPreview(text, max = 72) {
    if (!text) return '';
    return text.length > max ? `${text.slice(0, max)}…` : text;
}

const feedbackModal = ref(null);

function openFeedback(r) {
    const fb = reportFeedback(r);
    if (!fb) return;
    feedbackModal.value = {
        title: fb.kind === 'reject' ? 'Lý do trả lại' : 'Nhận xét từ người duyệt',
        label: fb.label,
        kind: fb.kind,
        text: fb.text,
        meta: `${r.title} · ${r.date}`,
        reportId: r.id,
        canEdit: Boolean(r.can?.update),
    };
}

function closeFeedbackModal() {
    feedbackModal.value = null;
}

const reportRows = computed(() =>
    (props.reports.data ?? []).map((r) => ({ r, feedback: reportFeedback(r) })),
);

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
          <thead class="border-b border-slate-200 bg-slate-50 text-left text-slate-500">
            <tr>
              <th
                v-if="visible('date')"
                class="px-4 py-3 font-medium"
              >
                Ngày
              </th>
              <th
                v-if="visible('employee')"
                class="px-4 py-3 font-medium"
              >
                Người báo cáo
              </th>
              <th
                v-if="visible('title')"
                class="px-4 py-3 font-medium"
              >
                Tiêu đề
              </th>
              <th
                v-if="visible('projects')"
                class="px-4 py-3 font-medium"
              >
                Dự án
              </th>
              <th
                v-if="visible('status')"
                class="px-4 py-3 font-medium"
              >
                Trạng thái
              </th>
              <th
                v-if="visible('feedback')"
                class="px-4 py-3 font-medium"
              >
                Phản hồi
              </th>
              <th
                v-if="visible('grade')"
                class="px-4 py-3 font-medium"
              >
                Xếp loại
              </th>
              <th class="px-4 py-3 text-right font-medium">
                Thao tác
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="{ r, feedback } in reportRows"
              :key="r.id"
              class="hover:bg-slate-50"
            >
              <td
                v-if="visible('date')"
                class="whitespace-nowrap px-4 py-3 text-slate-600"
              >
                {{ r.date }}
                <span
                  v-if="r.is_late"
                  class="ml-1 rounded bg-rose-50 px-1.5 py-0.5 text-[11px] font-medium text-danger"
                >trễ</span>
              </td>
              <td
                v-if="visible('employee')"
                class="px-4 py-3 text-slate-600"
              >
                {{ r.employee?.name ?? '—' }}
              </td>
              <td
                v-if="visible('title')"
                class="px-4 py-3 font-medium text-slate-700"
              >
                {{ r.title }}
              </td>
              <td
                v-if="visible('projects')"
                class="px-4 py-3"
              >
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="p in (r.projects || [])"
                    :key="p.id"
                    class="inline-flex items-center rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand"
                  >{{ p.name }}</span>
                  <span
                    v-if="!(r.projects || []).length"
                    class="text-slate-300"
                  >—</span>
                </div>
              </td>
              <td
                v-if="visible('status')"
                class="px-4 py-3"
              >
                <StatusBadge
                  :label="r.status_label"
                  :color="r.status_color"
                />
              </td>
              <td
                v-if="visible('feedback')"
                class="max-w-[14rem] px-4 py-3"
              >
                <template v-if="feedback">
                  <span
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold"
                    :class="feedback.kind === 'reject'
                      ? 'bg-amber-100 text-amber-800'
                      : 'bg-sky-100 text-sky-800'"
                  >
                    {{ feedback.label }}
                  </span>
                  <p class="mt-1 text-xs leading-snug text-slate-600">
                    {{ feedbackPreview(feedback.text) }}
                  </p>
                  <button
                    type="button"
                    class="mt-1 text-xs font-medium text-brand hover:underline"
                    @click="openFeedback(r)"
                  >
                    Xem đầy đủ
                  </button>
                </template>
                <span
                  v-else
                  class="text-slate-300"
                >—</span>
              </td>
              <td
                v-if="visible('grade')"
                class="px-4 py-3"
              >
                <GradePill
                  v-if="r.score"
                  :grade="r.score.grade"
                  :color="r.score.grade_color"
                />
                <span
                  v-else
                  class="text-slate-300"
                >—</span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="inline-flex items-center justify-end gap-3">
                  <Link
                    :href="`/daily-reports/${r.id}`"
                    class="font-medium text-brand hover:underline"
                  >
                    Xem
                  </Link>
                  <button
                    v-if="r.can?.delete"
                    type="button"
                    class="font-medium text-danger hover:underline"
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

    <Modal
      :show="feedbackModal !== null"
      :title="feedbackModal?.title ?? ''"
      max-width="max-w-md"
      @close="closeFeedbackModal"
    >
      <div
        v-if="feedbackModal"
        class="space-y-4"
      >
        <p class="text-xs text-slate-500">
          {{ feedbackModal.meta }}
        </p>
        <div
          class="rounded-card border p-4 text-sm leading-relaxed text-slate-700"
          :class="feedbackModal.kind === 'reject'
            ? 'border-amber-200 bg-amber-50/80'
            : 'border-slate-200 bg-slate-50'"
        >
          {{ feedbackModal.text }}
        </div>
        <div class="flex flex-wrap justify-end gap-2">
          <button
            type="button"
            class="btn-ghost"
            @click="closeFeedbackModal"
          >
            Đóng
          </button>
          <Link
            v-if="feedbackModal.kind === 'reject' && feedbackModal.canEdit"
            href="/daily-reports/today"
            class="btn-primary"
            @click="closeFeedbackModal"
          >
            Sửa báo cáo
          </Link>
          <Link
            v-else
            :href="`/daily-reports/${feedbackModal.reportId}`"
            class="btn-primary"
            @click="closeFeedbackModal"
          >
            Xem báo cáo
          </Link>
        </div>
      </div>
    </Modal>
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
