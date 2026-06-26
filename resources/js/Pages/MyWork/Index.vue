<script setup>
import { computed, reactive, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import DatagridSegmentedControl from '@/shared/ui/DatagridSegmentedControl.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import MyWorkSummaryBar from './partials/MyWorkSummaryBar.vue';
import MyWorkTeamSummaryBar from './partials/MyWorkTeamSummaryBar.vue';
import MyWorkTaskCard from './partials/MyWorkTaskCard.vue';
import TeamScopeSwitcher from './partials/TeamScopeSwitcher.vue';
import TeamScopeBanner from './partials/TeamScopeBanner.vue';
import TeamRoster from './partials/TeamRoster.vue';
import TeamWorkDepartmentLanes from './partials/TeamWorkDepartmentLanes.vue';
import MemberWorkModal from './partials/MemberWorkModal.vue';
import { useMyWork } from '@/composables/useMyWork';
import { useToast } from '@/shared/composables/useToast';
import { exportMyWorkTasks, exportTeamRoster } from '@/composables/useMyWorkExport';

const props = defineProps({
    mode: { type: String, default: 'self' },
    viewing: { type: Object, default: null },
    summary: { type: Object, default: null },
    teamSummary: { type: Object, default: null },
    teamScope: { type: Object, default: null },
    teamDepartmentLanes: { type: Object, default: () => ({ lanes: [] }) },
    buckets: { type: Object, default: null },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({ priorities: [], statuses: [] }) },
    team: { type: Object, default: () => ({ canTeamView: false, canActTeam: false, members: [] }) },
});

const { changeStatus, logWork, openTask, goTo } = useMyWork();
const toast = useToast();

// ── Modal "Xem nhanh" việc của thành viên (chế độ nhóm) ──────────────────────
const quickViewMember = ref(null);
const showQuickView = ref(false);

function onQuickView(member) {
    quickViewMember.value = member;
    showQuickView.value = true;
}

// ── Xuất Excel ───────────────────────────────────────────────────────────────
function exportSelf() {
    const ok = exportMyWorkTasks({
        buckets: props.buckets ?? {},
        ownerName: props.viewing?.name ?? 'Tôi',
        summary: props.summary,
    });
    if (ok) toast.success('Đã xuất Excel việc của ' + (props.viewing?.isSelf ? 'tôi' : (props.viewing?.name ?? 'thành viên')) + '.');
    else toast.error('Không có việc để xuất.');
}

function exportTeam() {
    const ok = exportTeamRoster({
        members: props.team?.members ?? [],
        summary: props.teamSummary,
        scopeLabel: props.teamScope?.scopeLabel ?? '',
    });
    if (ok) toast.success('Đã xuất Excel tải việc nhóm.');
    else toast.error('Nhóm chưa có thành viên để xuất.');
}

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const MY_WORK_FILTER_CONTROLS = [
    { key: 'project_id', label: 'Dự án', default: false },
    { key: 'priority', label: 'Ưu tiên', default: false },
    { key: 'status', label: 'Trạng thái', default: false },
];

const TEAM_FILTER_CONTROLS = [
    { key: 'role', label: 'Vai trò', default: false },
];

const filterPanelDdRef = ref(null);
const teamFilterPanelDdRef = ref(null);

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(MY_WORK_FILTER_CONTROLS, 'va-qlda.my-work.visible-filters.v1');

const {
    visibleFilters: teamVisibleFilters,
    showFilterPanelDd: showTeamFilterPanelDd,
    enabledFilterControlCount: teamEnabledFilterCount,
    hasFilterRow: hasTeamFilterRow,
    persistVisibleFilters: persistTeamVisibleFilters,
    openFilterPanel: openTeamFilterPanel,
    FILTER_CONTROLS: TEAM_FILTER_CONTROLS_LIST,
} = useVisibleFilterControls(TEAM_FILTER_CONTROLS, 'va-qlda.my-work.team-visible-filters.v1');

function onToolbarClickOutside(e) {
    if (e.target.closest?.('[data-filter-visibility-panel]')) return;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
    if (teamFilterPanelDdRef.value && !teamFilterPanelDdRef.value.contains(e.target)) {
        showTeamFilterPanelDd.value = false;
    }
}
onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));

// ── Header copy ──────────────────────────────────────────────────────────────
const headerSubtitle = computed(() => {
    if (props.mode === 'team') return 'Việc của thành viên nhóm bạn phụ trách';
    if (props.mode === 'member') return `Đang xem việc của ${props.viewing?.name ?? 'thành viên'} · chế độ giám sát`;
    return 'Tất cả công việc được giao cho bạn, ưu tiên hôm nay';
});

const headerBadge = computed(() => {
    if (props.mode === 'team') return props.teamSummary?.atRisk ?? null;
    return props.summary?.overdue ?? null;
});

// ── Buckets ──────────────────────────────────────────────────────────────────
const bucketMeta = [
    { key: 'overdue', label: 'Quá hạn', icon: 'alert', alwaysShow: true, highlight: 'overdue' },
    { key: 'today', label: 'Hôm nay', icon: 'calendar-clock', alwaysShow: false, highlight: 'today' },
    { key: 'upcoming', label: 'Sắp tới', icon: 'clock', alwaysShow: false, highlight: 'open' },
    { key: 'no_due', label: 'Chưa có hạn', icon: 'task', alwaysShow: false, highlight: 'open' },
];

const openState = reactive({ overdue: true, today: true, upcoming: true, no_due: false });
const bucketOf = (key) => props.buckets?.[key] ?? [];

const bucketHighlight = ref('');

const visibleBuckets = computed(() => {
    const h = bucketHighlight.value;
    if (!h || h === 'open' || h === 'inProgress') {
        if (h === 'inProgress') {
            return bucketMeta.filter((b) => b.key !== 'no_due');
        }
        return bucketMeta;
    }
    if (h === 'overdue') return bucketMeta.filter((b) => b.key === 'overdue');
    if (h === 'today') return bucketMeta.filter((b) => b.key === 'today');
    return bucketMeta;
});

const totalTasks = computed(() =>
    bucketMeta.reduce((acc, b) => acc + bucketOf(b.key).length, 0),
);

// ── Filters (URL-bound, preserves ?member context) ───────────────────────────
const filters = reactive({
    q: props.filters.q ?? '',
    project_id: props.filters.project_id ?? '',
    priority: props.filters.priority ?? '',
    status: props.filters.status ?? '',
});

const projectOptions = computed(() => {
    const seen = new Map();
    bucketMeta.forEach((b) => bucketOf(b.key).forEach((t) => {
        if (t.project && !seen.has(t.project.id)) seen.set(t.project.id, t.project);
    }));
    return [...seen.values()];
});

let timer = null;
function apply() {
    const params = {};
    Object.entries(filters).forEach(([k, v]) => {
        if (v !== '' && v != null) params[k] = v;
    });
    if (props.mode === 'member' && props.viewing) params.member = props.viewing.id;
    router.get('/my-work', params, { preserveScroll: true, preserveState: true, replace: true });
}
function debouncedApply() {
    clearTimeout(timer);
    timer = setTimeout(apply, 350);
}
watch(() => filters.q, debouncedApply);
watch(() => [filters.project_id, filters.priority, filters.status], apply);

const hasActiveFilters = computed(
    () => filters.q || filters.project_id || filters.priority || filters.status,
);
function resetFilters() {
    filters.q = '';
    filters.project_id = '';
    filters.priority = '';
    filters.status = '';
}

const TEAM_VIEW_KEY = 'va-qlda.my-work.team-view.v1';

const teamView = ref(
    typeof localStorage !== 'undefined' && localStorage.getItem(TEAM_VIEW_KEY) === 'member'
        ? 'member'
        : 'department',
);

watch(teamView, (v) => {
    if (typeof localStorage !== 'undefined') localStorage.setItem(TEAM_VIEW_KEY, v);
});

const teamViewItems = [
    { key: 'department', label: 'Phòng ban dự án', icon: 'building', title: 'Hàng ngang theo phòng ban phụ trách dự án' },
    { key: 'member', label: 'Thành viên', icon: 'people', title: 'Bảng theo nhân sự trong phạm vi nhóm' },
];

const teamLanes = computed(() => props.teamDepartmentLanes?.lanes ?? []);

// ── Team roster filters (client) ─────────────────────────────────────────────
const teamFilters = reactive({
    q: '',
    role: '',
    highlight: 'all',
});

const teamRoleOptions = computed(() => {
    const roles = new Set();
    (props.team?.members ?? []).forEach((m) => {
        const r = (m.role_title ?? '').trim();
        if (r) roles.add(r);
    });
    return [...roles].sort((a, b) => a.localeCompare(b, 'vi'));
});

const filteredTeamMembers = computed(() => {
    const q = teamFilters.q.trim().toLowerCase();
    let list = props.team?.members ?? [];

    if (q) {
        list = list.filter((m) =>
            (m.name ?? '').toLowerCase().includes(q)
            || (m.role_title ?? '').toLowerCase().includes(q),
        );
    }

    if (teamFilters.role) {
        list = list.filter((m) => (m.role_title ?? '') === teamFilters.role);
    }

    const h = teamFilters.highlight;
    if (h === 'overdue') list = list.filter((m) => (m.overdue ?? 0) > 0);
    else if (h === 'dueToday') list = list.filter((m) => (m.dueToday ?? 0) > 0);
    else if (h === 'atRisk') list = list.filter((m) => (m.overdue ?? 0) > 0 || (m.dueToday ?? 0) > 0);
    else if (h === 'clear') list = list.filter((m) => (m.open ?? 0) === 0);
    else if (h === 'inProgress') list = list.filter((m) => (m.inProgress ?? 0) > 0);

    return list;
});

const teamHasActiveFilters = computed(
    () => teamFilters.q || teamFilters.role || (teamFilters.highlight && teamFilters.highlight !== 'all'),
);

function resetTeamFilters() {
    teamFilters.q = '';
    teamFilters.role = '';
    teamFilters.highlight = 'all';
}

function onTeamQuickFilter({ highlight }) {
    teamFilters.highlight = highlight ?? 'all';
}

function onSelfQuickFilter({ highlight }) {
    bucketHighlight.value = highlight ?? '';
    if (highlight === 'overdue') openState.overdue = true;
    if (highlight === 'today') openState.today = true;
    if (highlight === 'open') {
        openState.overdue = true;
        openState.today = true;
        openState.upcoming = true;
    }
}

// ── Scope navigation ─────────────────────────────────────────────────────────
function onScope(scope) {
    goTo({ scope });
}
</script>

<template>
  <Head title="Việc hôm nay" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Việc hôm nay"
        :subtitle="headerSubtitle"
        icon="calendar-clock"
        icon-color="brand"
        :badge="headerBadge"
      >
        <TeamScopeSwitcher
          v-if="team.canTeamView"
          :mode="mode"
          class="ml-auto"
          @select="onScope"
        />
      </PageHeader>
    </template>

    <!-- Oversight banner -->
    <div
      v-if="mode === 'member' && viewing"
      class="mb-4 flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm dark:border-amber-900/40 dark:bg-amber-950/30"
    >
      <Avatar
        :name="viewing.name"
        :src="viewing.avatar_path"
        :size="32"
      />
      <div class="min-w-0 flex-1">
        <p class="font-semibold text-amber-900 dark:text-amber-200">
          {{ viewing.name }}
        </p>
        <p class="text-[12px] text-amber-700/80 dark:text-amber-300/70">
          {{ viewing.role_title || 'Thành viên nhóm' }} · chế độ giám sát
        </p>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium text-amber-800 hover:bg-amber-100 dark:text-amber-200 dark:hover:bg-amber-900/40"
        @click="onScope('team')"
      >
        <AppIcon
          name="chevron-left"
          :size="14"
        />
        Về nhóm
      </button>
    </div>

    <!-- ═══ TEAM ═══ -->
    <template v-if="mode === 'team'">
      <MyWorkTeamSummaryBar
        v-if="teamSummary"
        :summary="teamSummary"
        :active-highlight="teamFilters.highlight"
        @quick-filter="onTeamQuickFilter"
      />

      <TeamScopeBanner :team-scope="teamScope" />

      <div class="card overflow-visible">
        <div class="border-b border-slate-100 px-5 py-4">
          <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
            <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
              <DatagridToolbarSearch
                v-model="teamFilters.q"
                input-id="my-work-team-search"
                input-name="team_q"
                :placeholder="teamView === 'department' ? 'Tên dự án, mã hoặc thành viên…' : 'Tên hoặc vai trò thành viên…'"
                stretch
                inline-actions
                hide-label
                input-height="h-10"
              />
            </div>
            <div class="flex shrink-0 items-center gap-2">
              <div
                ref="teamFilterPanelDdRef"
                class="relative shrink-0"
              >
                <DatagridToolbarActionButton
                  icon="filter"
                  :active="showTeamFilterPanelDd"
                  :title="`Hiển thị bộ lọc (${teamEnabledFilterCount}/${TEAM_FILTER_CONTROLS_LIST.length})`"
                  @click="openTeamFilterPanel()"
                >
                  Lọc
                </DatagridToolbarActionButton>
                <FilterVisibilityDropdown
                  v-model="teamVisibleFilters"
                  :show="showTeamFilterPanelDd"
                  :anchor-ref="teamFilterPanelDdRef"
                  :controls="TEAM_FILTER_CONTROLS_LIST"
                  input-id-prefix="my-work-team-filter-vis"
                  @persist="persistTeamVisibleFilters"
                />
              </div>
              <button
                v-if="teamHasActiveFilters"
                type="button"
                class="btn-ghost inline-flex h-10 items-center gap-1 px-2.5 text-xs"
                @click="resetTeamFilters"
              >
                <AppIcon
                  name="close"
                  :size="13"
                />
                Xoá lọc
              </button>
              <button
                type="button"
                class="btn-ghost inline-flex h-10 items-center gap-1.5 px-2.5 text-xs font-medium"
                :disabled="(team.members ?? []).length === 0"
                title="Xuất Excel tải việc theo thành viên"
                @click="exportTeam"
              >
                <AppIcon
                  name="download"
                  :size="14"
                />
                Xuất Excel
              </button>
            </div>
            <DatagridSegmentedControl
              v-model="teamView"
              class="ml-auto w-full basis-full sm:w-auto sm:basis-auto"
              aria-label="Cách nhóm danh sách nhóm"
              :items="teamViewItems"
              icon-only-below-sm
            />
          </div>

          <div
            v-if="hasTeamFilterRow"
            class="grid grid-cols-1 gap-3 border-t border-slate-100 px-0 pt-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
          >
            <DatagridFilterField
              v-if="teamVisibleFilters.role"
            >
              <select
                v-model="teamFilters.role"
                :class="FILTER_CONTROL_CLASS"
              >
                <option value="">
                  Vai trò
                </option>
                <option
                  v-for="role in teamRoleOptions"
                  :key="role"
                  :value="role"
                >
                  {{ role }}
                </option>
              </select>
            </DatagridFilterField>
          </div>
        </div>

        <div
          v-if="(team.members ?? []).length === 0"
          class="px-5 py-12 text-center text-sm text-slate-500"
        >
          <AppIcon
            name="people"
            :size="32"
            class="mx-auto mb-2 text-slate-300"
          />
          Bạn chưa phụ trách nhóm nào, hoặc nhóm chưa có thành viên.
        </div>
        <TeamWorkDepartmentLanes
          v-else-if="teamView === 'department'"
          :lanes="teamLanes"
          :search-query="teamFilters.q"
          @select-member="(id) => goTo({ member: id })"
          @quick-view="onQuickView"
        />
        <TeamRoster
          v-else
          :members="filteredTeamMembers"
          :team-open-total="teamSummary?.open ?? 0"
          @select="(id) => goTo({ member: id })"
          @quick-view="onQuickView"
        />
      </div>
    </template>

    <!-- ═══ SELF / MEMBER ═══ -->
    <template v-else>
      <MyWorkSummaryBar
        v-if="summary"
        :summary="summary"
        :active-highlight="bucketHighlight"
        :heading="mode === 'member' ? 'Tổng quan công việc' : 'Tổng quan việc của tôi'"
        @quick-filter="onSelfQuickFilter"
      />

      <div class="card overflow-visible">
        <div class="border-b border-slate-100 px-5 py-4">
          <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
            <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
              <DatagridToolbarSearch
                v-model="filters.q"
                input-id="my-work-search"
                input-name="q"
                placeholder="Tìm theo tên việc…"
                stretch
                inline-actions
                hide-label
                input-height="h-10"
              />
            </div>
            <div class="flex shrink-0 items-center gap-2">
              <div
                ref="filterPanelDdRef"
                class="relative shrink-0"
              >
                <DatagridToolbarActionButton
                  icon="filter"
                  :active="showFilterPanelDd"
                  :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
                  @click="openFilterPanel()"
                >
                  Lọc
                </DatagridToolbarActionButton>
                <FilterVisibilityDropdown
                  v-model="visibleFilters"
                  :show="showFilterPanelDd"
                  :anchor-ref="filterPanelDdRef"
                  :controls="FILTER_CONTROLS"
                  input-id-prefix="my-work-filter-vis"
                  @persist="persistVisibleFilters"
                />
              </div>
              <button
                v-if="hasActiveFilters"
                type="button"
                class="btn-ghost inline-flex h-10 items-center gap-1 px-2.5 text-xs"
                @click="resetFilters"
              >
                <AppIcon
                  name="close"
                  :size="13"
                />
                Xoá lọc
              </button>
              <button
                type="button"
                class="btn-ghost inline-flex h-10 items-center gap-1.5 px-2.5 text-xs font-medium"
                :disabled="totalTasks === 0"
                title="Xuất Excel danh sách việc"
                @click="exportSelf"
              >
                <AppIcon
                  name="download"
                  :size="14"
                />
                Xuất Excel
              </button>
            </div>
          </div>

          <div
            v-if="hasFilterRow"
            class="grid grid-cols-1 gap-3 border-t border-slate-100 px-0 pt-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
          >
            <DatagridFilterField
              v-if="visibleFilters.project_id"
            >
              <select
                v-model="filters.project_id"
                :class="FILTER_CONTROL_CLASS"
              >
                <option value="">
                  Dự án
                </option>
                <option
                  v-for="p in projectOptions"
                  :key="p.id"
                  :value="p.id"
                >
                  {{ p.name }}
                </option>
              </select>
            </DatagridFilterField>
            <DatagridFilterField
              v-if="visibleFilters.priority"
            >
              <select
                v-model="filters.priority"
                :class="FILTER_CONTROL_CLASS"
              >
                <option value="">
                  Ưu tiên
                </option>
                <option
                  v-for="opt in options.priorities"
                  :key="opt.value"
                  :value="opt.value"
                >
                  {{ opt.label }}
                </option>
              </select>
            </DatagridFilterField>
            <DatagridFilterField
              v-if="visibleFilters.status"
            >
              <select
                v-model="filters.status"
                :class="FILTER_CONTROL_CLASS"
              >
                <option value="">
                  Trạng thái
                </option>
                <option
                  v-for="opt in options.statuses"
                  :key="opt.value"
                  :value="opt.value"
                >
                  {{ opt.label }}
                </option>
              </select>
            </DatagridFilterField>
          </div>
        </div>

        <div class="px-5 py-4">
          <div
            v-if="totalTasks === 0"
            class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 px-5 py-12 text-center dark:border-slate-700"
          >
            <AppIcon
              name="check-circle"
              :size="32"
              class="mx-auto mb-2 text-emerald-400"
            />
            <p class="text-sm font-medium text-slate-600 dark:text-slate-300">
              {{ hasActiveFilters ? 'Không có việc khớp bộ lọc.' : 'Tuyệt vời — không có việc nào đang chờ!' }}
            </p>
          </div>

          <template v-else>
            <section
              v-for="b in visibleBuckets"
              v-show="bucketOf(b.key).length > 0 || b.alwaysShow"
              :key="b.key"
              class="mb-4 last:mb-0"
            >
              <button
                type="button"
                class="flex w-full items-center gap-2 py-1.5 text-left"
                @click="openState[b.key] = !openState[b.key]"
              >
                <AppIcon
                  :name="openState[b.key] ? 'chevron-down' : 'chevron-right'"
                  :size="15"
                  class="text-slate-400"
                />
                <AppIcon
                  :name="b.icon"
                  :size="15"
                  :class="b.key === 'overdue' ? 'text-rose-500' : 'text-slate-400'"
                />
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ b.label }}</span>
                <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-500 dark:bg-slate-800">
                  {{ bucketOf(b.key).length }}
                </span>
              </button>

              <div
                v-show="openState[b.key]"
                class="mt-1.5 space-y-2"
              >
                <p
                  v-if="bucketOf(b.key).length === 0"
                  class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-400 dark:bg-slate-800/50"
                >
                  Không có việc nào.
                </p>
                <MyWorkTaskCard
                  v-for="task in bucketOf(b.key)"
                  :key="task.id"
                  :task="task"
                  :mode="mode"
                  :status-options="options.statuses"
                  @change-status="changeStatus"
                  @log-work="logWork"
                  @open="openTask"
                />
              </div>
            </section>
          </template>
        </div>
      </div>
    </template>

    <MemberWorkModal
      :open="showQuickView"
      :member="quickViewMember"
      @close="showQuickView = false"
    />
  </AppLayout>
</template>
