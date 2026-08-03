<script setup>
/* eslint-disable vue/no-v-html -- rendered HTML from Tiptap editor */
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import ReviewScoringPanel from '@/modules/daily-report/components/ReviewScoringPanel.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';
import { date as formatDate, datetime } from '@/composables/useFormat';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';
import { isRoutineProjectEntry } from '@/modules/daily-report/constants/routineWork';
import { baocaoFieldsMirrorSelectedTasks } from '@/modules/daily-report/utils/taskDerivedBaocaoHtml';

const dialog = useDialog();
const toast = useToast();

const props = defineProps({
    reports: { type: Object, required: true },
    pendingMembers: { type: Array, default: () => [] },
    queueTotals: {
        type: Object,
        default: () => ({ reports: 0, members: 0, today: 0, late: 0 }),
    },
    scoringRubricsByEmployee: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    /** Business calendar «hôm nay» (config daily_report.timezone), not browser UTC/local. */
    today: { type: String, required: true },
});

const searchQuery = ref(props.filters.q ?? '');
const queueTab = ref(props.filters.queue ?? 'all');
const detailTab = ref('overview');
const selectedReportId = ref(null);
const scoringPanelRef = ref(null);
const bulkSelected = ref(new Set());
const submitting = ref(false);
const bulkBusy = ref(false);
const mobileShowDetail = ref(false);
const perPage = ref(Number(props.reports.meta?.per_page) || 15);

let searchTimer = null;

const QUEUE_TABS = [
    { key: 'all', label: 'Tất cả' },
    { key: 'today', label: 'Hôm nay' },
    { key: 'late', label: 'Quá hạn' },
];

const DETAIL_TABS = [
    { key: 'overview', label: 'Tổng quan' },
    { key: 'goals', label: 'Mục tiêu & Kết quả' },
    { key: 'blockers', label: 'Vướng mắc & Đề xuất' },
    { key: 'plan', label: 'Kế hoạch' },
    { key: 'history', label: 'Lịch sử' },
];

const pageReports = computed(() => props.reports.data ?? []);

const selectedReport = computed(() => {
    if (selectedReportId.value == null) return pageReports.value[0] ?? null;
    return (
        pageReports.value.find((r) => r.id === selectedReportId.value)
        ?? pageReports.value[0]
        ?? null
    );
});

const selectedIndex = computed(() => {
    if (!selectedReport.value) return -1;
    return pageReports.value.findIndex((r) => r.id === selectedReport.value.id);
});

const prevReport = computed(() => pageReports.value[selectedIndex.value - 1] ?? null);
const nextReport = computed(() => pageReports.value[selectedIndex.value + 1] ?? null);

const selectedMember = computed(() =>
    props.pendingMembers.find((m) => m.employee_id === selectedReport.value?.employee?.id) ?? null,
);

const selectedRubric = computed(() => {
    const empId = selectedReport.value?.employee?.id;
    if (empId == null) return null;
    return props.scoringRubricsByEmployee?.[empId]
        ?? props.scoringRubricsByEmployee?.[String(empId)]
        ?? null;
});

const allSelected = computed(
    () =>
        pageReports.value.length > 0
        && bulkSelected.value.size === pageReports.value.length,
);

const tabCount = (key) => {
    if (key === 'today') return props.queueTotals.today ?? 0;
    if (key === 'late') return props.queueTotals.late ?? 0;
    return props.queueTotals.reports ?? 0;
};

watch(
    pageReports,
    (list) => {
        if (!list.length) {
            selectedReportId.value = null;
            return;
        }
        if (!list.find((r) => r.id === selectedReportId.value)) {
            selectedReportId.value = list[0].id;
        }
    },
    { immediate: true },
);

watch(
    () => props.filters,
    (f) => {
        searchQuery.value = f.q ?? '';
        queueTab.value = f.queue ?? 'all';
    },
);

function applyFilters({ queue, q, employeeId, page, resetPage = false } = {}) {
    const params = {
        queue: queue ?? queueTab.value,
        q: (q ?? searchQuery.value).trim() || undefined,
        employee_id: employeeId !== undefined
            ? (employeeId || undefined)
            : (props.filters.employee_id || undefined),
        per_page: perPage.value,
        page: resetPage ? 1 : (page ?? props.reports.meta?.current_page ?? 1),
    };

    router.get('/daily-reports/review', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

watch(searchQuery, (val) => {
    if (searchTimer) clearTimeout(searchTimer);
    if (val === (props.filters.q ?? '')) return;
    searchTimer = setTimeout(() => {
        applyFilters({ q: val, resetPage: true });
    }, 350);
});

function setQueueTab(key) {
    if (queueTab.value === key) return;
    queueTab.value = key;
    applyFilters({ queue: key, resetPage: true });
}

function clearEmployeeFilter() {
    applyFilters({ employeeId: null, resetPage: true });
}

function selectReport(report) {
    selectedReportId.value = report.id;
    detailTab.value = 'overview';
    mobileShowDetail.value = true;
}

function toggleBulk(id) {
    const s = new Set(bulkSelected.value);
    if (s.has(id)) s.delete(id);
    else s.add(id);
    bulkSelected.value = s;
}

function toggleAll() {
    if (allSelected.value) {
        bulkSelected.value = new Set();
    } else {
        bulkSelected.value = new Set(pageReports.value.map((r) => r.id));
    }
}

const render = (html) => html || `<span class="text-slate-400">${EMPTY_LABELS.generic}</span>`;
const hasText = (html) => (html || '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim().length > 0;

function reportProjects(report) {
    return (report.projects ?? []).filter((p) => !isRoutineProjectEntry(p));
}

function routineTasks(report) {
    const routine = (report.projects ?? []).find(isRoutineProjectEntry);
    return routine?.tasks ?? [];
}

function showStructuredWorkScope(report) {
    const real = reportProjects(report);
    const routine = routineTasks(report);
    if (!real.length && !routine.length) return false;
    return !baocaoFieldsMirrorSelectedTasks(
        report.projects,
        report.goals_today,
        report.progress_update,
    );
}

function memberForReport(report) {
    return props.pendingMembers.find((m) => m.employee_id === report.employee?.id) ?? null;
}

function deptLabel(report) {
    const code = report?.employee?.department_code;
    const name = report?.employee?.department_name;
    if (name && code) return `${name} · ${code}`;
    return name || code || null;
}

async function handleReject() {
    if (!selectedReport.value || submitting.value) return;
    const notes = await dialog.prompt({
        title: 'Trả lại báo cáo để chỉnh sửa?',
        message: 'Nêu lý do cụ thể để thành viên biết cần bổ sung / sửa gì:',
        placeholder: 'VD: Thiếu phần kết quả cụ thể, vui lòng bổ sung số liệu…',
        confirmText: 'Trả lại',
        cancelText: 'Huỷ',
    });
    if (notes === null) return;
    if (!notes.trim()) {
        toast.error('Vui lòng nhập lý do trả lại.');
        return;
    }
    submitting.value = true;
    scoringPanelRef.value?.reject(notes, {
        onFinish: () => { submitting.value = false; },
    });
}

function handleScoreAndNext() {
    if (!scoringPanelRef.value || submitting.value) return;
    const nextId = nextReport.value?.id ?? null;
    submitting.value = true;
    scoringPanelRef.value.submit({
        onSuccess: () => {
            if (nextId) selectedReportId.value = nextId;
        },
        onFinish: () => { submitting.value = false; },
    });
}

async function handleBulkScore() {
    if (bulkSelected.value.size === 0 || bulkBusy.value) return;
    const payload = scoringPanelRef.value?.scorePayload?.() ?? {
        task_completion: 8,
        skill_score: 8,
        attitude_score: 8,
        kaizen_score: 5,
        expertise_score: 8,
        notes: null,
    };

    const ok = await dialog.confirm({
        title: `Duyệt ${bulkSelected.value.size} báo cáo đã chọn?`,
        message: 'Áp dụng điểm đang chọn trên panel chấm (hoặc mặc định 8/10 nếu chưa mở panel) cho tất cả báo cáo đã chọn.',
        confirmText: 'Duyệt hàng loạt',
        cancelText: 'Huỷ',
    });
    if (!ok) return;

    bulkBusy.value = true;
    router.post('/daily-reports/review/bulk-score', {
        ids: [...bulkSelected.value],
        ...payload,
    }, {
        preserveScroll: true,
        onFinish: () => {
            bulkBusy.value = false;
            bulkSelected.value = new Set();
        },
    });
}

async function handleBulkReject() {
    if (bulkSelected.value.size === 0 || bulkBusy.value) return;
    const notes = await dialog.prompt({
        title: `Trả lại ${bulkSelected.value.size} báo cáo?`,
        message: 'Lý do sẽ áp dụng cho tất cả báo cáo đã chọn:',
        placeholder: 'VD: Thiếu số liệu kết quả, vui lòng bổ sung…',
        confirmText: 'Trả lại hàng loạt',
        cancelText: 'Huỷ',
    });
    if (notes === null) return;
    if (!notes.trim()) {
        toast.error('Vui lòng nhập lý do trả lại.');
        return;
    }

    bulkBusy.value = true;
    router.post('/daily-reports/review/bulk-reject', {
        ids: [...bulkSelected.value],
        notes,
    }, {
        preserveScroll: true,
        onFinish: () => {
            bulkBusy.value = false;
            bulkSelected.value = new Set();
        },
    });
}

function handleKeydown(e) {
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;
    if (e.key === 'ArrowDown' || e.key === 'j') {
        if (nextReport.value) { selectReport(nextReport.value); e.preventDefault(); }
    } else if (e.key === 'ArrowUp' || e.key === 'k') {
        if (prevReport.value) { selectReport(prevReport.value); e.preventDefault(); }
    }
}

onMounted(() => document.addEventListener('keydown', handleKeydown));
onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
    if (searchTimer) clearTimeout(searchTimer);
});
</script>

<template>
  <Head title="Chờ phê duyệt" />

  <AppLayout :flush="true">
    <template #header>
      <PageHeader
        title="Chờ phê duyệt"
        subtitle="Xem xét và chấm điểm báo cáo của thành viên"
        icon="review-reports"
        icon-color="violet"
        :badge="queueTotals.reports || null"
      />
    </template>

    <div
      v-if="queueTotals.reports === 0 && !filters.q && (filters.queue || 'all') === 'all' && !filters.employee_id"
      class="flex flex-1 flex-col items-center justify-center gap-4 p-12 text-center"
    >
      <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 shadow-inner">
        <AppIcon
          name="review-reports"
          :size="28"
          class="text-slate-400"
        />
      </div>
      <div>
        <p class="font-display text-base font-semibold text-slate-700">
          Hàng chờ trống
        </p>
        <p class="mt-1 text-sm text-slate-400">
          Không có báo cáo nào đang chờ duyệt.
        </p>
      </div>
    </div>

    <div
      v-else
      class="flex min-h-0 flex-1 overflow-hidden"
    >
      <!-- LEFT — Queue -->
      <div
        class="flex w-full min-w-0 shrink-0 flex-col overflow-hidden border-r border-slate-200 bg-white lg:w-[340px] xl:w-[360px]"
        :class="mobileShowDetail ? 'hidden lg:flex' : 'flex'"
      >
        <div class="shrink-0 space-y-2.5 overflow-x-hidden border-b border-slate-100 px-4 py-3">
          <div class="flex items-center justify-between gap-2">
            <div class="min-w-0">
              <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">
                Hàng chờ duyệt
              </p>
              <h2 class="font-display text-sm font-semibold text-slate-800">
                Báo cáo
                <span class="ml-1 inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-slate-100 px-1.5 text-[11px] font-semibold tabular-nums text-slate-600">
                  {{ queueTotals.reports }}
                </span>
              </h2>
            </div>

            <div
              v-if="bulkSelected.size > 0"
              class="flex shrink-0 items-center gap-1.5"
            >
              <span class="text-xs text-slate-500">{{ bulkSelected.size }} đã chọn</span>
              <button
                type="button"
                class="btn-ghost h-7 gap-1 px-2 text-[11px] text-success"
                :disabled="bulkBusy"
                @click="handleBulkScore"
              >
                <AppIcon
                  name="check"
                  :size="12"
                />
                Duyệt
              </button>
              <button
                type="button"
                class="btn-ghost h-7 gap-1 px-2 text-[11px] text-danger"
                :disabled="bulkBusy"
                @click="handleBulkReject"
              >
                <AppIcon
                  name="x"
                  :size="12"
                />
                Trả lại
              </button>
            </div>
          </div>

          <div class="relative">
            <AppIcon
              name="search"
              :size="13"
              class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
            />
            <input
              v-model="searchQuery"
              type="text"
              class="input h-9 w-full pl-8 pr-3 text-sm"
              placeholder="Tìm nhân viên, tiêu đề…"
              aria-label="Tìm kiếm trong hàng chờ"
            >
          </div>

          <div class="flex flex-wrap items-center gap-0.5">
            <button
              v-for="tab in QUEUE_TABS"
              :key="tab.key"
              type="button"
              class="flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium transition-colors"
              :class="queueTab === tab.key
                ? 'bg-brand/10 text-brand'
                : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700'"
              @click="setQueueTab(tab.key)"
            >
              {{ tab.label }}
              <span
                v-if="tabCount(tab.key) > 0"
                class="tabular-nums"
                :class="tab.key === 'late' ? 'text-danger' : ''"
              >({{ tabCount(tab.key) }})</span>
            </button>
          </div>

          <div
            v-if="filters.employee_id"
            class="flex items-center gap-2 rounded-md bg-violet-50 px-2.5 py-1.5 text-[11px] text-violet-700"
          >
            <span class="min-w-0 truncate">
              Lọc:
              {{ pendingMembers.find((m) => m.employee_id === filters.employee_id)?.name || 'Nhân viên' }}
            </span>
            <button
              type="button"
              class="shrink-0 font-medium underline"
              @click="clearEmployeeFilter"
            >
              Bỏ lọc
            </button>
          </div>

          <div
            v-if="pageReports.length > 0"
            class="flex items-center gap-2 border-t border-slate-100 pt-1.5"
          >
            <input
              id="queue-select-all"
              type="checkbox"
              :checked="allSelected"
              class="h-3.5 w-3.5 cursor-pointer rounded border-slate-300 accent-brand"
              @change="toggleAll"
            >
            <label
              for="queue-select-all"
              class="cursor-pointer select-none text-[11px] text-slate-500"
            >
              Chọn tất cả trang này ({{ pageReports.length }})
            </label>
          </div>
        </div>

        <div class="min-w-0 flex-1 overflow-x-hidden overflow-y-auto">
          <div
            v-if="pageReports.length === 0"
            class="flex flex-col items-center gap-2 py-14 text-center"
          >
            <AppIcon
              name="search"
              :size="22"
              class="text-slate-300"
            />
            <p class="text-sm text-slate-400">
              Không có báo cáo nào khớp bộ lọc.
            </p>
          </div>

          <button
            v-for="r in pageReports"
            :key="r.id"
            type="button"
            class="flex w-full min-w-0 items-start gap-2.5 overflow-hidden border-b border-b-slate-100 border-l-2 px-3 py-3 text-left transition-colors"
            :class="selectedReport?.id === r.id
              ? 'border-l-brand bg-brand/[0.04] hover:bg-brand/[0.06]'
              : 'border-l-transparent hover:bg-slate-50'"
            :aria-selected="selectedReport?.id === r.id"
            @click="selectReport(r)"
          >
            <div
              class="mt-0.5 shrink-0"
              @click.stop
            >
              <input
                type="checkbox"
                :checked="bulkSelected.has(r.id)"
                :aria-label="`Chọn báo cáo của ${r.employee?.name}`"
                class="h-3.5 w-3.5 cursor-pointer rounded border-slate-300 accent-brand"
                @change="toggleBulk(r.id)"
              >
            </div>

            <Avatar
              :name="r.employee?.name ?? '?'"
              :src="r.employee?.avatar_path"
              :size="36"
              class="mt-0.5 shrink-0"
            />

            <div class="min-w-0 flex-1 overflow-hidden">
              <div class="flex min-w-0 items-start justify-between gap-1.5">
                <p class="min-w-0 truncate text-sm font-semibold text-slate-800">
                  {{ r.employee?.name ?? EMPTY_LABELS.notUpdated }}
                </p>
                <span
                  v-if="r.is_late"
                  class="shrink-0 rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold text-danger"
                >
                  Trễ
                </span>
              </div>

              <p class="truncate text-[11px] text-slate-400">
                {{ displayOrEmpty(r.employee?.role_title, EMPTY_LABELS.role) }}
              </p>
              <p
                v-if="deptLabel(r)"
                class="truncate text-[10px] text-slate-400"
              >
                {{ deptLabel(r) }}
              </p>

              <div class="mt-1.5 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-slate-500">
                <span class="flex min-w-0 items-center gap-1">
                  <AppIcon
                    name="calendar"
                    :size="11"
                    class="shrink-0 text-slate-400"
                  />
                  <span class="truncate">{{ formatDate(r.date) }}</span>
                </span>
              </div>

              <div
                v-if="(memberForReport(r)?.pending_count ?? 0) > 1"
                class="mt-1.5"
              >
                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-700">
                  {{ memberForReport(r).pending_count }} báo cáo chờ
                </span>
              </div>
            </div>
          </button>
        </div>

        <div
          v-if="reports.meta?.last_page > 1"
          class="shrink-0 border-t border-slate-100"
        >
          <DatagridPaginationFooter
            variant="bar"
            :meta="reports.meta"
            :per-page="perPage"
            :per-page-options="[15, 30, 50]"
          />
        </div>
      </div>

      <!-- RIGHT — Detail -->
      <div
        class="flex min-w-0 flex-1 flex-col overflow-hidden bg-slate-50/40"
        :class="mobileShowDetail ? 'flex' : 'hidden lg:flex'"
      >
        <div
          v-if="!selectedReport"
          class="flex flex-1 flex-col items-center justify-center gap-4 p-12 text-center"
        >
          <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100">
            <AppIcon
              name="review-reports"
              :size="28"
              class="text-slate-300"
            />
          </div>
          <div>
            <p class="text-sm font-medium text-slate-500">
              Chọn báo cáo để bắt đầu đánh giá
            </p>
            <p class="mt-1 text-xs text-slate-400">
              Dùng phím ↑ ↓ hoặc J K để di chuyển nhanh
            </p>
          </div>
        </div>

        <template v-else>
          <div class="shrink-0 border-b border-slate-100 bg-white px-4 py-2 lg:hidden">
            <button
              type="button"
              class="btn-ghost h-8 gap-1.5 text-xs"
              @click="mobileShowDetail = false"
            >
              <AppIcon
                name="back"
                :size="14"
              />
              Danh sách
            </button>
          </div>

          <div class="shrink-0 border-b border-slate-200/80 bg-white px-5 py-4">
            <div class="flex items-start justify-between gap-4">
              <div class="flex min-w-0 items-start gap-3.5">
                <Avatar
                  :name="selectedReport.employee?.name ?? '?'"
                  :src="selectedReport.employee?.avatar_path"
                  :size="44"
                  class="shrink-0"
                />
                <div class="min-w-0">
                  <h2 class="truncate font-display text-base font-semibold leading-snug text-slate-800">
                    {{ selectedReport.employee?.name ?? EMPTY_LABELS.notUpdated }}
                  </h2>
                  <p class="truncate text-sm text-slate-500">
                    {{ displayOrEmpty(selectedReport.employee?.role_title, EMPTY_LABELS.role) }}
                  </p>
                  <p
                    v-if="deptLabel(selectedReport)"
                    class="mt-0.5 truncate text-xs text-slate-400"
                  >
                    {{ deptLabel(selectedReport) }}
                  </p>
                  <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                    <span class="flex items-center gap-1">
                      <AppIcon
                        name="calendar"
                        :size="12"
                        class="text-slate-400"
                      />
                      Kỳ:
                      <span class="font-medium text-slate-700">{{ formatDate(selectedReport.date) }}</span>
                    </span>
                    <span
                      v-if="selectedReport.submitted_at"
                      class="flex items-center gap-1"
                    >
                      Nộp
                      <span class="font-medium text-slate-700">{{ datetime(selectedReport.submitted_at) }}</span>
                    </span>
                  </div>
                </div>
              </div>

              <div class="flex shrink-0 flex-col items-end gap-2">
                <div class="flex flex-wrap items-center justify-end gap-1.5">
                  <span
                    v-if="selectedReport.is_late"
                    class="rounded-md bg-rose-50 px-2 py-0.5 text-xs font-semibold text-danger"
                  >Nộp trễ</span>
                  <span
                    v-else
                    class="rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700"
                  >Đúng hạn</span>
                </div>
                <p class="text-[11px] text-slate-400">
                  {{ selectedIndex + 1 }} / {{ pageReports.length }}
                </p>
                <div class="flex items-center gap-1">
                  <button
                    type="button"
                    :disabled="!prevReport"
                    title="Báo cáo trước (↑ / K)"
                    class="btn-ghost h-7 w-7 rounded-md p-0 disabled:pointer-events-none disabled:opacity-30"
                    @click="prevReport && selectReport(prevReport)"
                  >
                    <AppIcon
                      name="chevron-up"
                      :size="14"
                    />
                  </button>
                  <button
                    type="button"
                    :disabled="!nextReport"
                    title="Báo cáo tiếp (↓ / J)"
                    class="btn-ghost h-7 w-7 rounded-md p-0 disabled:pointer-events-none disabled:opacity-30"
                    @click="nextReport && selectReport(nextReport)"
                  >
                    <AppIcon
                      name="chevron-down"
                      :size="14"
                    />
                  </button>
                  <Link
                    :href="`/daily-reports/${selectedReport.id}`"
                    class="btn-ghost h-7 gap-1 px-2 text-xs"
                    title="Mở báo cáo đầy đủ"
                    target="_blank"
                    rel="noopener"
                  >
                    <AppIcon
                      name="eye"
                      :size="13"
                    />
                    Mở
                  </Link>
                </div>
              </div>
            </div>
          </div>

          <div class="shrink-0 border-b border-slate-200/80 bg-white px-5">
            <div class="flex items-center overflow-x-auto">
              <button
                v-for="tab in DETAIL_TABS"
                :key="tab.key"
                type="button"
                class="shrink-0 border-b-2 px-3 py-2.5 text-xs font-medium transition-colors"
                :class="detailTab === tab.key
                  ? 'border-brand text-brand'
                  : 'border-transparent text-slate-500 hover:text-slate-700'"
                @click="detailTab = tab.key"
              >
                {{ tab.label }}
              </button>
            </div>
          </div>

          <div class="flex-1 overflow-y-auto">
            <!-- Overview: 2-col -->
            <div
              v-if="detailTab === 'overview'"
              class="flex min-h-full flex-col xl:flex-row"
            >
              <div class="min-w-0 flex-1 space-y-4 p-5">
                <div
                  v-if="hasText(selectedReport.goals_today)"
                  class="overflow-hidden rounded-xl border border-slate-200 bg-white"
                >
                  <div class="border-b border-slate-100 px-4 py-2.5">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                      Tóm tắt báo cáo
                    </p>
                  </div>
                  <div class="p-4">
                    <div
                      class="rich-content line-clamp-5 min-w-0 text-sm text-slate-700"
                      v-html="selectedReport.goals_today"
                    />
                    <button
                      type="button"
                      class="mt-2 text-xs text-brand hover:underline"
                      @click="detailTab = 'goals'"
                    >
                      Xem đầy đủ →
                    </button>
                  </div>
                </div>

                <div
                  v-if="hasText(selectedReport.progress_update)"
                  class="overflow-hidden rounded-xl border border-slate-200 bg-white"
                >
                  <div class="border-b border-slate-100 px-4 py-2.5">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                      Tiến độ / kết quả
                    </p>
                  </div>
                  <div class="p-4">
                    <div
                      class="rich-content line-clamp-4 min-w-0 text-sm text-slate-700"
                      v-html="selectedReport.progress_update"
                    />
                    <button
                      type="button"
                      class="mt-2 text-xs text-brand hover:underline"
                      @click="detailTab = 'goals'"
                    >
                      Xem đầy đủ →
                    </button>
                  </div>
                </div>

                <div
                  v-if="showStructuredWorkScope(selectedReport)"
                  class="overflow-hidden rounded-xl border border-slate-200 bg-white"
                >
                  <div class="border-b border-slate-100 px-4 py-2.5">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                      Phạm vi công việc
                    </p>
                  </div>
                  <div class="divide-y divide-slate-100">
                    <div
                      v-for="p in reportProjects(selectedReport)"
                      :key="p.id"
                      class="px-4 py-3"
                    >
                      <p class="truncate text-sm font-medium text-brand">
                        {{ p.name }}
                      </p>
                      <ul
                        v-if="p.tasks?.length"
                        class="mt-1.5 space-y-1"
                      >
                        <li
                          v-for="t in p.tasks"
                          :key="`${p.id}-${t.id}`"
                          class="truncate text-xs text-slate-600"
                        >
                          · {{ t.title }}
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>

                <dl class="grid grid-cols-2 gap-3 rounded-xl border border-slate-200 bg-white p-4 text-sm sm:grid-cols-4">
                  <div>
                    <dt class="text-[11px] text-slate-400">
                      Ngày báo cáo
                    </dt>
                    <dd class="font-medium text-slate-700">
                      {{ formatDate(selectedReport.date) }}
                    </dd>
                  </div>
                  <div>
                    <dt class="text-[11px] text-slate-400">
                      Trạng thái nộp
                    </dt>
                    <dd
                      class="font-semibold"
                      :class="selectedReport.is_late ? 'text-danger' : 'text-emerald-600'"
                    >
                      {{ selectedReport.is_late ? 'Nộp trễ' : 'Đúng hạn' }}
                    </dd>
                  </div>
                  <div v-if="selectedMember">
                    <dt class="text-[11px] text-slate-400">
                      Báo cáo chờ
                    </dt>
                    <dd class="font-semibold text-slate-700">
                      {{ selectedMember.pending_count ?? 0 }}
                    </dd>
                  </div>
                  <div v-if="selectedRubric?.department_code || selectedRubric?.source">
                    <dt class="text-[11px] text-slate-400">
                      Rubric
                    </dt>
                    <dd class="truncate font-medium text-slate-700">
                      {{ selectedRubric?.source === 'department'
                        ? `PB ${selectedRubric.department_code}`
                        : 'Hệ thống' }}
                    </dd>
                  </div>
                </dl>
              </div>

              <div class="shrink-0 border-t border-slate-200 bg-white p-4 xl:w-[380px] xl:overflow-y-auto xl:border-l xl:border-t-0">
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white p-4">
                  <ReviewScoringPanel
                    :key="selectedReport.id"
                    ref="scoringPanelRef"
                    :report="selectedReport"
                    :rubric="selectedRubric"
                  />
                </div>
              </div>
            </div>

            <div
              v-else-if="detailTab === 'goals'"
              class="mx-auto max-w-3xl space-y-5 p-5"
            >
              <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="border-b border-slate-100 bg-brand/[0.03] px-5 py-3">
                  <p class="text-xs font-bold uppercase tracking-wide text-brand/70">
                    Mục tiêu hôm nay
                  </p>
                </div>
                <div
                  class="rich-content min-w-0 break-words p-5 text-sm text-slate-700"
                  v-html="render(selectedReport.goals_today)"
                />
              </div>
              <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="border-b border-slate-100 bg-emerald-50/60 px-5 py-3">
                  <p class="text-xs font-bold uppercase tracking-wide text-emerald-700/70">
                    Tiến độ thực hiện
                  </p>
                </div>
                <div
                  class="rich-content min-w-0 break-words p-5 text-sm text-slate-700"
                  v-html="render(selectedReport.progress_update)"
                />
              </div>
            </div>

            <div
              v-else-if="detailTab === 'blockers'"
              class="mx-auto max-w-3xl space-y-5 p-5"
            >
              <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="border-b border-slate-100 bg-amber-50/60 px-5 py-3">
                  <p class="text-xs font-bold uppercase tracking-wide text-amber-700/70">
                    Khó khăn & Vướng mắc
                  </p>
                </div>
                <div
                  class="rich-content min-w-0 break-words p-5 text-sm text-slate-700"
                  v-html="render(selectedReport.blockers)"
                />
              </div>
              <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="border-b border-slate-100 bg-emerald-50/40 px-5 py-3">
                  <p class="text-xs font-bold uppercase tracking-wide text-emerald-700/70">
                    Đề xuất cải tiến (Kaizen)
                  </p>
                </div>
                <div
                  class="rich-content min-w-0 break-words p-5 text-sm text-slate-700"
                  v-html="render(selectedReport.improvement_suggestions)"
                />
              </div>
            </div>

            <div
              v-else-if="detailTab === 'plan'"
              class="mx-auto max-w-3xl p-5"
            >
              <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="border-b border-slate-100 bg-sky-50/60 px-5 py-3">
                  <p class="text-xs font-bold uppercase tracking-wide text-sky-700/70">
                    Kế hoạch ngày mai
                  </p>
                </div>
                <div
                  class="rich-content min-w-0 break-words p-5 text-sm text-slate-700"
                  v-html="render(selectedReport.plan_tomorrow)"
                />
              </div>
            </div>

            <div
              v-else-if="detailTab === 'history'"
              class="mx-auto max-w-2xl p-5"
            >
              <div class="overflow-hidden rounded-xl border border-slate-200 bg-white p-8 text-center">
                <AppIcon
                  name="review-reports"
                  :size="32"
                  class="mx-auto mb-3 text-slate-300"
                />
                <p class="text-sm font-medium text-slate-600">
                  Xem lịch sử đầy đủ
                </p>
                <p class="mt-1 text-xs leading-relaxed text-slate-400">
                  Bao gồm lịch sử điểm số, timeline duyệt và các lần rút lại trước đây.
                </p>
                <Link
                  :href="`/daily-reports/${selectedReport.id}`"
                  class="btn-ghost mx-auto mt-5 gap-1.5 text-sm"
                  target="_blank"
                  rel="noopener"
                >
                  <AppIcon
                    name="eye"
                    :size="14"
                  />
                  Mở báo cáo đầy đủ
                </Link>
              </div>
            </div>
          </div>

          <div
            v-if="selectedReport.can?.score"
            class="shrink-0 border-t border-slate-200 bg-white px-5 py-3"
          >
            <div class="flex items-center justify-between gap-3">
              <button
                type="button"
                :disabled="submitting"
                class="btn-ghost h-9 gap-1.5 text-sm text-danger hover:bg-rose-50 disabled:pointer-events-none disabled:opacity-50"
                @click="handleReject"
              >
                <AppIcon
                  name="x"
                  :size="15"
                />
                Trả lại chỉnh sửa
              </button>

              <button
                type="button"
                :disabled="submitting"
                class="btn-primary h-9 gap-1.5 text-sm disabled:pointer-events-none disabled:opacity-60"
                @click="handleScoreAndNext"
              >
                <span v-if="submitting">Đang duyệt…</span>
                <template v-else>
                  {{ nextReport ? 'Duyệt & chuyển tiếp' : 'Duyệt báo cáo' }}
                  <AppIcon
                    v-if="nextReport"
                    name="chevron-right"
                    :size="14"
                  />
                </template>
              </button>
            </div>
            <p class="mt-1.5 text-right text-[10px] text-slate-400">
              Phím tắt: ↑ ↓ hoặc J K · Điểm mặc định 8/10
            </p>
          </div>

          <div
            v-else
            class="shrink-0 border-t border-slate-200 bg-slate-50/80 px-5 py-3 text-center text-xs text-slate-400"
          >
            Bạn không có quyền chấm điểm báo cáo này.
          </div>
        </template>
      </div>
    </div>
  </AppLayout>
</template>
