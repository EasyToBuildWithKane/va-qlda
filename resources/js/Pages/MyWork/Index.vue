<script setup>
import { computed, reactive, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import MyWorkTaskCard from './partials/MyWorkTaskCard.vue';
import TeamScopeSwitcher from './partials/TeamScopeSwitcher.vue';
import TeamRoster from './partials/TeamRoster.vue';
import { useMyWork } from '@/composables/useMyWork';

const props = defineProps({
    mode: { type: String, default: 'self' },
    viewing: { type: Object, default: null },
    summary: { type: Object, default: null },
    buckets: { type: Object, default: null },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({ priorities: [], statuses: [] }) },
    team: { type: Object, default: () => ({ canTeamView: false, canActTeam: false, members: [] }) },
});

const { changeStatus, logWork, openTask, goTo } = useMyWork();

// ── Header copy ──────────────────────────────────────────────────────────────
const headerSubtitle = computed(() => {
    if (props.mode === 'team') return 'Việc của thành viên nhóm bạn phụ trách';
    if (props.mode === 'member') return `Đang xem việc của ${props.viewing?.name ?? 'thành viên'} · chế độ giám sát`;
    return 'Tất cả công việc được giao cho bạn, ưu tiên hôm nay';
});

// ── KPI strip ────────────────────────────────────────────────────────────────
const summaryCards = computed(() => {
    const s = props.summary ?? {};
    return [
        { key: 'open', label: 'Đang mở', value: s.open ?? 0, icon: 'task', tone: 'brand' },
        { key: 'overdue', label: 'Quá hạn', value: s.overdue ?? 0, icon: 'alert', tone: 'rose' },
        { key: 'today', label: 'Đến hạn hôm nay', value: s.dueToday ?? 0, icon: 'calendar-clock', tone: 'amber' },
        { key: 'inProgress', label: 'Đang làm', value: s.inProgress ?? 0, icon: 'sprint', tone: 'sky' },
        { key: 'hours', label: 'Giờ log hôm nay', value: s.hoursLoggedToday ?? 0, icon: 'worklog', tone: 'emerald' },
    ];
});

const teamCards = computed(() => {
    const members = props.team?.members ?? [];
    const sum = (k) => members.reduce((acc, m) => acc + (m[k] ?? 0), 0);
    return [
        { key: 'members', label: 'Thành viên', value: members.length, icon: 'people', tone: 'brand' },
        { key: 'open', label: 'Tổng việc mở', value: sum('open'), icon: 'task', tone: 'sky' },
        { key: 'overdue', label: 'Quá hạn', value: sum('overdue'), icon: 'alert', tone: 'rose' },
        { key: 'today', label: 'Đến hạn hôm nay', value: sum('dueToday'), icon: 'calendar-clock', tone: 'amber' },
    ];
});

// ── Buckets ──────────────────────────────────────────────────────────────────
const bucketMeta = [
    { key: 'overdue', label: 'Quá hạn', icon: 'alert', alwaysShow: true },
    { key: 'today', label: 'Hôm nay', icon: 'calendar-clock', alwaysShow: false },
    { key: 'upcoming', label: 'Sắp tới', icon: 'clock', alwaysShow: false },
    { key: 'no_due', label: 'Chưa có hạn', icon: 'task', alwaysShow: false },
];

const openState = reactive({ overdue: true, today: true, upcoming: true, no_due: false });
const bucketOf = (key) => props.buckets?.[key] ?? [];
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
      >
        <TeamScopeSwitcher
          v-if="team.canTeamView"
          :mode="mode"
          class="ml-auto"
          @select="onScope"
        />
      </PageHeader>
    </template>

    <div class="mx-auto w-full max-w-5xl space-y-4 p-4">
      <!-- Oversight banner -->
      <div
        v-if="mode === 'member' && viewing"
        class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm dark:border-amber-900/40 dark:bg-amber-950/30"
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

      <!-- ═══ TEAM ROSTER ═══ -->
      <template v-if="mode === 'team'">
        <KpiSummaryStrip
          :cards="teamCards"
          aria-label="Tổng quan nhóm"
          heading="Tổng quan nhóm của tôi"
          eyebrow="Nhóm"
          grid-class="grid-cols-2 lg:grid-cols-4"
        />
        <TeamRoster
          :members="team.members"
          @select="(id) => goTo({ member: id })"
        />
      </template>

      <!-- ═══ SELF / MEMBER BUCKETS ═══ -->
      <template v-else>
        <KpiSummaryStrip
          :cards="summaryCards"
          aria-label="Tổng quan việc của tôi"
          :heading="mode === 'member' ? 'Tổng quan công việc' : 'Tổng quan việc của tôi'"
          eyebrow="Hôm nay"
        />

        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-2">
          <div class="relative min-w-[12rem] flex-1">
            <AppIcon
              name="search"
              :size="15"
              class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400"
            />
            <input
              v-model="filters.q"
              type="search"
              placeholder="Tìm theo tên việc…"
              class="w-full rounded-lg border border-slate-200 py-1.5 pl-8 pr-3 text-sm outline-none focus:border-brand focus:ring-1 focus:ring-brand dark:border-slate-700 dark:bg-slate-900"
            >
          </div>
          <select
            v-model="filters.project_id"
            class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm outline-none focus:border-brand dark:border-slate-700 dark:bg-slate-900"
          >
            <option value="">
              Tất cả dự án
            </option>
            <option
              v-for="p in projectOptions"
              :key="p.id"
              :value="p.id"
            >
              {{ p.name }}
            </option>
          </select>
          <select
            v-model="filters.priority"
            class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm outline-none focus:border-brand dark:border-slate-700 dark:bg-slate-900"
          >
            <option value="">
              Mọi ưu tiên
            </option>
            <option
              v-for="opt in options.priorities"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </option>
          </select>
          <select
            v-model="filters.status"
            class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm outline-none focus:border-brand dark:border-slate-700 dark:bg-slate-900"
          >
            <option value="">
              Đang mở
            </option>
            <option
              v-for="opt in options.statuses"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </option>
          </select>
          <button
            v-if="hasActiveFilters"
            type="button"
            class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
            @click="resetFilters"
          >
            <AppIcon
              name="close"
              :size="13"
            />
            Xoá lọc
          </button>
        </div>

        <!-- Empty state -->
        <div
          v-if="totalTasks === 0"
          class="rounded-xl border border-dashed border-slate-200 bg-white px-5 py-12 text-center dark:border-slate-700 dark:bg-slate-900"
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

        <!-- Buckets -->
        <template v-else>
          <section
            v-for="b in bucketMeta"
            v-show="bucketOf(b.key).length > 0 || b.alwaysShow"
            :key="b.key"
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
      </template>
    </div>
  </AppLayout>
</template>
