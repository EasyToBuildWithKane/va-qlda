<script setup>
import {
    reactive,
    ref,
    computed,
    watch,
    onMounted,
    onBeforeUnmount,
} from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import AppIcon from "@/Components/AppIcon.vue";
import StatusBadge from "@/modules/daily-report/components/StatusBadge.vue";
import GradePill from "@/modules/daily-report/components/GradePill.vue";
import DailyReportSummaryBar from '@/modules/daily-report/components/DailyReportSummaryBar.vue';
import ReportCard from "@/modules/daily-report/components/ReportCard.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import Avatar from "@/shared/ui/Avatar.vue";
import DatagridToolbarSearch from "@/shared/ui/DatagridToolbarSearch.vue";
import FilterVisibilityDropdown from "@/shared/ui/FilterVisibilityDropdown.vue";
import DatagridToolbarActionButton from "@/shared/ui/DatagridToolbarActionButton.vue";
import DatagridSegmentedControl from "@/shared/ui/DatagridSegmentedControl.vue";
import DatagridFilterField from "@/shared/ui/DatagridFilterField.vue";
import FilterDatePicker from "@/shared/ui/FilterDatePicker.vue";
import DatagridPaginationFooter from "@/shared/ui/DatagridPaginationFooter.vue";
import SearchMultiSelect from "@/shared/ui/SearchMultiSelect.vue";
import { useFixedDropdownAnchor } from "@/shared/composables/useFixedDropdownAnchor";
import { useVisibleFilterControls } from "@/shared/composables/useVisibleFilterControls";
import { useConfirmDelete } from "@/composables/useConfirmClose";
import { useToast } from "@/shared/composables/useToast";
import { date as formatDate } from "@/composables/useFormat";
import { exportDailyReportHistory } from "@/modules/daily-report/composables/useDailyReportHistoryExport";

const PER_PAGE_OPTIONS = [5, 10, 15, 20];
const VIEW_KEY = "va-qlda.reports.view";
const GROUP_KEY = "va-qlda.reports.group";
const confirmDelete = useConfirmDelete();
const toast = useToast();

const props = defineProps({
    reports: { type: Object, required: true },
    summary: {
        type: Object,
        default: () => ({
            total: 0,
            draft: 0,
            submitted: 0,
            reviewed: 0,
            late: 0,
            completion_rate: 0,
            trend: {},
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

function normalizeIds(value) {
    if (Array.isArray(value))
        return value.map((v) => Number(v)).filter(Boolean);
    if (value == null || value === "") return [];
    if (typeof value === "object")
        return Object.values(value)
            .map((v) => Number(v))
            .filter(Boolean);
    return [Number(value)].filter(Boolean);
}

function pad2(n) {
    return String(n).padStart(2, "0");
}

const VALID_GROUPS = ["day", "week", "month"];

const filterForm = reactive({
    q: props.filters.q ?? "",
    status: props.filters.status ?? "",
    project_id: props.filters.project_id ?? "",
    employee_ids: normalizeIds(
        props.filters.employee_ids ?? props.filters.employee_id,
    ),
    grade: props.filters.grade ?? "",
    from: props.filters.from ?? "",
    to: props.filters.to ?? "",
    late: Boolean(props.filters.late),
});

const perPage = ref(
    Number(props.filters.per_page) || props.reports.meta?.per_page || 10,
);
const viewMode = ref("cards");
const groupMode = ref(
    VALID_GROUPS.includes(props.filters.group) ? props.filters.group : "day",
);
const collapsedGroups = ref({});
const exporting = ref(false);

function routeParams(resetPage = false) {
    const raw = {
        ...filterForm,
        per_page: perPage.value,
        group: groupMode.value,
        late: filterForm.late ? "1" : "",
    };
    const params = {};
    for (const [k, v] of Object.entries(raw)) {
        if (v === "" || v == null || v === false) continue;
        if (Array.isArray(v)) {
            if (v.length) params[k] = v;
            continue;
        }
        params[k] = v;
    }
    if (resetPage) params.page = 1;
    return params;
}

const applyFilters = (resetPage = true) => {
    router.get("/daily-reports", routeParams(resetPage), {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    Object.assign(filterForm, {
        q: "",
        status: "",
        project_id: "",
        employee_ids: [],
        grade: "",
        from: "",
        to: "",
        late: false,
    });
    applyFilters(true);
};

function onPerPageChange(n) {
    perPage.value = n;
    applyFilters(true);
}

const activeCount = computed(
    () =>
        Object.entries(filterForm).filter(([k, v]) => {
            if (k === "late") return v === true;
            if (k === "employee_ids") return v.length > 0;
            return v !== "" && v != null;
        }).length,
);

let kwTimer = null;
watch(
    () => filterForm.q,
    () => {
        clearTimeout(kwTimer);
        kwTimer = setTimeout(() => applyFilters(true), 350);
    },
);

watch(
    () => [
        filterForm.status,
        filterForm.project_id,
        filterForm.employee_ids,
        filterForm.grade,
        filterForm.from,
        filterForm.to,
        filterForm.late,
    ],
    () => applyFilters(true),
    { deep: true },
);

const HISTORY_FILTER_CONTROLS = [
    { key: "status", label: "Trạng thái", default: false },
    { key: "project", label: "Dự án", default: false },
    { key: "employee", label: "Người báo cáo", default: false },
    { key: "grade", label: "Xếp loại", default: false },
    { key: "date_range", label: "Thời gian", default: false },
];

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(
    HISTORY_FILTER_CONTROLS,
    "va-qlda.reports.visible-filters.v3",
);

const filterPanelDdRef = ref(null);
const colsRef = ref(null);
const colsMenu = ref(false);

const { panelStyle: colsPanelStyle } = useFixedDropdownAnchor(
    () => colsRef.value,
    colsMenu,
    { width: 224, zIndex: 85 },
);

const exportRef = ref(null);
const exportMenu = ref(false);

const { panelStyle: exportPanelStyle } = useFixedDropdownAnchor(
    () => exportRef.value,
    exportMenu,
    { width: 208, zIndex: 85 },
);

const COLS_KEY = "va-qlda.reports.columns";
const columns = reactive([
    {
        key: "employee",
        label: "Người báo cáo",
        visible: props.canFilterEmployee,
        manager: true,
    },
    { key: "title", label: "Tiêu đề", visible: true },
    { key: "projects", label: "Dự án", visible: true },
    { key: "status", label: "Trạng thái", visible: true },
    { key: "score", label: "Điểm", visible: true },
    { key: "feedback", label: "Phản hồi", visible: true },
]);
const visible = (key) => columns.find((c) => c.key === key)?.visible ?? false;

// ---- Grouping (Ngày / Tuần / Tháng) — client-side over the current page ----

function parseYmd(ymd) {
    const [y, m, d] = String(ymd).split("-").map(Number);
    return new Date(y, (m || 1) - 1, d || 1);
}

function isoWeek(dateObj) {
    const date = new Date(dateObj.getTime());
    date.setHours(0, 0, 0, 0);
    date.setDate(date.getDate() + 3 - ((date.getDay() + 6) % 7));
    const week1 = new Date(date.getFullYear(), 0, 4);
    const week =
        1 +
        Math.round(
            ((date - week1) / 86400000 - 3 + ((week1.getDay() + 6) % 7)) / 7,
        );
    return { year: date.getFullYear(), week };
}

function groupOf(dateStr) {
    if (groupMode.value === "month") {
        const [y, m] = String(dateStr).split("-");
        return { key: `${y}-${m}`, label: `Tháng ${m}/${y}` };
    }
    if (groupMode.value === "week") {
        const d = parseYmd(dateStr);
        const { year, week } = isoWeek(d);
        const monday = new Date(d);
        monday.setDate(d.getDate() - ((d.getDay() + 6) % 7));
        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);
        const range = `${pad2(monday.getDate())}/${pad2(monday.getMonth() + 1)}–${pad2(sunday.getDate())}/${pad2(sunday.getMonth() + 1)}`;
        return {
            key: `${year}-W${pad2(week)}`,
            label: `Tuần ${week}/${year} · ${range}`,
        };
    }
    return { key: dateStr, label: formatDate(dateStr) };
}

const grouped = computed(() => {
    const order = [];
    const map = new Map();
    for (const r of props.reports.data ?? []) {
        const g = groupOf(r.date);
        if (!map.has(g.key)) {
            map.set(g.key, { key: g.key, label: g.label, items: [] });
            order.push(g.key);
        }
        map.get(g.key).items.push(r);
    }
    return order.map((key) => {
        const grp = map.get(key);
        return {
            ...grp,
            items: [...grp.items].sort((a, b) =>
                (a.employee?.name ?? "").localeCompare(
                    b.employee?.name ?? "",
                    "vi",
                ),
            ),
        };
    });
});

const tableRows = computed(() => [...(props.reports.data ?? [])]);

function projectChips(report) {
    return (report.projects ?? [])
        .map((p) => p?.code || p?.name)
        .filter(Boolean)
        .slice(0, 3);
}

function isGroupCollapsed(key) {
    return collapsedGroups.value[key] === true;
}

function toggleGroup(key) {
    collapsedGroups.value[key] = !collapsedGroups.value[key];
}

function setViewMode(mode) {
    viewMode.value = mode;
    localStorage.setItem(VIEW_KEY, mode);
}

function setGroupMode(mode) {
    if (groupMode.value === mode) return;
    groupMode.value = mode;
    collapsedGroups.value = {};
    localStorage.setItem(GROUP_KEY, mode);
    applyFilters(false);
}

function onKpiStatus(status) {
    filterForm.late = false;
    filterForm.status = status;
}

function onKpiToggleLate() {
    filterForm.late = !filterForm.late;
    if (filterForm.late) filterForm.status = "";
}

const persistColumns = () => {
    localStorage.setItem(
        COLS_KEY,
        JSON.stringify(
            Object.fromEntries(columns.map((c) => [c.key, c.visible])),
        ),
    );
};

async function runExport(format) {
    exportMenu.value = false;
    if (exporting.value) return;
    exporting.value = true;
    toast.info("Đang chuẩn bị dữ liệu xuất…");
    try {
        const result = await exportDailyReportHistory({
            params: routeParams(false),
            filters: { ...filterForm, group: groupMode.value },
            meta: props.reports.meta,
            format,
        });
        if (!result) {
            toast.warning("Không có dữ liệu để xuất.");
        } else {
            toast.success(`Đã tải ${result.filename}`);
            if (result.truncated) {
                const limit = result.limit?.toLocaleString("vi-VN") ?? result.limit;
                const total = result.total?.toLocaleString("vi-VN") ?? result.total;
                toast.warning(
                    `Chỉ xuất ${limit}/${total} báo cáo (giới hạn hệ thống). Hãy thu hẹp bộ lọc (khoảng ngày, nhân sự) để xuất đầy đủ.`,
                );
            }
        }
    } catch (e) {
        toast.error("Xuất dữ liệu thất bại. Vui lòng thử lại.");
        console.error(e);
    } finally {
        exporting.value = false;
    }
}

onMounted(() => {
    try {
        const savedView = localStorage.getItem(VIEW_KEY);
        if (savedView === "cards" || savedView === "table")
            viewMode.value = savedView;

        if (!VALID_GROUPS.includes(props.filters.group)) {
            const savedGroup = localStorage.getItem(GROUP_KEY);
            if (VALID_GROUPS.includes(savedGroup)) groupMode.value = savedGroup;
        }

        if (!props.canFilterEmployee) visibleFilters.value.employee = false;

        const saved = JSON.parse(localStorage.getItem(COLS_KEY) || "null");
        if (saved) {
            if (saved.grade !== undefined && saved.score === undefined)
                saved.score = saved.grade;
            columns.forEach((c) => {
                if (
                    c.key in saved &&
                    !(c.manager && !props.canFilterEmployee)
                ) {
                    c.visible = !!saved[c.key];
                }
            });
        }
    } catch {
        /* ignore */
    }
    document.addEventListener("click", onDocClick);
});
onBeforeUnmount(() => document.removeEventListener("click", onDocClick));

const onDocClick = (e) => {
    const t = e.target;
    if (t.closest?.("[data-filter-visibility-panel]")) return;
    if (t.closest?.("[data-daily-report-toolbar-panel]")) return;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(t))
        showFilterPanelDd.value = false;
    if (colsMenu.value && colsRef.value && !colsRef.value.contains(t))
        colsMenu.value = false;
    if (exportMenu.value && exportRef.value && !exportRef.value.contains(t))
        exportMenu.value = false;
};

const removeReport = (r) => {
    confirmDelete(
        `Xoá báo cáo nháp "${r.title}" (${r.date})? Thao tác không thể hoàn tác.`,
        () => router.delete(`/daily-reports/${r.id}`, { preserveScroll: true }),
    );
};

function hasFeedback(r) {
    if (r.has_feedback) return true;
    if (r.status === "draft" && (r.review_notes || "").trim()) return true;
    return Boolean((r.score?.notes || "").trim());
}

const GROUP_TABS = [
    { key: "day", label: "Ngày", icon: "calendar" },
    { key: "week", label: "Tuần", icon: "weekly" },
    { key: "month", label: "Tháng", icon: "calendar-clock" },
];

const VIEW_TABS = [
    { key: "cards", label: "Thẻ", icon: "cards", title: "Thẻ báo cáo" },
    { key: "table", label: "Bảng", icon: "table", title: "Bảng danh sách" },
];

const FILTER_CONTROL_CLASS = "input h-10 w-full text-sm";
</script>

<template>
  <Head title="Lịch sử báo cáo" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Lịch sử báo cáo"
        subtitle="Theo dõi báo cáo HORENSO theo ngày, tuần và tháng"
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

    <div class="ui-compact">
      <DailyReportSummaryBar
        :summary="summary"
        :status-filter="filterForm.status"
        :late-filter="filterForm.late"
        @set-status="onKpiStatus"
        @toggle-late="onKpiToggleLate"
      />

      <div
        class="card mb-4 overflow-visible"
      >
        <div
          class="border-b border-slate-100 px-5 py-4 dark:border-slate-700"
        >
          <div
            class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap"
          >
            <div
              class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto"
            >
              <DatagridToolbarSearch
                v-model="filterForm.q"
                input-id="daily-reports-search"
                placeholder="Tiêu đề, tên nhân viên…"
                stretch
                inline-actions
                hide-label
                input-height="h-10"
              />
            </div>

            <div
              class="flex shrink-0 items-center gap-2"
            >
              <div
                ref="filterPanelDdRef"
                class="relative shrink-0"
              >
                <DatagridToolbarActionButton
                  icon="filter"
                  :active="showFilterPanelDd"
                  :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
                  @click="
                    openFilterPanel(() => {
                      colsMenu = false;
                      exportMenu = false;
                    })
                  "
                >
                  Lọc
                </DatagridToolbarActionButton>
                <FilterVisibilityDropdown
                  v-model="visibleFilters"
                  :show="showFilterPanelDd"
                  :anchor-ref="filterPanelDdRef"
                  :controls="
                    FILTER_CONTROLS.filter(
                      (f) =>
                        f.key !== 'employee' ||
                        canFilterEmployee,
                    )
                  "
                  @persist="persistVisibleFilters"
                />
              </div>

              <div
                ref="colsRef"
                class="relative shrink-0"
              >
                <DatagridToolbarActionButton
                  icon="columns"
                  :active="colsMenu"
                  title="Cột hiển thị (chế độ bảng)"
                  @click="
                    colsMenu = !colsMenu;
                    exportMenu = false;
                    showFilterPanelDd = false;
                  "
                >
                  Cột
                </DatagridToolbarActionButton>
              </div>

              <div
                ref="exportRef"
                class="relative shrink-0"
              >
                <DatagridToolbarActionButton
                  icon="export"
                  :active="exportMenu"
                  :disabled="exporting"
                  title="Xuất toàn bộ dữ liệu đang lọc"
                  @click="
                    exportMenu = !exportMenu;
                    colsMenu = false;
                    showFilterPanelDd = false;
                  "
                >
                  {{ exporting ? "Đang xuất…" : "Xuất" }}
                </DatagridToolbarActionButton>
              </div>
            </div>

            <div
              class="ml-auto flex min-w-0 shrink-0 flex-wrap items-center justify-end gap-2"
            >
              <DatagridSegmentedControl
                :model-value="groupMode"
                :items="GROUP_TABS"
                aria-label="Nhóm theo thời gian"
                icon-only-below-sm
                @update:model-value="setGroupMode"
              />

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
                v-if="colsMenu"
                :style="colsPanelStyle"
                class="overflow-hidden rounded-card border border-slate-200 bg-white p-1.5 shadow-elevation-2 dark:border-slate-700 dark:bg-slate-900"
                data-daily-report-toolbar-panel
              >
                <p
                  class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400"
                >
                  Hiển thị cột
                </p>
                <label
                  v-for="c in columns"
                  :key="c.key"
                  class="flex cursor-pointer items-center gap-2 rounded-btn px-2 py-1.5 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"
                  :class="{
                    'opacity-40 pointer-events-none':
                      c.manager && !canFilterEmployee,
                  }"
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
            </Transition>
          </Teleport>

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
                class="overflow-hidden rounded-card border border-slate-200 bg-white p-1 shadow-elevation-2 dark:border-slate-700 dark:bg-slate-900"
                data-daily-report-toolbar-panel
              >
                <button
                  type="button"
                  class="flex w-full flex-col rounded-btn px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-800"
                  @click="runExport('xlsx')"
                >
                  <span
                    class="text-sm font-medium text-slate-700 dark:text-slate-200"
                  >Excel báo cáo (7 sheet)</span>
                  <span class="text-[10px] text-slate-400">Toàn bộ kết quả đang lọc</span>
                </button>
                <button
                  type="button"
                  class="flex w-full rounded-btn px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
                  @click="runExport('csv')"
                >
                  CSV (đơn giản)
                </button>
              </div>
            </Transition>
          </Teleport>
        </div>

        <Transition name="fade-slide">
          <div
            v-if="hasFilterRow"
            class="grid grid-cols-1 gap-3 border-t border-slate-100 px-5 py-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6 dark:border-slate-700"
          >
            <DatagridFilterField
              v-if="visibleFilters.status"
            >
              <select
                v-model="filterForm.status"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Trạng thái"
              >
                <option value="">
                  Tất cả trạng thái
                </option>
                <option
                  v-for="s in statuses"
                  :key="s.value"
                  :value="s.value"
                >
                  {{ s.label }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField
              v-if="visibleFilters.project"
            >
              <select
                v-model="filterForm.project_id"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Dự án"
              >
                <option value="">
                  Tất cả dự án
                </option>
                <option
                  v-for="p in projects"
                  :key="p.id"
                  :value="p.id"
                >
                  {{ p.name }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField
              v-if="visibleFilters.employee && canFilterEmployee"
            >
              <SearchMultiSelect
                v-model="filterForm.employee_ids"
                :options="employees"
                show-avatar
                control-size="md"
                placeholder="Người báo cáo"
                search-placeholder="Tìm nhân viên…"
              />
            </DatagridFilterField>

            <DatagridFilterField
              v-if="visibleFilters.grade"
            >
              <select
                v-model="filterForm.grade"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Xếp loại"
              >
                <option value="">
                  Tất cả xếp loại
                </option>
                <option
                  v-for="g in grades"
                  :key="g.value"
                  :value="g.value"
                >
                  {{ g.label }}
                </option>
              </select>
            </DatagridFilterField>

            <div
              v-if="visibleFilters.date_range"
              class="min-w-0 w-full sm:col-span-2 xl:col-span-2"
            >
              <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:gap-3">
                <FilterDatePicker
                  v-model="filterForm.from"
                  placeholder="Từ ngày"
                  :max-date="filterForm.to || null"
                />
                <FilterDatePicker
                  v-model="filterForm.to"
                  placeholder="Đến ngày"
                  :min-date="filterForm.from || null"
                />
              </div>
            </div>

            <div
              v-if="activeCount"
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

        <!-- Card view: grouped by day / week / month -->
        <div
          v-else-if="viewMode === 'cards'"
          class="divide-y divide-slate-100 dark:divide-slate-700"
        >
          <section
            v-for="group in grouped"
            :key="group.key"
            class="min-w-0"
          >
            <button
              type="button"
              class="flex w-full flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50/80 px-4 py-2.5 text-left transition hover:bg-slate-100/80 dark:border-slate-700 dark:bg-slate-800/60 dark:hover:bg-slate-800"
              @click="toggleGroup(group.key)"
            >
              <AppIcon
                :name="
                  isGroupCollapsed(group.key)
                    ? 'chevron-right'
                    : 'chevron-down'
                "
                :size="16"
                class="shrink-0 text-slate-400"
              />
              <span
                class="font-display text-sm font-semibold tabular-nums text-slate-800 dark:text-slate-100"
              >
                {{ group.label }}
              </span>
              <span class="text-xs text-slate-500">
                {{ group.items.length }} báo cáo
              </span>
            </button>

            <div
              v-show="!isGroupCollapsed(group.key)"
              class="grid grid-cols-1 gap-3 px-4 py-3 md:grid-cols-2 xl:grid-cols-3"
            >
              <ReportCard
                v-for="r in group.items"
                :key="r.id"
                :report="r"
                :show-employee="visible('employee')"
                @delete="removeReport"
              />
            </div>
          </section>
        </div>

        <!-- Table view -->
        <div
          v-else
          class="overflow-x-auto"
        >
          <table class="w-full text-sm">
            <thead
              class="border-b border-slate-200 bg-slate-50/80 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:bg-slate-800/60"
            >
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
            <tbody
              class="divide-y divide-slate-100 dark:divide-slate-700"
            >
              <tr
                v-for="r in tableRows"
                :key="r.id"
                class="transition hover:bg-slate-50/80 dark:hover:bg-slate-800/50"
              >
                <td
                  class="whitespace-nowrap px-4 py-3 align-middle text-slate-600 dark:text-slate-300"
                >
                  <span class="tabular-nums">{{
                    formatDate(r.date)
                  }}</span>
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
                    <span
                      class="max-w-[10rem] truncate text-slate-700 dark:text-slate-200"
                    >{{ r.employee?.name ?? "—" }}</span>
                  </div>
                </td>
                <td
                  v-if="visible('title')"
                  class="max-w-md px-4 py-3 align-middle"
                >
                  <Link
                    :href="`/daily-reports/${r.id}`"
                    class="line-clamp-2 font-medium text-slate-800 hover:text-brand dark:text-slate-100"
                  >
                    {{ r.title }}
                  </Link>
                </td>
                <td
                  v-if="visible('projects')"
                  class="max-w-[8rem] truncate px-4 py-3 text-xs text-slate-500"
                  :title="projectChips(r).join(', ')"
                >
                  {{ projectChips(r).join(", ") || "—" }}
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
                    <span
                      class="text-xs font-semibold tabular-nums"
                    >{{
                      Number(
                        r.score.total_score ?? 0,
                      ).toFixed(1)
                    }}</span>
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
                <td
                  class="whitespace-nowrap px-4 py-3 text-right align-middle"
                >
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
    </div>
  </AppLayout>
</template>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.2s ease;
}
.fade-slide-enter-from,
.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>
